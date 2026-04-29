# Reports Module + Queued Export Center

## Summary

Build the Reports module and export infrastructure as one transformation. Reports add 9 filterable pages, while every export in the system, including existing customer/supplier/product/expense exports, becomes an async queued export with a tenant-scoped log, Reverb status updates, and a download link when ready.

Confirmed defaults:
- Owners/managers see all export logs for the current tenant only.
- Regular users see only their own export logs.
- Export files and log rows are both retained for 90 days, then pruned.
- Progress UI is state-based: queued, processing, completed, failed.

References used: [Laravel 12 Broadcasting/Reverb](https://laravel.com/docs/12.x/broadcasting), [Laravel 12 Reverb](https://laravel.com/docs/12.x/reverb), [Laravel Excel Queued Exports](https://docs.laravel-excel.com/3.1/exports/queued.html).

---

## Key Changes

### Reverb + Echo Support
- Install/configure Laravel Reverb and `laravel-echo`/`pusher-js`.
- Update `resources/js/bootstrap.js` to initialize Echo with Reverb.
- Add private broadcast channels for user export updates and tenant export updates.
- Add database queue support because exports and broadcasts must not run on the request cycle.

### Export Log System
- Create `ExportLog` with tenant, user, export key, format, filters, status, disk, path, filename, failure message, timestamps, and `expires_at`.
- Create `ExportStatus` enum: `queued`, `processing`, `completed`, `failed`.
- Add `ExportLogPolicy`: users view/download own exports; owner/manager view tenant exports.
- Add daily scheduled pruning that deletes files and rows older than 90 days.

### Replace Direct Downloads with Queued Exports
- Add `POST /exports` to enqueue an export request.
- Add `GET /exports` for export history.
- Add `GET /exports/{exportLog}/download` for completed downloads.
- Existing export controllers stop returning `Excel::download()` directly and instead enqueue through the shared export action.
- New report exports use the same pipeline.

### Queued Export Generation
- Create `RequestExportAction` to authorize, create `ExportLog`, dispatch `GenerateExportJob`, and flash the queued export id.
- Create `GenerateExportJob` on an `exports` queue with explicit timeout, tries, backoff, and `failed()` handling.
- The job binds the tenant context before running report/export queries because queued workers do not have request tenant context.
- Excel files are stored through Maatwebsite Excel; PDF files are rendered with DomPDF from Blade templates.
- Every status change broadcasts an `ExportStatusUpdated` event.

---

## Reports

### 9 Report Pages

1. **Sales** — Revenue by period, filters: date range, preset
2. **Purchases** — Purchase cost by period
3. **POS Sessions** — Session summaries: opening/closing float, cash sales, variance. Filters: date range, operator
4. **Profit & Loss** — Revenue - COGS - Expenses = Net Profit
5. **Inventory Valuation** — Current stock × average cost per product/storage
6. **Expenses by Category** — Expenses grouped by category with budget comparison
7. **Customer Balances** — Outstanding customer receivables
8. **Supplier Balances** — Outstanding supplier payables
9. **Treasury Summary** — Account movements and balances

### Every Report
- Requires `reports.view` permission.
- Supports `preset`, `from_date`, and `to_date`.
- Defaults to `this_month`.
- Uses a shared date resolver and tenant-scoped query class.
- Shows summary cards, a detail table, and queued export buttons for Excel/PDF.

### Report UI
- `Reports/Index.vue` hub page.
- One Vue page per report.
- Shared `ReportDateFilter.vue`.
- Reports sidebar link visible only when `can('reports.view')`.

---

## Export UI

### Exports/Index.vue — Export History Page
- Regular users see their own exports.
- Owners/managers see all current-tenant exports.
- Completed rows show download actions.
- Failed rows show the failure message.

### ExportProgressToast.vue — Real-time Updates
- Mounted in `AppLayout.vue`.
- Shows a toast when an export is queued.
- Updates via Reverb when processing/completed/failed.
- Shows a download link when completed.
- Uses flat Tailwind styling, dark mode, RTL-safe layout, and existing flash/toast conventions.

---

## Test Plan

### Reports Tests
- Unauthorized users receive 403.
- Authorized users can view every report page.
- Date presets and custom date ranges produce expected data.
- Each report returns the expected Inertia component and data shape.

### Export Pipeline Tests
- `POST /exports` creates an `ExportLog`, dispatches `GenerateExportJob`, and returns a queued flash.
- Existing customer/supplier/product/expense exports create export logs instead of direct downloads.
- The job marks exports processing, completed, or failed.
- Completed exports create files on disk and can be downloaded by authorized users.
- Users cannot view/download another user's export unless they are owner/manager in the same tenant.
- Owners/managers cannot see exports from another tenant.
- The prune command deletes expired files and log rows after 90 days.
- Broadcast events are fired with the expected payload.

### Verification Commands
```
php artisan route:list --name=reports
php artisan route:list --name=exports
php artisan test --compact tests/Feature/Reports
php artisan test --compact tests/Feature/Exports
npm run build
vendor/bin/pint --dirty --format agent
```
