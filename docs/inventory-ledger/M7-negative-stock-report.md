# PRD — M7: Negative-stock reconciliation report

**Status:** Draft · **Milestone:** M7 · **Depends on:** M2 (ledger balance helper) · **PR grouping:** one PR

## 1. Problem

Once `free_form` + `allow_overselling` is live (M4), balances can go negative, and a late purchase naturally lifts them back toward zero — no special balance-matching logic is needed. But a negative balance is a *signal a human must triage*: it is either a missed purchase entry, shrinkage/theft, or a counting error, and each resolves differently (enter the late purchase, post a write-off adjustment, or post a correction adjustment). Today there is **no negative-stock report at all** — the only inventory report, `InventoryValuationQuery`, explicitly filters `stocks.quantity > 0` (`app/Queries/Reports/InventoryValuationQuery.php:39`), so negatives are invisible everywhere. This milestone gives operators a dedicated surface listing every product/storage currently below zero, how long it has been negative, and the movements that drove it there, so they can decide the resolution per case.

## 2. Goals / Non-goals

**Goals**
- A tenant-scoped report of every `(product, storage)` whose current balance is `< 0`.
- For each row: current negative balance, **how long it has been negative** (since the movement that crossed zero downward), and a **drill-down of the driving movements**.
- Export (Excel) parity with the other reports; localized/RTL/dark UI.

