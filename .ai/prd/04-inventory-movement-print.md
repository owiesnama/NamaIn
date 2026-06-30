# PRD 04 — Print for inventory movement (stock transfer)

**Batch:** D — Inventory reports · **Branch:** `feat/inventory-reports` · **Item:** 4

## Problem

Inventory movements (stock transfers) cannot be printed. Merchants want a printable document for a
transfer, matching how invoices/quotes already print.

## Goal

A "Print" action on a stock transfer opens a clean, standalone printable page that auto-triggers the
browser print dialog — reusing the app's existing print pattern (browser `window.print()`, no PDF
library).

## Requirements

Mirror the existing print pattern exactly (reference: `app/Http/Controllers/Invoicing/InvoicePrintController.php`
+ `resources/js/Pages/Invoices/Print.vue`):

1. **Controller** — new `app/Http/Controllers/Inventory/StockTransferPrintController.php` with
   `show(StockTransfer $transfer)` that eager-loads the needed relations (from/to storage, creator,
   transferred items + product) and returns `inertia('StockTransfers/Print', [...])`. Enforce the
   same auth/permission used by the existing stock-transfer routes.
2. **Route** — add `Route::get('/stock-transfers/{transfer}/print', [StockTransferPrintController::class, 'show'])
   ->name('stock-transfers.print')` in `routes/tenant.php` alongside the other `stock-transfers.*`
   routes (~lines 208-211).
3. **Print page** — new `resources/js/Pages/StockTransfers/Print.vue` modeled on
   `resources/js/Pages/Invoices/Print.vue`: bare layout (no `AppLayout`), `onMounted(() => window.print())`,
   `@media print { @page { size: A4; margin: 12mm } }`. Render: transfer reference/date, from→to
   storage, created-by, notes, and the items table (product name, quantity, unit). Use `.ai/Design
   rules` typography/spacing.
4. **Print button** — add a print button/link on `resources/js/Pages/StockTransfers/Show.vue` that
   navigates to the new print route (open in the print page).

## Testing (mandatory)

**Pest** — new feature test for the print route: authorized user gets 200 and the
`StockTransfers/Print` Inertia component with the expected props (transfer + items); unauthorized /
unauthenticated is blocked; a transfer from another tenant is not accessible.

**Cypress** — open a seeded transfer's Show page → click Print → assert the print page renders the
items table and transfer header. (Stub/await `window.print` so the dialog doesn't block the run.)

## Acceptance criteria

- [ ] Print route + controller + page follow the invoice print pattern.
- [ ] Print button on the transfer Show page.
- [ ] Pest + Cypress green; `vendor/bin/pint --dirty` clean.
