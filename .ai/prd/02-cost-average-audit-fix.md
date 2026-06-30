# PRD 02 — Cost average audit + fix

**Batch:** C — Auth + Cost · **Branch:** `feat/auth-cost` · **Item:** 2

## Problem

`app/Models/Product.php:50-69` `recalculateAverageCost()` has correctness issues:

1. **Cumulative lifetime average, not moving average.** It averages over *all* historical delivered
   purchases and never reduces the pool as stock is sold/consumed. After price changes the stored
   `average_cost` drifts from the true cost of stock on hand.
2. **Unit-basis mismatch.** The average is weighted by `transactions.base_quantity`
   (`Product.php:59`), but COGS uses `transactions.quantity` — in `ProfitAndLossQuery.php:53,102`
   and the per-line stamping in `StoreInvoiceAction.php:54-69` and
   `ProcessPosCheckoutAction.php:110-126`. When `quantity != base_quantity` (unit conversions), the
   cost basis is inconsistent.
3. **Integer rounding drift.** `average_cost` is cast to `(int)` on each recalc
   (`Product.php:63,175`), compounding rounding error for low-cost / high-volume items.

## Goal

`average_cost` reflects a correct, unit-consistent moving weighted average with preserved precision,
and every consumer (valuation, dashboard value, COGS) uses a consistent basis.

## Requirements

> **First**, append a short findings note to the bottom of this PRD file documenting the exact
> behavior you confirmed and the fix you chose (so the decision is recorded). **Then** implement.

1. **Moving weighted average.** Recompute `average_cost` as a moving average that updates on each
   delivered purchase against the quantity currently on hand, rather than a lifetime cumulative
   average over all purchases. Preserve the existing fallback to `cost` when no stock/purchases.
2. **Consistent unit basis.** Make the weighting basis used by `recalculateAverageCost()` consistent
   with the basis used for COGS stamping and the P&L COGS query. Decide on one basis (base units vs.
   transaction units) and apply it everywhere; adjust the COGS sites if needed so cost-per-unit
   matches the average's basis.
3. **Preserve precision.** Stop integer-truncating on each recalc. Keep enough precision in the
   stored/derived value to avoid compounding rounding. (If the column must stay integer minor-units,
   ensure rounding happens once at the boundary, not on every intermediate recalc.)
4. **Update consumers only as needed for correctness:** `app/Queries/Reports/InventoryValuationQuery.php`,
   `app/Queries/DashboardStatsQuery.php` (`totalInventoryValue`, `grossProfit`),
   `app/Models/Stock.php` accessors (`getAverageCostAttribute`, `getTotalCostAttribute`).

   > **Conflict guard:** Item 3 (Batch D) fixes the inventory report on the *frontend* only and will
   > not touch `InventoryValuationQuery.php`. You own any backend changes to that query.

## Implementation notes / files

- `app/Models/Product.php` — `recalculateAverageCost()`, cast/precision.
- `app/Models/Transaction.php:161-182` — `add()` (`unit_cost` source, recalc trigger).
- `app/Actions/Purchase/ReceiveGoodsAction.php`, `app/Actions/Stock/ReverseTransactionAction.php` —
  recalc trigger points.
- COGS sites: `app/Actions/StoreInvoiceAction.php`, `app/Actions/Pos/ProcessPosCheckoutAction.php`,
  `app/Queries/Reports/ProfitAndLossQuery.php`.

## Testing (mandatory)

**Pest** — extend `tests/Feature/AverageCostCalculationTest.php` with cases the current tests miss:
- Unit conversion: a product with a unit whose `conversion_factor != 1`, purchased and sold, asserting
  the average and COGS agree on basis.
- Non-integer average (e.g. purchases that produce a fractional average) asserting precision is kept,
  not truncated mid-calc.
- Sell-then-buy sequence proving moving-average behavior (average reflects remaining stock, not the
  full purchase history).
- Keep the existing passing cases green.

**Cypress** — mostly backend; if a UI value changes (e.g. inventory valuation total), add/extend an
assertion on that surface. Otherwise note in the findings that no Cypress change was needed.

## Acceptance criteria

- [ ] Findings note appended to this file.
- [ ] Moving-average + consistent unit basis + preserved precision implemented.
- [ ] All COGS/valuation consumers consistent.
- [ ] New + existing Pest tests green; `vendor/bin/pint --dirty` clean.

---

## Findings & decision (implementation note)

Confirmed against the code:

1. **Lifetime cumulative average — real.** `recalculateAverageCost()` computed
   `SUM(base_quantity * unit_cost) / SUM(base_quantity)` over *all* delivered supplier transactions
   and never reduced the pool when stock left on sales, so after a price change the stored
   `average_cost` no longer reflected the cost of the units actually on hand.
2. **Unit-basis mismatch — real.** `average_cost` is a cost *per base unit* (weighted by
   `base_quantity`) and is what gets stamped onto sale lines as `unit_cost`. Inventory valuation
   (`InventoryValuationQuery`, `DashboardStatsQuery::totalInventoryValue`) and the `Stock` accessors
   already multiply by base-unit quantities, so they were already consistent — but
   `ProfitAndLossQuery` COGS multiplied the per-base-unit `unit_cost` by `transactions.quantity`
   (display units). When `quantity != base_quantity` (unit conversion) COGS was wrong.
   `Transaction::getTotalCostAttribute()` already used `base_quantity`, confirming base units as the
   intended canonical basis. The per-line stamping in `StoreInvoiceAction` / `ProcessPosCheckoutAction`
   stamps the per-base-unit `average_cost` and is correct as-is — only the COGS *multiplication* basis
   was wrong.
3. **Integer truncation — partly theoretical.** The previous recalc already recomputed from raw
   transactions and rounded once, so it did not compound *across* recalcs; the genuine loss is the
   sub-unit precision discarded by the `unsignedInteger average_cost` column / `integer` cast.

**Decision / fix:**

- **Basis: base units everywhere.** Fixed `ProfitAndLossQuery` COGS (data + summary) to weight by
  `transactions.base_quantity`, matching the per-base-unit `average_cost`/`unit_cost` and the
  already-base-unit valuation consumers (which therefore needed no change).
- **Moving average.** `recalculateAverageCost()` now replays all delivered movements in chronological
  order — purchases raise the pool weighted by base quantity; sales draw down on-hand without changing
  the average — and derives the moving weighted average of the stock on hand. Replaying from raw data
  is idempotent and independent of *when* the recalc is triggered. The fallback to `cost` is preserved
  when no purchase has been delivered.
- **Precision.** The replay runs entirely in floating point and rounds exactly once at the storage
  boundary. Because each recalc recomputes from raw transactions (and never re-reads the truncated
  stored value) there is no cross-recalc compounding. The `average_cost` column stays integer
  minor-units per the PRD's explicit allowance.
- **No Cypress change required.** No surfaced numeric value changes for the existing seeded fixtures
  (which use `quantity == base_quantity`); the inventory-valuation total still uses
  `stocks.quantity * average_cost`.
