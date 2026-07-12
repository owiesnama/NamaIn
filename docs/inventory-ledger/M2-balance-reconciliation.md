# PRD — M2: Balance helper + reconciliation

**Status:** Draft · **Milestone:** M2 · **Depends on:** M1 · **PR grouping:** one PR

## 1. Problem

Once the ledger is typed and immutable (M1), it can serve as the authoritative record of stock — but nothing yet *derives* a balance from it, and nothing verifies that the mutable cache (`stocks.quantity`) still agrees with the sum of movements. We need (a) a first-class way to compute the ledger balance, and (b) a reconciliation tool that detects drift between the cache and the ledger. This is the safety net that lets later milestones (M9 opening balances, M10 read cutover) trust the ledger and lets us flip existing tenants over with confidence. Crucially, the cache stays the hot-path source of reads — the ledger sum is never summed at POS speed.

## 2. Goals / Non-goals

**Goals**
- Provide a tenant-scoped helper computing `SUM(stock_movements.quantity)` per product (and per product×storage).
- Provide a `stock:reconcile` command that reports drift between `stocks.quantity` and the ledger sum.
- Lock in the invariant `SUM(movements) == stocks.quantity` per (product,storage) with a test.

**Non-goals**
- Switching any read surface onto the balance helper (M10).
- Auto-correcting drift or inserting compensating movements (M9 owns opening-balance backfill).
- Changing how writes maintain `stocks.quantity` — it remains the O(1) row-locked cache (`Storage::addStock/deductStock/setStockTo`).
- Any strategy/overselling behavior (M3/M4).

## 3. Current state (from audit)

- The cache is maintained by the single write choke point: `Storage::addStock` (`app/Models/Storage.php:108`), `deductStock` (`:144`), `setStockTo` (`:173`), each locking the `stocks` row and calling `recordMovement()` (`:198`).
- Balance is read today from the cache, never the ledger: `Product::quantityOnHand()` (`app/Models/Product.php:263`), `scopeWithStockAggregates` (`app/Models/Product.php:186`), and ~15 report/UI surfaces (the M10 change set).
- The ledger (`stock_movements`) is currently latent — no reader derives balances from it.
- Known drift sources are non-production only: seeder bypasses `database/seeders/DatabaseSeeder.php:58` (`stock()->attach`) and `database/seeders/DashboardExampleSeeder.php:58` (`syncWithoutDetaching`), plus `database/factories/StockFactory.php`. All runtime paths funnel through the choke point, so real tenants should reconcile cleanly.

## 4. Design & behavior

A dedicated query object computes the ledger balance as `SUM(stock_movements.quantity)`, grouped per product or per product×storage, tenant-scoped (movements carry `tenant_id`; reads go through the tenant-scoped path). On clean data this equals `stocks.quantity`.

A console command `stock:reconcile` iterates every (tenant, product, storage) with either a `stocks` row or movements, computes cache-vs-ledger, and reports every non-zero delta (product, storage, cache qty, ledger qty, drift). Human-readable table by default; `--json` for machine consumption / CI. The command is read-only — it never mutates stock; it only surfaces drift for M9 to absorb into opening balances.

The reconciliation invariant is asserted by a test that runs a representative sequence of operations (purchase receive, sale delivery, adjustment, transfer, return) and checks `SUM(movements) == stocks.quantity` for every (product,storage).

## 5. Data model / schema changes

None. This milestone is pure read/query + a command. No migrations.

## 6. Task specs

### T2.1 — Ledger balance helper · **S**
- **Behavior:** new `app/Queries/StockBalanceQuery.php` (or methods on `Product`/`Storage`) returning `SUM(stock_movements.quantity)` per (product) and per (product,storage), tenant-scoped. Mirrors the semantics of `Product::quantityOnHand()` but sourced from the ledger.
- **Files:** *new* `app/Queries/StockBalanceQuery.php`; optional thin accessor on `app/Models/Product.php` / `app/Models/Storage.php`.
- **Edge cases:** product/storage with zero movements → balance `0` (not null); soft-deleted products still summable; ensure the query doesn't accidentally sum across tenants.
- **Acceptance criteria:** on clean fixture data the helper returns the same value as `stocks.quantity` for every (product,storage); tenant isolation holds.
- **Test plan:** unit/feature test seeding movements via the choke point, asserting helper equals `stocks.quantity`; a second tenant's movements excluded.

### T2.2 — Reconcile command · **M**
- **Behavior:** new `app/Console/Commands/ReconcileStockCommand.php` signature `stock:reconcile {--json} {--tenant=}`. Per (tenant,product,storage) compares `stocks.quantity` (cache) vs `StockBalanceQuery` (ledger); prints only drifting rows with cache, ledger, and delta; summary count. `--json` emits structured output; exit code non-zero when drift found (CI-friendly).
- **Files:** *new* `app/Console/Commands/ReconcileStockCommand.php`.
- **Edge cases:** rows present in `stocks` but with no movements (seeder bypass) → full quantity reported as drift; movements present but no `stocks` row → negative-only ledger reported; must not choke on large tenants (chunk iteration).
- **Acceptance criteria:** clean data → zero drift, exit 0; a seeded (bypassed) tenant → reports the exact gap, exit non-zero; `--json` well-formed.
- **Test plan:** feature test with a clean tenant (no drift) and a bypass-seeded tenant (known drift), asserting reported deltas and exit code; `--json` shape asserted.

### T2.3 — Reconciliation invariant test · **S**
- **Behavior:** test-only guard asserting the core invariant after an arbitrary op sequence.
- **Files:** *new* test under `tests/Feature/Inventory/`.
- **Edge cases:** include a transfer (net-zero across two storages) and a return to exercise sign handling; assert per (product,storage), not just per product.
- **Acceptance criteria:** after purchase→sale→adjustment→transfer→return, `SUM(movements) == stocks.quantity` for every (product,storage).
- **Test plan:** Pest feature test driving the actions and asserting the invariant.

## 7. Edge cases (cross-task)

- Seeder/factory bypass rows (`DatabaseSeeder.php:58`, `DashboardExampleSeeder.php:58`, `StockFactory`) will surface as drift — this is expected and is exactly what M9.T9.2 fixes and M9.T9.1 absorbs.
- `stocks` rows and movements can each exist without the other; the command must handle both directions of drift.
- Tenant scoping: reconcile per tenant; never sum movements across tenants.

## 8. Test plan (summary)

- Balance-helper equals cache on clean data + tenant isolation (T2.1).
- Reconcile command: clean vs drifting tenant, exit codes, `--json` (T2.2).
- Invariant after mixed op sequence (T2.3).
- Regression: existing stock feature tests still green (no behavior change).

## 9. Rollout & backwards compatibility

Pure additive tooling — no schema, no behavior change, no read-path change. `stocks.quantity` remains the authoritative read cache throughout. Safe to deploy immediately after M1. The `stock:reconcile` command is the pre-flight gate reused by M9.T9.3 before the opening-balance backfill.

## 10. Open questions

- Should `stock:reconcile` be scheduled (e.g. nightly) to catch future drift, or run on demand only? (Lean: on-demand now; consider a scheduled health check post-cutover.)
- Do we want a `--fix` mode later that emits compensating movements, or keep correction exclusively in M9's opening-balance path? (Lean: keep correction in M9 to avoid two reconciliation code paths.)
