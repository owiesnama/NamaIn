# PRD — M4: Free-form mode + single-point enforcement

**Status:** Draft · **Milestone:** M4 · **Depends on:** M3 (strategy layer) · **Ship with:** M8 (provisional costing) · **PR grouping:** one PR

## 1. Problem

Today stock can only be *added* by creating a purchase invoice, and sales are *blocked* the moment on-hand would go below zero — the `stocks.quantity` column is `UNSIGNED`, so negatives are physically impossible, and `Storage::deductStock` throws unconditionally on a shortage. A large merchant segment does not want inventory to gate sales at all: they want to sell freely and let the balance go negative for later reconciliation. Another segment wants the strict discipline preserved. M3 introduced a per-tenant `InventoryStrategy`; M4 makes it *actually change behavior* by (a) allowing the physical representation of negative stock and (b) routing every sufficiency decision through the strategy layer at the single existing choke point — never scattering `if (overselling)` checks across controllers. Because free-form overselling makes the pre-existing zero/stale-COGS bug frequent, M4 ships together with M8.

## 2. Goals / Non-goals

**Goals**
- Make `stocks.quantity` capable of holding negative balances (signed column).
- Enforce the three strategy behaviors at one place (`Storage::deductStock`), inside the existing row lock:
  - **`purchase_driven`** → sales check the balance; block if the sale would drive it negative.
  - **`free_form` + `allow_overselling` ON** → sales never block; the balance goes negative and is surfaced for reconciliation.
  - **`free_form` + `allow_overselling` OFF** → sales still block at zero (free quantity management without purchase invoices, guardrail retained).
- Make the POS precheck and preflight *advisory-consistent* with the strategy (never the authority).
- Gate manual upward adjustments by strategy (free under `free_form`; blocked/flagged under `purchase_driven`).

**Non-goals**
- The strategy resolver/contract itself (delivered in M3).
- Surfacing negatives in reports / the negative-stock report (M7).
- Provisional cost handling of oversold lines (M8, shipped alongside).
- Reconciliation/opening balances (M9). Reads still come from `stocks.quantity` (cache), unchanged here.

## 3. Current state (from audit)

- `stocks.quantity` is `unsignedBigInteger` (`database/migrations/2023_01_07_162613_create_stocks_table.php`, widened in `2026_04_10_181858_fix_stocks_quantity_type_for_postgres.php`) — the DB forbids negatives.
- Only hard guard: `Storage::deductStock` (`app/Models/Storage.php:144-168`) locks the `stocks` row `forUpdate` and throws `InsufficientStockException` (`:156-158`) if the row is missing or `quantity < requested`. Every deduction ultimately hits this.
- POS adds an upfront precheck: `ProcessPosCheckoutAction.php:67-81` compares `Storage::quantityOf` vs needed and throws (`:76`), or triggers cross-warehouse `replenish()` (`:170-198`) when transfers are acknowledged. `PosPreflightAction.php:45-62` returns advisory (non-blocking) JSON.
- Non-POS sales invoice creation does **not** check/deduct stock; the invoice-deduction path (`DeductStockFromInvoice.php:27-31`) prefers **partial-delivery splitting** over blocking.
- Adjustments: `RecordAdjustmentAction` → `Storage::setStockTo` (`app/Models/Storage.php:173`); UI `resources/js/Components/Storages/AdjustmentModal.vue`; request `StockAdjustmentRequest` (`new_quantity min:0`, `type` unconstrained server-side). No strategy gate today; the only guard is permission `inventory.manage`.

## 4. Design & behavior

The strategy is resolved once per operation (M3) and consulted **inside** `deductStock`'s locked transaction so the allow/deny decision cannot race the balance it read. `deductStock` gains one branch:

```
lock row → available = quantity
if strategy.allowsOverselling():        // free_form + ON
    decrement (may go negative), record movement
else:
    strategy.assertCanDeduct(available, requested)  // throws InsufficientStockException at zero
    decrement, record movement
```

