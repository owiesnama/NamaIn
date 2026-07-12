# PRD — M10: Cutover (reads → ledger, cache retained)

**Status:** Draft · **Milestone:** M10 · **Depends on:** M9 (opening balances) · **PR grouping:** one PR (or incremental commits — one read surface per commit)

## 1. Problem

Every read of stock today goes to the mutable `stocks.quantity` column, treating it as the authoritative number. The target design retires that *mentality*: movement records become authoritative and current stock is `SUM(signed_quantity)`, with a cached/denormalized balance for read performance. NamaIn is already most of the way there — `stocks.quantity` is an incrementally-updated, row-locked balance cache (`Storage::addStock/deductStock/setStockTo`), and after M9 the ledger reconciles to it exactly. This final milestone flips the framing: reads become *ledger-consistent* (sourced from the reconciled balance helper), `stocks.quantity` is demoted to a pure cache, and nothing is destructively dropped. This is the only milestone permitted non-additive changes — and even here we keep the column.

## 2. Goals / Non-goals

**Goals**
- Route the ~15 stock-read surfaces through the reconciled balance helper (M2.T2.1) instead of reading `stocks.quantity` directly.
- Preserve O(1) POS-speed reads by keeping `stocks.quantity` as the maintained cache — never sum the ledger on a hot path.
- Correctly surface negative balances in reads/reports (stop silently dropping them).
- Formally retire the "column is source of truth" mentality via docs + guardrails, with the ledger authoritative.

**Non-goals**
- Dropping `stocks.quantity` (it survives as the derived cache).
- Any strategy/enforcement changes (M3/M4) or costing changes (M8).
- Rebuilding the write path — writes already maintain both ledger and cache.
- Product variants (out of scope).

## 3. Current state (from audit)

Read surfaces that hit `stocks.quantity` / `average_cost` directly and become cutover change points:
- `app/Queries/Reports/InventoryValuationQuery.php:34-47` — `stocks.quantity * COALESCE(average_cost,cost,0)`, **filters `quantity > 0`** (`:39`) so negatives are silently excluded.
- `app/Queries/DashboardStatsQuery.php` — `totalInventoryValue()` (`:87`), `lowStockProducts()` (`:206`, `SUM(quantity) <= alert_quantity`); `topSellingProducts()` (`:171`) reads `transactions`, not on-hand (leave as-is).
- `app/Http/Controllers/Inventory/StoragesController.php:30-41` — per-storage value/quantity in PHP over the `stock` pivot.
- `app/Http/Controllers/Catalog/ProductsController.php:26` + `Product::scopeWithStockAggregates` (`app/Models/Product.php:186`) + `Product::quantityOnHand()` (`:263`).
- `app/Http/Controllers/Sales/PosSessionController.php` — availability joins on `stocks.quantity`.
- `app/Http/Controllers/Inventory/StockTransfersController.php` — reads on-hand for transfer source.
- `app/Models/Stock.php:28-41` — `average_cost`/`total_cost` accessors appended to every serialized pivot.
- Exports + Vue: `InventoryValuationExport`; `resources/js/Pages/Reports/InventoryValuation.vue`, `Dashboard.vue`, `Storages/{Index,Show}.vue`, `Products/{Index,Show}.vue`.

By M9, `SUM(stock_movements.quantity) == stocks.quantity` per `(tenant, product, storage)` holds by construction, so switching reads is a source-swap, not a value change (except where negatives were being dropped).

## 4. Design & behavior

Introduce the balance read through the M2.T2.1 helper (`StockBalanceQuery` / `Product`/`Storage` balance methods) as the canonical way to obtain on-hand. Two read tiers:
- **Hot path (POS, availability, product lists):** read the `stocks.quantity` cache — it *is* the denormalized balance, kept correct on every write under a row lock. Keep O(1); never `SUM` the ledger here.
- **Reporting/valuation path:** read via the balance helper so the number is definitionally the reconciled ledger balance; where a raw `stocks` join is retained for performance, it is documented as "cache == ledger (guaranteed by M9 reconciliation)".

Migrate one surface per commit so each is independently verifiable against the reconciled balance. After all surfaces are switched, add a guardrail test asserting the cache-equals-ledger invariant across the suite, and update docs to declare the ledger authoritative and `stocks.quantity` a cache. `stocks.quantity` is **not** dropped.

## 5. Data model / schema changes

- **None destructive.** `stocks.quantity` is retained as the cache column.
- No new tables/columns required (the balance helper and reconciliation land in M2).
- The only "change" is conceptual + code-level (read source) plus a documented invariant; fully reversible per surface by reverting the commit.

## 6. Task specs

