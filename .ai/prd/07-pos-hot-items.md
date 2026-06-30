# PRD 07 — Hot items section on POS

**Batch:** B — POS · **Branch:** `feat/pos` · **Item:** 7

## Problem

The POS screen has no quick-access section for popular/most-sold products. Cashiers must search for
frequently sold items every time.

## Goal

A "Hot items" strip at the top of the POS product area showing the most-sold products for the
current sale point, where tapping an item adds it to the cart exactly like any product card.

## Requirements

1. **Backend data.** In `app/Http/Controllers/Sales/PosSessionController.php` `show()`, pass a new
   `hotProducts` prop for the `Pos/Session` render. Compute it by mirroring the existing
   `app/Queries/DashboardStatsQuery.php` `topSellingProducts()` aggregation, scoped to the current
   sale point:
   ```php
   Transaction::delivered(now()->subDays(30))
       ->forCustomer()
       ->forStorage($storage->id)
       ->select('product_id', DB::raw('SUM(quantity) as total_quantity'))
       ->groupBy('product_id')
       ->orderByDesc('total_quantity')
       ->limit(/* ~8 */)
       ->get();
   ```
   Map each to the same shape as `initialProducts` rows (`id, name, price, sale_point_qty, units`,
   etc.) so the existing card component can render them. Consider extracting the aggregation into a
   small query/method rather than duplicating inline (SOLID — reuse `topSellingProducts` pattern;
   optionally generalize it to accept a storage id).
2. **Frontend.** Render a horizontal "Hot items" strip at the top of
   `resources/js/Components/Pos/PosProductGrid.vue` (above the search input, ~line 82), or as a
   section in `resources/js/Pages/Pos/Session.vue` before the grid (~line 201). Reuse the existing
   product-card markup and the `add-to-cart` emit so a tap adds to the cart identically. Follow
   `.ai/Design rules` (flat, horizontally scrollable strip, dark mode, RTL). Hide the strip
   gracefully when there are no hot items.

## Testing (mandatory)

**Pest** — extend `tests/Feature/PosSessionTest.php`: seed sales for the sale point and assert
`PosSessionController@show` passes `hotProducts` ordered by total quantity sold, scoped to that
storage (a product sold only at another storage must not appear).

**Cypress** — new POS spec (none exists yet under `tests/cypress/integration/`): open a POS session
with seeded sales → assert the Hot items strip renders the top product → tap it → assert it's added
to the cart.

## Acceptance criteria

- [ ] `hotProducts` computed per sale point, ordered by sales.
- [ ] Hot items strip renders and tapping adds to cart; empty state hidden.
- [ ] Pest + Cypress green; `vendor/bin/pint --dirty` clean.