`purchase_driven` and `free_form`-OFF both take the `else` branch (identical block-at-zero behavior); only overselling ON permits the decrement past zero. This keeps enforcement in exactly one method — controllers, POS, and delivery actions call `deductStock` and inherit correct behavior automatically.

POS precheck and preflight consult `strategy.allowsOverselling()` so the UX matches: when overselling is ON they stop warning/blocking on shortage (and skip the replenishment prompt), but `deductStock` remains the sole authority. Manual upward adjustments consult `strategy.allowsManualStockIncrease()` (M3): under `purchase_driven` an upward `setStockTo` is blocked (or permitted only with an explicit, clearly-flagged reason); under `free_form` it is unrestricted.

All existing tenants default to `purchase_driven` (M3), so behavior is bit-for-bit unchanged until a tenant opts into free-form.

## 5. Data model / schema changes

- **New migration**: convert `stocks.quantity` from unsigned to **signed** `BIGINT`. Follow the driver-split pattern of `2026_04_10_181858_fix_stocks_quantity_type_for_postgres.php`:
  - pgsql: `ALTER TABLE stocks ALTER COLUMN quantity TYPE BIGINT USING quantity::BIGINT` + keep `DEFAULT 0` (Postgres BIGINT is already signed; the change is dropping any check/unsigned semantics).
  - sqlite/mysql: `$table->bigInteger('quantity')->default(0)->change();` (drops `unsigned`).
  - Values preserved; `down()` restores `unsignedBigInteger`.
- No other schema change in M4. (Provisional-cost columns come from M8.)

## 6. Task specs

### T4.1 — Signed `stocks.quantity` · **M**
- **Behavior:** additive, reversible migration flipping the column to signed BIGINT across pgsql/sqlite/mysql, mirroring the existing postgres-fix migration's driver switch.
- **Files:** *new* migration under `database/migrations/`.
- **Edge cases:** the `['storage_id','quantity']` index (`2026_07_09_100002`) and the FK constraints must survive the type change; Postgres `USING` cast for existing data; ensure no app code relies on the column being unsigned.
- **Acceptance criteria:** a negative value can be persisted to `stocks.quantity`; all existing values unchanged post-migrate; `down()` restores unsigned and the migration is reversible on all three drivers.
- **Test plan:** migration test inserting a negative quantity succeeds after `up()`; existing rows unchanged; assert index still present.