**Non-goals**
- Auto-reconciliation or auto-matching of late purchases to oversold units (the balance self-heals; resolution is a human decision).
- Changing valuation math or the `quantity > 0` filter in `InventoryValuationQuery` (that is M10.T10.2).
- Costing/margin restatement of oversold lines (that is M8, provisional costing + back-fill).
- Blocking or preventing negatives (that is M4's enforcement).

## 3. Current state (from audit)

- **No negative-stock report exists.** The low-stock dashboard widget (`app/Queries/DashboardStatsQuery.php:206`) uses `SUM(quantity) <= alert_quantity`, which would incidentally include negatives but never surfaces "negative" as a distinct condition; it is a 5-row dashboard widget, not a report.
- `InventoryValuationQuery` (`app/Queries/Reports/InventoryValuationQuery.php:34-47`) is the template to mirror: raw join `stocks → products → storages`, tenant-scoped via `products.tenant_id`, reads `stocks.quantity`, per-storage rows, summary sum. Its `:39` `where('stocks.quantity', '>', 0)` filter is exactly what this report must **invert** (`< 0`).
- Report wiring pattern to copy: `app/Reports/InventoryValuationReport.php:24-29` (renders Inertia page `Reports/InventoryValuation`), `app/Exports/Reports/InventoryValuationExport.php:22` (reuses the query; columns Product/Storage/Quantity/Avg Cost/Total Value), Vue `resources/js/Pages/Reports/InventoryValuation.vue`, plus the reports index `resources/js/Pages/Reports/Index.vue`.
- **Ledger is the source for age + driving movements:** `stock_movements` carries signed `quantity`, `quantity_before`, `quantity_after`, `created_at`, and (after M1) `movement_type` — everything needed to find the movement that crossed zero and to list contributors. `Storage::recordMovement` (`app/Models/Storage.php:198`) is where these rows originate.
- Balance source: prefer M2's ledger balance helper (`app/Queries/StockBalanceQuery.php`) so the report and the ledger cannot disagree; `stocks.quantity` remains the O(1) cache and can seed the candidate set.

## 4. Design & behavior

The report answers three questions per negative `(product, storage)`:

1. **What is negative and by how much** — current balance `< 0` (candidate set from `stocks.quantity`, confirmed against the M2 ledger balance).
2. **How long has it been negative** — walk that pair's `stock_movements` ordered by `created_at, id` and find the **most recent** movement whose `quantity_after < 0` while the immediately preceding `quantity_before >= 0` (the downward zero-crossing that started the *current* negative streak). Its `created_at` gives the "negative since" date; age = `now − created_at`. If the balance has been continuously negative from the first movement, use the first movement's date.
3. **What drove it there** — a drill-down listing the movements since that crossing (type via M1's `movement_type`, signed quantity, before/after, actor, `movable` source, timestamp), so the operator can see whether sales outran purchases (→ missed purchase), an adjustment/write-off is warranted (→ shrinkage), or a correction is needed (→ counting error).

Main view: one row per negative `(product, storage)` with product name, storage name, current balance, "negative since" date + humanized age. Expandable/linked drill-down of driving movements. A summary line (count of negative lines, total negative units). Export mirrors the main rows.

Zero special resolution logic: the report is read-only insight; resolution happens through the existing purchase/adjustment flows, and the balance self-corrects via their positive movements.

## 5. Data model / schema changes

**None.** Read-only over existing `stocks` and `stock_movements` (post-M1 `movement_type`). No migration. Depends on M2's balance helper existing.

## 6. Task specs

### T7.1 — Negative-stock report (query + report + export + page + route + nav) · **L**
- **Behavior:** produce tenant-scoped rows for every `(product, storage)` with balance `< 0`, each with current negative quantity, "negative since" timestamp + humanized age (from the current-streak zero-crossing in `stock_movements`), and a drill-down of driving movements since that crossing. Summary aggregates (count of negative lines, total negative units). Export to Excel with the main-row columns.
- **Files:**
  - *new* `app/Queries/Reports/NegativeStockQuery.php` — mirror `InventoryValuationQuery` (raw join `stocks→products→storages`, tenant-scoped via `products.tenant_id`) but filter `stocks.quantity < 0`; compute the zero-crossing date + driving movements from `stock_movements`; reconcile balance against M2's helper.
  - *new* `app/Reports/NegativeStockReport.php` — mirror `app/Reports/InventoryValuationReport.php`, render Inertia page `Reports/NegativeStock`.
  - *new* `app/Exports/Reports/NegativeStockExport.php` — mirror `app/Exports/Reports/InventoryValuationExport.php`, reuse the query.
  - *new* `resources/js/Pages/Reports/NegativeStock.vue` — mirror `Reports/InventoryValuation.vue`; table + expandable drill-down; localized (`__()`), RTL, dark mode per `.ai/Design rules`.
  - `routes/tenant.php` — add the report route (next to the other report routes) + export route.
  - `resources/js/Pages/Reports/Index.vue` (and any nav/menu) — add a "Negative stock" entry.
- **Edge cases:**
  - A pair currently negative that was negative from its very first movement → use the first movement's date as "negative since".
  - A pair that dipped negative, recovered, and dipped again → age counts only the **current** streak (most recent downward zero-crossing), not the earliest.
  - A negative `stocks.quantity` with **no** matching `stock_movements` (legacy/seeded drift before M9) → still list it, with "negative since" unknown/`created_at` of the stock row and a flag; do not crash on empty movement history.
  - Soft-deleted products (`WithTrashScope`) and deleted storages must be excluded/handled like `InventoryValuationQuery` does.
  - Multi-storage: a product negative in one storage but positive net across storages must still appear (report is per `(product, storage)`, matching valuation granularity).
  - Pagination/performance for many negative lines; index usage on `stock_movements(storage_id, product_id)` (exists).
- **Acceptance criteria:**
  - Only `(product, storage)` pairs with balance `< 0` appear; positives/zeros excluded; `InventoryValuationQuery` remains unchanged.
  - "Negative since"/age reflect the current-streak zero-crossing, verified against a crafted movement sequence (dip → recover → dip).
  - Drill-down lists the driving movements since the crossing with type, signed qty, before/after, actor, source, timestamp.
  - Report balance equals M2's ledger balance for each row (no divergence from the cache).
  - Export produces the same rows as the page; tenant isolation holds (no cross-tenant rows).
  - UI strings localized; RTL + dark verified.
- **Test plan:**
  - Feature test: seed sales exceeding stock under `free_form`+overselling → assert the pair appears with correct negative qty and "negative since".
  - Streak test: movements dip → recover (late purchase) → dip again → assert age uses the second crossing.
  - Isolation test: negative stock in tenant A not visible to tenant B.
  - Empty-history test: negative `stocks` row with no movements listed without error, flagged.
  - Export test: exported rows match the query.
  - Regression: `InventoryValuationQuery`/valuation report unaffected.

## 7. Edge cases (cross-task)

- Depends on M2's balance helper; if run before M9's opening-balance backfill, ledger and `stocks.quantity` may diverge for legacy tenants — the report should reconcile to the cache and flag rows where ledger ≠ cache rather than hide them.
- After M8 (provisional costing), oversold lines may carry provisional/zero cost — this report is quantity-only and must not depend on cost, but may optionally link to affected provisional sale lines for context.
- M10.T10.2 revisits the `quantity > 0` valuation filter; keep this report's `< 0` logic independent so the two decisions don't couple.

## 8. Test plan (summary)

- Query correctness: negative-only filter, per-`(product,storage)` granularity, tenant scoping.
- Zero-crossing/age logic incl. multi-streak and empty-history cases.
- Drill-down contents (types/quantities/source).
- Ledger-vs-cache reconciliation per row.
- Export parity + tenant isolation.
- Localization/RTL/dark smoke of the Vue page.

## 9. Rollout & backwards compatibility

Purely additive: a new read-only report, new routes, new page, no schema change, no write paths touched. Safe to ship anytime after M2. Most valuable once M4 (overselling) is enabled, since that is when negatives begin to occur; before then it simply renders an empty state. One PR.

## 10. Open questions

- Should "negative since" be computed on the fly from `stock_movements` each request, or denormalized/cached for large tenants? (Lean: on-the-fly first, using the `(storage_id, product_id)` index; optimize only if needed.)
- Should the report offer inline quick-actions (e.g. "post correction adjustment", "record late purchase") or stay read-only with links to existing flows? (Lean: read-only + links for v1.)
- Do we include products negative in one storage but net-positive overall as a separate "net negative" view, or only per-storage rows? (Lean: per-storage only, matching valuation granularity.)
