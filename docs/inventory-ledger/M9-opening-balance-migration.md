# PRD — M9: Data migration — opening balances

**Status:** Draft · **Milestone:** M9 · **Depends on:** M1 (`MovementType::OpeningBalance`, append-only guard), M2 (`stock:reconcile` command, balance helper) · **PR grouping:** one PR — **except T9.2, which is a separate hygiene PR**

## 1. Problem

The ledger (`stock_movements`) is complete for all *runtime* stock changes, but two conditions mean `SUM(movements)` does not necessarily equal the live `stocks.quantity` for existing data: (1) dev/demo **seeders bypass** the write choke point and set `stocks.quantity` directly with no matching movement, and (2) any historical drift between the cached quantity and the movement history. Before reads can be switched to a ledger-derived balance (M10), every `(tenant, product, storage)` row must satisfy the invariant `SUM(stock_movements.quantity) == stocks.quantity`. This milestone establishes that invariant for existing data by inserting a single **opening-balance movement** per row that absorbs whatever gap exists — regardless of *why* it exists.

## 2. Goals / Non-goals

**Goals**
- Guarantee `SUM(movements) == stocks.quantity` for every `(tenant, product, storage)` after migration.
- Do so idempotently, so a re-run is a no-op.
- Surface pre-existing drift for review before it is folded into opening balances.
- Close the non-production integrity holes (seeders/factory) so future seeded data stays reconciled.

**Non-goals**
- Switching any read path to the ledger (M10).
- Changing runtime write behavior (M1/M3/M4).
- "Explaining" historical drift — the opening balance intentionally absorbs it as a single reconciling entry; forensic attribution is out of scope.
- Retiring `stocks.quantity` (it remains the cache; see M10).

## 3. Current state (from audit)

- **Runtime coverage is complete**: every runtime mutation funnels through `Storage::addStock` (`app/Models/Storage.php:108`), `deductStock` (`:144`), `setStockTo` (`:173`) → `recordMovement()` (`:198`), so for tenants created purely through the app, `SUM(movements)` already equals `stocks.quantity`.
- **Bypasses (write `stocks.quantity` with NO movement), non-production only:** `database/seeders/DatabaseSeeder.php:58` (`$product->stock()->attach($storage->id, ['quantity' => rand(...)])`), `database/seeders/DashboardExampleSeeder.php:58` (`syncWithoutDetaching([$storage->id => ['quantity' => 60]])`), and `database/factories/StockFactory.php` (creates pivot rows directly). No production/app path bypasses.
- **Balance shape:** on-hand is per `(product, storage)` — `stocks` pivot rows (`stocks.quantity`, `unsignedBigInteger`, `database/migrations/2023_01_07_162613_create_stocks_table.php`). Movements are per `(storage, product)` too (`stock_movements`, `database/migrations/2026_04_22_131838_create_stock_movements_table.php`).
- **Prerequisites now available:** `MovementType::OpeningBalance = 'opening_balance'` (M1.T1.1) and the append-only guard (M1.T1.4, which the backfill must respect — it only *creates* rows); `stock:reconcile` (M2.T2.2) computes per-row drift.

## 4. Design & behavior

For each `(tenant_id, product_id, storage_id)` present in `stocks`:

```
opening = stocks.quantity − COALESCE(SUM(stock_movements.quantity for that product+storage), 0)
if opening != 0:
    insert ONE stock_movements row:
        movement_type   = OpeningBalance
        reason          = 'opening_balance'
        quantity        = opening               (signed; may be negative if movements exceed cache)
        quantity_before = stocks.quantity − opening   (== prior SUM(movements))
        quantity_after  = stocks.quantity
        movable_*       = null
        user_id         = null   (system)
        tenant_id/storage_id/product_id = the row's
```

After this runs, `SUM(movements) == stocks.quantity` holds **by construction** for every row — the opening entry is defined as exactly the residual. This is robust whether the gap came from a seeder bypass, a factory row, or historical drift; the migration does not need movement history to be complete or correct.

**Idempotency:** the backfill is guarded so a row that already reconciles (`opening == 0`) gets nothing, and a re-run recomputes `opening` against the *now-reconciled* state → `0` → no-op. (Equivalently, skip any `(product, storage)` that already has an `OpeningBalance` movement.)

**Ordering with the reconciliation gate:** T9.3 runs `stock:reconcile` first (dry-run) so an operator reviews the drift report *before* T9.1 folds it into opening balances. T9.1 is the write step; T9.3 is the read-only gate that precedes it.

## 5. Data model / schema changes

- **None.** No columns added or changed. This milestone only *inserts* `OpeningBalance` rows into `stock_movements` (schema from M1). Additive and reversible: `down()` deletes rows where `movement_type = 'opening_balance'` (the only creator of such rows).
- Depends on M1's `movement_type` column and `OpeningBalance` case already existing.

## 6. Task specs

