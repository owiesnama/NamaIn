# PRD 03 — Inventory report shows product & warehouse names

**Batch:** D — Inventory reports · **Branch:** `feat/inventory-reports` · **Item:** 3

## Problem

On the Inventory Valuation report, the Product, Warehouse (storage), and Avg Cost columns render
blank/zero. Root cause is a key-name mismatch between the backend payload and the Vue template — the
data is present, the template reads the wrong keys.

- `app/Queries/Reports/InventoryValuationQuery.php` `buildData()` emits per row:
  `product_id`, `product_name`, `storage_name`, `quantity`, `average_cost`, `total_value`.
- `resources/js/Pages/Reports/InventoryValuation.vue:125-131` reads: `row.product`, `row.storage`,
  `row.avg_cost` (plus `row.quantity`, `row.total_value` which already match).

| Template reads | Query emits | Result |
|---|---|---|
| `row.product` | `product_name` | blank |
| `row.storage` | `storage_name` | blank |
| `row.avg_cost` | `average_cost` | $0.00 |

## Requirements

- Fix the **Vue bindings only** in `resources/js/Pages/Reports/InventoryValuation.vue:125-131`:
  - `row.product` → `row.product_name`
  - `row.storage` → `row.storage_name`
  - `row.avg_cost` → `row.average_cost`
  - Update the row `:key` (line 125, currently `row.product + '-' + row.storage`) to use the correct
    keys.
- **Do NOT edit `InventoryValuationQuery.php`.** (Conflict guard: Batch C may touch that query for
  the cost-average work.)

## Testing (mandatory)

**Cypress** — extend `tests/cypress/integration/reports-with-data.cy.js:149-160` ("Inventory
Valuation with data"): assert the seeded product name `'Cypress Product'` (seed at line ~21) and a
storage name actually render in the table, and that the Avg Cost cell is non-zero.

**Pest** — in `tests/Feature/Reports/ReportsIndexTest.php`, assert the Inertia `data` payload rows
contain `product_name`, `storage_name`, and `average_cost` keys (locks the contract the Vue relies
on).

## Acceptance criteria

- [ ] Product, Warehouse, and Avg Cost columns render real values.
- [ ] `InventoryValuationQuery.php` untouched.
- [ ] Cypress + Pest green; `vendor/bin/pint --dirty` clean.
