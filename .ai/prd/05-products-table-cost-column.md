# PRD 05 — Cost column back on products table

**Batch:** A — Products UI · **Branch:** `feat/products-ui` · **Item:** 5

## Problem

The products listing table (`resources/js/Pages/Products/Index.vue`, `layout === 'table'`) shows an
"Avg Cost" column but no raw "Cost" column. Merchants want the raw cost back.

The raw `cost` value is already on the product payload — `app/Http/Controllers/Catalog/ProductsController.php`
`index()` serializes full `Product` models and `cost` is not hidden — so **no backend data change is
needed**.

## Requirements

In `resources/js/Pages/Products/Index.vue` (table layout):

1. Add a **"Cost"** column header `<th>` near the existing Avg Cost header (~line 424), following the
   table-header convention in `.ai/Design rules` (`text-start`, `text-[10px] font-bold uppercase
   tracking-[0.1em] text-gray-400 dark:text-gray-500`).
2. Add the matching `<td>` (~near line 488, beside the Avg Cost cell) rendering
   `formatCurrency(product.cost, product.currency)` with the same text styling as Avg Cost. (The
   `product.cost` binding is already used elsewhere in this file, e.g. inline edit ~line 681.)
3. Add a `cost` sort option to the sortable options array (~line 67), e.g.
   `{ label: __("Cost"), value: "cost" }` — confirm `ProductFilter` / the index query already
   supports sorting by `cost` (a `min_cost`/`max_cost` filter already exists); wire sorting if the
   sort handler needs it.
4. Keep dark-mode variants on every added class.

## Testing (mandatory)

**Cypress** — extend `tests/cypress/integration/product-cards.cy.js` (or add a products-table spec):
in table layout, assert the "Cost" column header is present and a product's cost value renders.

**Pest** — assert the products index Inertia payload includes `cost` per product (locks the
contract). Keep `tests/Feature/ProductsControllerRefactoringTest.php` green.

## Acceptance criteria

- [ ] Cost column visible in table layout with correct formatting + dark mode.
- [ ] Sort-by-cost works (or option removed if backend genuinely can't sort it — prefer wiring it).
- [ ] Cypress + Pest green; `vendor/bin/pint --dirty` clean.