### T9.1 — Opening-balance backfill · **M**
- **Behavior:** a migration (or a dedicated artisan command invoked by a migration) that, per `(tenant, product, storage)`, computes `opening = stocks.quantity − SUM(movements)` and inserts one `OpeningBalance` movement for a non-zero residual, with consistent `quantity_before`/`quantity_after`. Runs across all tenants (bypass `TenantScope` or iterate tenants explicitly, since this is a system migration). Chunked over `stocks` rows for scale.
- **Files:** *new* `database/migrations/…_backfill_opening_balance_movements.php` (and optionally *new* `app/Console/Commands/BackfillOpeningBalancesCommand.php` if the logic is shared/re-runnable).
- **Edge cases:** (a) residual already `0` → insert nothing; (b) re-run → no-op (idempotent via the `opening == 0` recompute or an existing-`OpeningBalance` skip); (c) `stocks` rows across many tenants — must not be silently filtered by the global `TenantScope`; (d) negative residual (movements exceed cache) → a negative `OpeningBalance` entry is valid and must not be blocked by any unsigned assumption (note: the signed-column change is M4; if M9 runs before M4, a negative *opening* is possible only where `stocks.quantity` already permits — in practice residuals here are ≥ 0 for seeded data, but the code must not assume sign); (e) the append-only guard (M1.T1.4) must permit these `create()`s.
- **Acceptance criteria:** after running, for every `(tenant, product, storage)`, `SUM(stock_movements.quantity) == stocks.quantity`; a second run inserts zero additional rows; `down()` removes exactly the opening-balance rows.
- **Test plan:** migration test with (i) a fully app-driven fixture (residual 0 → no opening rows), (ii) a seeded/bypassed fixture (residual > 0 → one opening row, invariant holds), (iii) re-run asserts no new rows; assert invariant `SUM(movements) == stocks.quantity` per row in all cases.

### T9.2 — Fix seeder/factory bypasses · **S** · *(separate PR)*
- **Behavior:** route seeded/factory stock creation through the choke point so seeding leaves the ledger reconciled — either call `Storage::addStock`/`setStockTo`, or emit an `OpeningBalance` movement alongside the pivot write.
- **Files:** `database/seeders/DatabaseSeeder.php:58`, `database/seeders/DashboardExampleSeeder.php:58`, `database/factories/StockFactory.php`.
- **Edge cases:** factory used in isolation (no `Storage`/tenant context) — ensure it still produces a reconciled pair, or document that the factory intentionally creates raw pivot rows for tests that don't assert the invariant; keep seeder performance acceptable (chunk/batch).
- **Acceptance criteria:** after `db:seed`, `SUM(movements) == stocks.quantity` for every seeded `(product, storage)`; no seeder writes `stocks.quantity` without a corresponding movement.
- **Test plan:** seeder test (or `RefreshDatabase` + seed) asserting the invariant post-seed; factory test documenting/asserting its chosen contract.

### T9.3 — Pre-migration reconciliation gate · **S**
- **Behavior:** reuse `stock:reconcile` (M2.T2.2) as a dry-run drift report to be run and reviewed *before* T9.1; document it as the required first step in the migration runbook. Optionally have T9.1 print the same drift summary before writing.
- **Files:** reuses `app/Console/Commands/ReconcileStockCommand.php` (M2); optional runbook note in this PRD / deploy docs.
- **Edge cases:** large drift or many affected rows → report must be reviewable (`--json`/summary counts); a tenant with zero `stocks` rows → empty report, not an error.
- **Acceptance criteria:** a drift report (per-row and summary) is produced and reviewable before backfill; running it changes no data.
- **Test plan:** command test asserting it reports the same residuals T9.1 would fold in, and writes nothing.

## 7. Edge cases (cross-task)

- **Seeded/demo tenants** (movements missing) and **historical drift** are handled identically — both absorbed by the opening entry; the migration is agnostic to the cause.
- **Negative residuals** must be representable as a signed `OpeningBalance.quantity`; coordinate ordering with M4's signed-column change if a negative *opening balance itself* must persist (rare; seeded residuals are typically ≥ 0).
- **Multi-tenant execution**: the system migration must cover all tenants, not just the resolved one — avoid `TenantScope` filtering.
- **Append-only guard interaction**: backfill only creates; never updates existing movements.
- **No orphaned-quantity risk** beyond (1) seeder rows and (2) drift rows — both enumerated and absorbed.

## 8. Test plan (summary)

- T9.1 migration test: invariant holds for app-driven, seeded/bypassed, and negative-residual fixtures; idempotent re-run; reversible `down()`.
- T9.2 seeder/factory test: `SUM(movements) == stocks.quantity` after seeding.
- T9.3 command test: dry-run drift report matches folded residuals; no writes.
- Regression: existing stock feature tests green; `stock:reconcile` reports zero drift post-migration.

## 9. Rollout & backwards compatibility

Additive (insert-only) and reversible (`down()` deletes only `opening_balance` rows). Deploy order: **M1 and M2 first** (types + reconcile), then run T9.3 (review drift), then T9.1 (backfill). T9.2 ships as an independent hygiene PR and can land before or after T9.1 (once landed, freshly seeded environments are reconciled without needing the backfill). No runtime behavior changes for tenants; this only makes existing data satisfy the ledger invariant that M10 relies on.

## 10. Open questions

- Should T9.1 be a **migration** (auto-runs on deploy) or an **explicit command** gated behind the T9.3 review? (Lean: command invoked by a thin migration, so it's re-runnable and testable, with T9.3 as a documented pre-step.)
- Timestamp for opening-balance rows: `now()` at migration time, or backdated to the tenant/stock `created_at`? (Lean: migration time, clearly labeled as an opening entry, to avoid implying false history.)
- Do we run M9 before or after M4's signed-column change when negative residuals are possible? (Lean: M9 after M4 if any negative opening balances are expected; otherwise M9 can precede M4.)