### T10.1 — Switch read surfaces to the reconciled balance · **L**
- **Behavior:** replace each direct `stocks.quantity` read with the M2.T2.1 balance helper, keeping `stocks.quantity` as the O(1) cache on hot paths. Incremental — one surface per commit, each verified against the reconciled balance.
- **Files:** `app/Queries/Reports/InventoryValuationQuery.php:34-47`; `app/Queries/DashboardStatsQuery.php:87,206`; `app/Http/Controllers/Inventory/StoragesController.php:30-41`; `app/Http/Controllers/Catalog/ProductsController.php:26` + `app/Models/Product.php:186,263`; `app/Http/Controllers/Sales/PosSessionController.php`; `app/Http/Controllers/Inventory/StockTransfersController.php`; `app/Models/Stock.php:28-41`; `app/Exports/Reports/InventoryValuationExport.php`; Vue: `resources/js/Pages/Reports/InventoryValuation.vue`, `Dashboard.vue`, `Storages/{Index,Show}.vue`, `Products/{Index,Show}.vue`.
- **Edge cases:** POS availability and product-list sorting must stay O(1) — keep the cache read, do not sum the ledger; `topSellingProducts` (`:171`) is transactions-based, not on-hand — leave untouched; `withStockAggregates` subselects (`pending_sales_qty`/`pending_purchases_qty`) are transaction-derived and unaffected.
- **Acceptance criteria:** each migrated report/screen returns values identical to the reconciled ledger balance; POS balance reads remain O(1) (no per-request ledger `SUM`); no surface still treats the column as authoritative in comments/semantics.
- **Test plan:** per-surface feature test asserting reported balance/value equals `SUM(stock_movements.quantity)` for a fixture with mixed movements; a POS test asserting availability read does not trigger a ledger aggregation.

### T10.2 — Include negatives where correct · **M**
- **Behavior:** revisit the `quantity > 0` filter so negative balances are not silently dropped from stock views/valuation; define and document the valuation treatment of negative on-hand (e.g. negative value contribution vs. excluded-but-listed).
- **Files:** `app/Queries/Reports/InventoryValuationQuery.php:39` and any view/report mirroring that filter.
- **Edge cases:** negative × `average_cost` yields negative valuation — confirm this is the intended accounting treatment or explicitly list-but-exclude; ensure the M7 negative-stock report and the valuation report agree on which products are negative.
- **Acceptance criteria:** negative balances appear where expected (not dropped); valuation treatment of negatives is documented and covered by a test; valuation and M7 report reconcile on the negative set.
- **Test plan:** test with a product driven negative asserting it is visible in stock views and handled per the documented valuation rule.

### T10.3 — Retire authoritative-column mentality · **S**
- **Behavior:** docs + guardrails declaring the ledger authoritative and `stocks.quantity` a derived cache; verify every writer still maintains the cache; explicitly **no** destructive drop of `stocks.quantity`.
- **Files:** `docs/inventory-ledger/` (this milestone's closeout notes) and a suite-level invariant test.
- **Edge cases:** any future writer that mutates `stocks` must also emit a movement — the M1 append-only guard + M2 reconciliation command are the safety net; wire reconciliation into CI or a scheduled check.
- **Acceptance criteria:** cache-equals-ledger invariant test is green across the suite; `stocks.quantity` still exists; documentation states the source-of-truth switch.
- **Test plan:** invariant test asserting `SUM(stock_movements.quantity) == stocks.quantity` per `(product,storage)` after representative operation sequences; grep/arch guard that no non-`Storage` writer mutates `stocks.quantity`.

## 7. Edge cases (cross-task)

- Cache drift: if any pre-existing bug left cache ≠ ledger, M9 reconciliation already folded it into an opening balance; the invariant test (T10.3) is the ongoing tripwire.
- Performance regression risk: accidentally routing a hot path (POS/product list) through a ledger `SUM` — guarded by T10.1 acceptance criteria and an explicit O(1) test.
- Negative balances interacting with `average_cost` costing (M8) — valuation of negatives must not double-count with provisional-cost handling.

## 8. Test plan (summary)

- Per-surface parity tests: migrated read == reconciled ledger balance (T10.1).
- POS O(1) read test: no ledger aggregation on the hot path (T10.1).
- Negative-balance visibility + valuation-treatment test (T10.2).
- Suite-wide cache-equals-ledger invariant + no-rogue-writer guard (T10.3).
- Regression: all existing report/dashboard/product/storage tests green with unchanged values (except intended negative handling).

## 9. Rollout & backwards compatibility

Ship incrementally — one read surface per commit, each independently revertible — behind the guarantee that M9 made cache == ledger. Values are unchanged for existing tenants except where negatives were previously dropped (now surfaced intentionally). No destructive schema change: `stocks.quantity` remains as the cache, so rollback is a code revert with zero data migration. This is the last milestone; on completion the ledger is authoritative and the column is documented as a cache.

## 10. Open questions

- Valuation treatment of negative on-hand: contribute negative value to total inventory worth, or list-but-exclude from the valuation sum? (Lean: contribute negative value so valuation ties to the ledger; document clearly.)
- Do we schedule the M2 reconciliation command in CI/production as a standing tripwire, and at what cadence? (Lean: nightly per-tenant check with alerting on drift.)
- Long-term: once reads are ledger-consistent and stable, is there appetite to eventually drop `reason`/other legacy columns — deferred beyond this milestone.
