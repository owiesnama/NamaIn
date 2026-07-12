# PRD — M1: Ledger hardening

**Status:** Draft · **Milestone:** M1 · **Depends on:** — · **PR grouping:** one PR

## 1. Problem

`stock_movements` is already a signed, polymorphic, complete record of every runtime stock change, but it is not yet a trustworthy *source of truth*: its `reason` is a free-form string (not a typed vocabulary), and nothing prevents a row from being updated or deleted. Before we can derive balances from it or migrate reads onto it (M2, M9, M10), the ledger must be typed and immutable. This milestone hardens the existing recording without changing any behavior — reads and writes stay exactly as they are today (dual-write with `stocks.quantity`).

## 2. Goals / Non-goals

**Goals**
- Introduce a typed `MovementType` enum and persist it on every movement.
- Backfill `movement_type` for all existing rows from `reason`.
- Enforce append-only at the model layer (no updates, no deletes, no soft-deletes).

**Non-goals**
- Reading balances from the ledger (M2).
- Removing the `reason` column (kept for continuity; may retire post-cutover).
- Any strategy/enforcement/overselling behavior (M3/M4).
- Product variants (out of scope entirely — no variants in schema).

## 3. Current state (from audit)

- Single write choke point: `Storage::addStock` (`app/Models/Storage.php:108`), `deductStock` (`:144`), `setStockTo` (`:173`), all calling `recordMovement()` (`:198`) which `->create()`s a `stock_movements` row with `tenant_id, storage_id, product_id, user_id, movable_type/id, reason, quantity (signed), quantity_before, quantity_after`.
- Reasons in use today: `purchase_receipt, invoice_addition, invoice_deduction, sale_delivery, adjustment, transfer_out, transfer_in, sales_return, purchase_return` (from `ReceiveGoodsAction`, `Transaction::add/deduct`, `DeliverTransactionAction`, `RecordAdjustmentAction`, `TransferStockAction`, `ReverseTransactionAction`).
- Model `app/Models/StockMovement.php` has no immutability guard, no `SoftDeletes`. Grep confirms nothing updates/deletes movements today — append-only is convention, not enforced.
- Migration `2026_04_22_131838_create_stock_movements_table.php` defines the table.

## 4. Design & behavior

Add `App\Enums\MovementType` (string-backed) covering the nine existing reasons **plus** `opening_balance` (used by M9). `recordMovement()` accepts/derives a `MovementType` and writes it to a new `movement_type` column alongside the existing `reason` (dual-write; `reason` unchanged). A `MovementType::fromReason(string): self` mapping keeps callers that still pass a reason string working and drives the backfill. The `StockMovement` model gains a `booted()` guard that throws on `updating` and `deleting` events, making immutability a hard runtime invariant.

No behavior visible to users changes; this is a structural/foundation milestone.

## 5. Data model / schema changes

- **New migration** on `stock_movements`: add `movement_type` (string, nullable initially for backfill, then indexed). Optionally a second migration to set `NOT NULL` after backfill.
- Backfill `movement_type = MovementType::fromReason(reason)` for all existing rows.
- No column removed. Reversible (`down()` drops `movement_type`).

## 6. Task specs

### T1.1 — `MovementType` enum · **S**
- **Behavior:** string-backed enum, cases `OpeningBalance='opening_balance', PurchaseReceipt='purchase_receipt', InvoiceAddition='invoice_addition', InvoiceDeduction='invoice_deduction', SaleDelivery='sale_delivery', Adjustment='adjustment', TransferIn='transfer_in', TransferOut='transfer_out', SalesReturn='sales_return', PurchaseReturn='purchase_return'`. Static `fromReason(string $reason): self` returns the matching case (throws/logs on unknown). Optional `isIncrease(): bool` helper.
- **Files:** *new* `app/Enums/MovementType.php`.
- **Edge cases:** an unrecognized legacy `reason` — `fromReason` must fail loudly in tests but be handled (fallback to a generic case or exception) so backfill surfaces surprises.
- **Acceptance criteria:** every reason string currently emitted maps to exactly one case; `fromReason` round-trips for all known reasons.
- **Test plan:** unit test asserting each known reason → expected case; unknown reason behavior asserted.

### T1.2 — `movement_type` column + backfill · **S**
- **Behavior:** migration adds nullable `movement_type` string + index; a backfill step updates every existing row via `fromReason(reason)`.
- **Files:** *new* migration under `database/migrations/`.
- **Edge cases:** large tables — backfill in chunks; run inside the same migration or a follow-up command; must be idempotent/reversible.
- **Acceptance criteria:** after migrate, no `stock_movements` row has null `movement_type`; column indexed; `down()` cleanly drops it.
- **Test plan:** migration test seeding rows with legacy reasons, asserting populated `movement_type` post-migrate.

### T1.3 — Typed movement recording · **M**
- **Behavior:** `recordMovement()` sets `movement_type` (derived from the passed reason, or accept an explicit `MovementType` param defaulted from reason). Callers may keep passing reason strings; ideally pass a `MovementType` where natural. Keep `reason` written (dual-write).
- **Files:** `app/Models/Storage.php:198` and callers: `app/Actions/Stock/*`, `app/Actions/Purchase/ReceiveGoodsAction.php`, `app/Models/Transaction.php:151,173`.
- **Edge cases:** ensure `setStockTo` (delta path) and both increase/decrease paths all set type; transfers set `transfer_in`/`transfer_out` distinctly.
- **Acceptance criteria:** each of the nine write paths persists the correct `movement_type`.
- **Test plan:** one feature test per path (purchase receive, invoice add/deduct, sale delivery, adjustment, transfer in/out, sales/purchase return) asserting the persisted `movement_type`.

### T1.4 — Append-only enforcement · **S**
- **Behavior:** `StockMovement::booted()` registers `updating` and `deleting` listeners that throw a domain exception; ensure model does **not** use `SoftDeletes`.
- **Files:** `app/Models/StockMovement.php`.
- **Edge cases:** the M9 opening-balance backfill must *create* rows, never update — verify it isn't blocked. FK `cascadeOnDelete` still deletes rows if a parent tenant/storage/product is hard-deleted (DB-level, outside Eloquent) — document this as accepted.
- **Acceptance criteria:** calling `->update()`/`->save()` on a dirty movement or `->delete()` throws; creating a new movement still works.
- **Test plan:** unit test asserting update and delete throw; create succeeds.

## 7. Edge cases (cross-task)

- Unknown legacy reason strings from any historical data → backfill must surface, not silently mislabel.
- Cascade deletes via FK bypass the Eloquent guard — acceptable and documented (tenant/product/storage teardown).

## 8. Test plan (summary)

- Enum mapping unit test (T1.1).
- Migration + backfill test (T1.2).
- Per-path `movement_type` feature tests (T1.3).
- Immutability unit test (T1.4).
- Regression: existing stock feature tests still green (no behavior change).

## 9. Rollout & backwards compatibility

Fully additive and reversible. No behavior change; `reason` retained. Ship as one PR. Safe to deploy ahead of all other milestones; it is their prerequisite.

## 10. Open questions

- Do we retain `reason` long-term (human note) or retire it after cutover once `movement_type` + `movable` cover all needs? (Lean: retain as free-text note.)
- Should `fromReason` on an unknown string throw or map to a generic `Adjustment`? (Lean: throw in tests/backfill, log+generic in runtime.)
