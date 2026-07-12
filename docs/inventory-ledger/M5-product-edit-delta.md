# PRD — M5: Product-edit-as-adjustment-delta

**Status:** Draft · **Milestone:** M5 · **Depends on:** M2 (balance helper), M3/M4 (strategy layer, for gating upward deltas) · **PR grouping:** one PR

## 1. Problem

The product edit screen cannot change on-hand quantity today — quantity lives entirely in the `stocks` ledger and is only reachable through purchase/adjustment/transfer/POS flows. Merchants (especially free-form ones) expect to correct a product's quantity right where they edit its name and price. The target design is explicit: editing quantity on the product screen must **not overwrite** anything — it must compute `delta = new_quantity − current_balance` and append an **adjustment movement** for that delta. The edit UI is sugar over the ledger. The delta mechanic already exists (`Storage::setStockTo`), so this milestone is about exposing it safely on the product form, not building new stock plumbing.

## 2. Goals / Non-goals

**Goals**
- Add an optional on-hand quantity field to the product create/edit form, prefilled from the current balance.
- On save, translate any quantity change into a delta and record it through the existing ledger write path (an `adjustment` movement), never a direct `stocks.quantity` overwrite.
- Respect the per-tenant strategy: an upward delta under `purchase_driven` is a manual stock increase and must be gated/flagged exactly like a manual adjustment.

**Non-goals**
- Replacing or removing the dedicated Adjustment modal (`AdjustmentModal.vue`) — this is a convenience path, not a replacement.
- Multi-line/bulk quantity editing.
- Any change to costing (product-edit deltas do not touch cost, consistent with adjustments today).
- Product variants (out of scope everywhere).

## 3. Current state (from audit)

- **No quantity field on the product form.** `resources/js/Components/Products/ProductForm.vue` `useForm` fields are `name, cost, price, expire_date, currency, alert_quantity, categories, units` — no quantity input anywhere. There is no `Products/Create.vue`/`Edit.vue`; the modal component is the form.
- **Controller never touches stock.** `ProductsController::store` (`app/Http/Controllers/Catalog/ProductsController.php:54-71`) and `::update` (`:82-93`) call `Product::create/update(...except units,categories)`; no quantity handling. `::quickUpdate` (`:73-80`) likewise.
- **Request has no quantity rule.** `app/Http/Requests/ProductRequest.php:24-39` validates `name, cost, price, currency, expire_date, alert_quantity, units, categories` only. (`alert_quantity` is the low-stock threshold, not on-hand.)
- **The delta mechanic already exists.** `Storage::setStockTo(product, quantity, reason, movable, actor)` (`app/Models/Storage.php:173-196`) `insertOrIgnore`s the stock row, locks it, computes `delta = quantity − quantity_before`, updates the cache, and records a `StockMovement` for the delta — all in a transaction. `RecordAdjustmentAction` (`app/Actions/Stock/RecordAdjustmentAction.php:13-39`) wraps `setStockTo` with an `Adjustment` audit row (`reason:'adjustment'`, `movable:Adjustment`).
- **On-hand is per-storage.** A product's balance is `SUM(stocks.quantity)` across storages (`Product::quantityOnHand()`, `app/Models/Product.php:263`); `setStockTo` operates on one `Storage`.

## 4. Design & behavior

Reuse the existing adjustment path — do not reinvent delta logic. The product form gains an optional quantity control:

- **Single storage (common small-merchant case):** one quantity input, prefilled with the product's on-hand in that storage.
- **Multiple storages:** a storage selector alongside the quantity input (edit one storage's on-hand at a time); default to the tenant's primary/sale-point storage. Bulk multi-storage editing is out of scope.

On submit, the controller compares the submitted quantity against the current balance **for the chosen storage**. If unchanged, it does nothing (no zero-delta movement). If changed, it routes through `RecordAdjustmentAction` (preferred — see decision below) with the computed target quantity, which records both an `Adjustment` audit row and an `adjustment` `StockMovement`. The product's other attributes (name/cost/price/…) update as they do today; quantity is handled separately so a validation failure on one doesn't half-apply the other (wrap in a transaction).

**Decision — Adjustment row vs. bare movement.** Route through `RecordAdjustmentAction` (create an `Adjustment` row), not a bare `setStockTo`. Rationale: it gives the change a first-class, reportable audit entity with `type`/`notes`/`created_by`, keeps product-edit deltas indistinguishable from modal adjustments in reports, and reuses gating added in M4. Tradeoff: a required `type` — default it to `manual` (or a dedicated `product_edit` type) and pass an auto-note like `__('Adjusted via product edit')`.

**Strategy interaction (M3/M4).** The upward-delta case is a manual stock increase. Under `purchase_driven` it must be blocked or flagged with a reason exactly like a manual adjustment (same `InventoryStrategy::allowsManualStockIncrease()` gate used in M4.T4.4). Downward deltas (corrections/shrinkage) are always allowed. Under `free_form`, both directions are allowed freely. The quantity field should be hidden or read-only in the UI when the strategy forbids the edit, mirroring the adjustment modal's behavior.

## 5. Data model / schema changes

None. Reuses `stocks`, `stock_movements`, and `adjustments` as-is. No migration.

## 6. Task specs

### T5.1 — Quantity field on product form · **M**
- **Behavior:** add an optional on-hand quantity input to `ProductForm.vue`, prefilled from the current balance; when the tenant has >1 storage, add a storage selector bound to the quantity. Field is hidden/read-only when the active strategy forbids the edit (upward under `purchase_driven`). Fully localized, RTL, dark-mode per `.ai/Design rules`.
- **Files:** `resources/js/Components/Products/ProductForm.vue` (and the inline "Add New Product" form in `resources/js/Pages/Products/Index.vue:272-276` if quantity should be settable at create).
- **Edge cases:** product with no `stocks` row yet (prefill 0); multiple storages (which one is shown/defaulted); tenant with a single storage (hide selector); strategy hides the field; non-integer/negative input.
- **Acceptance criteria:** quantity field appears prefilled with current on-hand; changing it submits the new value with the chosen storage; hidden/disabled when strategy disallows; passes RTL + dark rendering.
- **Test plan:** component/inertia render assertion that the field is present and prefilled; that it is absent when strategy forbids (covered jointly with backend via a feature test rendering the edit page).

### T5.2 — Delta write path · **M**
- **Behavior:** extend `ProductRequest` with an optional `quantity` (integer, `min:0`) and optional `storage_id` (exists, tenant-scoped, required when `quantity` present). In `ProductsController::store`/`::update`, after saving product attributes, if `quantity` is present and differs from the current balance for `storage_id`, call `RecordAdjustmentAction` to set the target quantity (records `Adjustment` + `adjustment` movement). Wrap product update + adjustment in a single `DB::transaction`. Never write `stocks.quantity` directly.
- **Files:** `app/Http/Controllers/Catalog/ProductsController.php:54-93`, `app/Http/Requests/ProductRequest.php:24-39`; reuse `app/Actions/Stock/RecordAdjustmentAction.php`; consult the M3 `InventoryStrategy` for gating.
- **Edge cases:** zero delta → no movement; upward delta under `purchase_driven` → blocked/flagged via strategy (surface a validation/authorization error, don't silently drop); `storage_id` omitted but `quantity` present → validation error; concurrent edits (rely on `setStockTo`'s row lock); create flow where no stock row exists yet.
- **Acceptance criteria:** editing quantity produces exactly one `adjustment` `StockMovement` whose signed `quantity` equals `new − current`, plus an `Adjustment` row; no direct `stocks` write occurs; `SUM(movements) == stocks.quantity` holds after; `purchase_driven` upward edit is rejected/flagged; unchanged quantity produces no movement.
- **Test plan:** feature test — edit product quantity up/down under `free_form` asserts movement sign/magnitude and `Adjustment` row; edit up under `purchase_driven` asserts rejection/flag; unchanged quantity asserts zero new movements; reconciliation invariant asserted post-edit.

## 7. Edge cases (cross-task)

- Multi-storage products: the form edits one storage's on-hand; the displayed "current" and the delta must both be scoped to the selected storage, not the product's total.
- Strategy toggled between render and submit: the backend gate (T5.2) is authoritative; the UI hiding (T5.1) is advisory.
- Newly created product with an initial quantity: treated as an adjustment delta from 0 (or an `opening_balance` — but for consistency with the edit path, use `adjustment`).

## 8. Test plan (summary)

- Feature: up/down delta under `free_form` → correct movement + Adjustment row (T5.2).
- Feature: upward delta under `purchase_driven` → rejected/flagged (T5.2, depends on M4.T4.4 gate).
- Feature: unchanged quantity → no movement (T5.2).
- Invariant: `SUM(stock_movements.quantity) == stocks.quantity` per `(product,storage)` after edit.
- Render: quantity field present/prefilled and correctly hidden under strict strategy (T5.1).

## 9. Rollout & backwards compatibility

Additive and behavior-preserving for existing flows — the product form simply gains an optional field; if a client submits no `quantity`, behavior is identical to today. No migration, no destructive change. Ships after M2 (balance helper) and alongside/after M4 (so the strategy gate exists); if M4 is not yet merged, gate defaults to `purchase_driven` blocking upward edits, matching current strict behavior. One PR.

## 10. Open questions

- Dedicated `MovementType`/adjustment `type` value `product_edit` vs. reusing `manual`? (Lean: reuse `manual` with an auto-note to avoid enum churn; revisit if reports need to distinguish source.)
- For a brand-new product, should an initial quantity be recorded as `adjustment` or `opening_balance`? (Lean: `adjustment` for consistency with the edit path; `opening_balance` is reserved for the M9 migration.)
- Should the multi-storage case eventually support editing several storages at once, or stay single-storage-per-save? (Lean: single-storage now; defer bulk.)