### T4.2 — Strategy-gated deduction · **M**
- **Behavior:** `deductStock` resolves the tenant strategy and branches inside the locked transaction as in §4: overselling ON decrements without throwing (balance may go negative); otherwise `assertCanDeduct` throws `InsufficientStockException` at zero. A movement is recorded in every case (negative balances included).
- **Files:** `app/Models/Storage.php:144-168`.
- **Edge cases:** decision must be inside the same `lockForUpdate` transaction (check-then-act race); a missing `stocks` row under overselling should be created at 0 then driven negative (parity with `addStock`'s insert path); ensure `quantity_before/after` on the recorded movement reflect the true signed values.
- **Acceptance criteria:** `free_form`+ON drives the balance negative and records a movement with negative `quantity_after`; `purchase_driven` and `free_form`+OFF both throw at zero and leave the balance untouched; all under a row lock.
- **Test plan:** feature tests for the three modes deducting past available; concurrency test (two deductions racing) asserts no double-spend under `purchase_driven` and consistent negative accounting under overselling.

### T4.3 — POS + preflight consult strategy (advisory) · **M**
- **Behavior:** `ProcessPosCheckoutAction` precheck and `PosPreflightAction` respect `allowsOverselling()` — when ON they neither throw on shortage nor prompt replenishment; `deductStock` (T4.2) stays the authority. When OFF, current behavior is preserved.
- **Files:** `app/Actions/Pos/ProcessPosCheckoutAction.php:67-81` (and `replenish()` gate at `:170`), `app/Actions/Pos/PosPreflightAction.php:45-62`.
- **Edge cases:** cross-warehouse replenishment should be skipped (not just tolerated) under overselling to avoid unnecessary transfers; preflight response shape must stay backward-compatible with the POS UI; a strategy flip mid-session is read per-checkout, not cached stale.
- **Acceptance criteria:** `free_form`+ON POS checkout succeeds into a negative balance without a replenishment prompt; `purchase_driven` POS behavior is unchanged (precheck + replenishment intact).
- **Test plan:** POS checkout feature tests per strategy; preflight test asserting advisory `available`/`unavailable` entries flip with `allow_overselling`.

### T4.4 — Adjustment gating · **M**
- **Behavior:** manual upward adjustments consult `allowsManualStockIncrease()` — blocked (or permitted only with an explicit, clearly-flagged reason) under `purchase_driven`; unrestricted under `free_form`. Downward adjustments (loss/damage/correction) remain allowed in both.
- **Files:** `app/Actions/Stock/RecordAdjustmentAction.php`, `app/Http/Controllers/Inventory/StockAdjustmentController.php`, `resources/js/Components/Storages/AdjustmentModal.vue` (localized, RTL, dark: hide/disable the upward path or require a reason under strict mode; surface a clear message).
- **Edge cases:** an adjustment that both crosses upward and is under strict mode must fail validation server-side (not just UI), since `type` is currently unconstrained server-side; ensure the `Adjustment` audit row is still written for permitted adjustments.
- **Acceptance criteria:** upward adjustment is rejected/flagged under `purchase_driven` and accepted under `free_form`; downward adjustments unaffected; server-side enforced (not UI-only).
- **Test plan:** feature tests: upward adjustment blocked in strict mode, allowed in free-form; downward allowed in both; UI conditionally renders the guard.

## 7. Edge cases (cross-task)

- **Strategy switch with existing stock:** flipping overselling OFF while a balance is already negative must **not** force reconciliation — the negative carries over; only *new* deductions are blocked (see M6). `deductStock` naturally yields this because it only checks *new* requests.
- **Reads still from cache:** all balance reads remain on `stocks.quantity` in M4; negatives will now appear in read surfaces that don't filter them — most are fine, but `InventoryValuationQuery` filters `quantity > 0` so it silently drops negatives (addressed in M7/M10, noted here as expected interim behavior).
- **Provisional cost:** oversold sale lines have no cost basis → zero/stale COGS; this is why M8 ships together. Without M8, margins on oversold lines would be visibly wrong.

## 8. Test plan (summary)

- Signed-column migration test (T4.1).
- Three-mode deduction feature tests + concurrency test (T4.2).
- POS checkout + preflight per-strategy tests (T4.3).
- Adjustment gating tests, server-side enforced (T4.4).
- Regression: full existing stock/POS suite green under default `purchase_driven`.

## 9. Rollout & backwards compatibility

Additive and reversible. With every tenant defaulting to `purchase_driven` (M3), deploying M4 changes nothing observable until a tenant opts into `free_form`. The signed-column migration preserves all values and is reversible. Ship M4 and M8 in the same release so free-form tenants never see inflated margins. Feature-flag not required — the strategy setting *is* the flag.

## 10. Open questions

- Under `purchase_driven`, should an upward adjustment be hard-blocked or allowed with a mandatory reason + flag? (Brief allows either; lean: allow with mandatory reason, clearly flagged, so shrinkage corrections stay possible.)
- Should overselling ON fully skip the cross-warehouse replenishment prompt, or still offer it as an option? (Lean: skip by default; revisit if merchants want it.)
- Do we clamp any read surface to zero for display, or always show the true negative? (Lean: show true negative everywhere except where a report explicitly defines otherwise — decided in M7/M10.)
