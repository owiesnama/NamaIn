# PRD 06 — Add product modal UX polish

**Batch:** A — Products UI · **Branch:** `feat/products-ui` · **Item:** 6

## Problem

The add/edit product modal (`resources/js/Components/Products/ProductForm.vue`, a dual create/edit
component) works but is visually unrefined, and the currency is display-only (a static badge) even
though `currency` is already in the form state (line 29) and accepted by validation.

**Scope decision: UX/layout polish only — NO new DB columns.**

## Requirements

In `resources/js/Components/Products/ProductForm.vue`:

1. **Layout & grouping polish** per `.ai/Design rules`: consistent spacing (`space-y-*`, `gap-*`,
   no sibling margins), clear visual grouping of related fields (pricing, stock/alerts,
   categories, units), `rounded-lg`/`rounded-xl` surfaces, proper modal header/footer structure,
   and the standard primary/secondary button patterns.
2. **Clearer validation feedback**: show field-level errors with the documented error style
   (`text-sm text-red-600 dark:text-red-400`) consistently across all fields.
3. **Editable currency control**: replace the static currency badge (~lines 187-189 / 211-213) with
   an editable control bound to the existing `form.currency` (a `CustomSelect`/input is fine). The
   `ProductRequest` already allows `currency` (`nullable|string|max:3`) — no backend change.
4. **Preserve behavior/contract**: same submit targets (`products.index` create / `products.update`
   edit), same field names/ids (`#name`, `#cost`, `#price`, units, categories), same units/categories
   sync. Full dark-mode + RTL support on all changed markup.

Do **not** add SKU/barcode/description/image/supplier or any new column — those are out of scope.

## Testing (mandatory)

**Cypress** — extend `tests/cypress/integration/product-cards.cy.js` modal flow: open the modal,
fill `#name`, `#cost`, `#price`, set the currency control, save → product created; then edit flow
updates a field. Assert validation message appears when required fields are empty.

**Pest** — keep `tests/Feature/ProductsControllerRefactoringTest.php` and
`tests/Feature/ProductCategoryManagementTest.php` green; add an assertion that `currency` round-trips
on create/update if not already covered.

## Acceptance criteria

- [ ] Modal visually polished and consistent with the design system (light + dark + RTL).
- [ ] Currency is editable and persists.
- [ ] No new columns/migrations.
- [ ] Cypress + Pest green; `vendor/bin/pint --dirty` clean.
