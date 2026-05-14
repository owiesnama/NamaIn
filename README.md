[![Laravel Forge Site Deployment Status](https://img.shields.io/endpoint?url=https%3A%2F%2Fforge.laravel.com%2Fsite-badges%2Fb4300804-07d5-4a0a-a053-632523cfabdd%3Fcommit%3D1&style=for-the-badge)](https://forge.laravel.com/servers/741800/sites/2188548)

# NamaIn

NamaIn is a multi-tenant inventory and invoicing platform that runs your whole back office — products, customers, suppliers, stock across multiple storages, invoices, payments, quotes, POS sessions, and account statements — with full Arabic/RTL support and dark mode throughout. Role-based access, CSV/Excel import-export, clean browser-based printing, and a calm, consistent design come built in, so your team spends time running the business instead of fighting the software.

## Features

- **Multi-tenancy** — every tenant lives on its own subdomain (`{tenant}.namain.test`) with strict data isolation enforced through a global tenant scope on every model.
- **Catalog** — products, categories, multiple units per product with conversion factors, expiry tracking, low-stock alerts.
- **Inventory** — multiple storages per tenant, stock additions from purchase invoices, deductions on sales, transfers between storages, manual adjustments with full audit trail.
- **Sales & Purchases** — invoices, transactions, partial delivery tracking, returns (sale and purchase), price quotes that convert to invoices.
- **POS** — checkout sessions, fast item lookup, thermal receipt printing (80mm).
- **Payments & Treasury** — payments against invoices, customer advances, cheques (payee tracking + status), treasury accounts and transfers, expense management with approvals.
- **Contacts** — customers and suppliers with running balances, category tagging, account statements over any date range.
- **Roles & Permissions** — tenant-scoped roles (owner, admin, etc.) with fine-grained permissions backed by a default-roles service.
- **Import / Export** — CSV and Excel pipelines with QuickBooks-compatible templates, queued background processing, broadcast progress updates, and validation failure reporting.
- **Real-time** — broadcasting via Reverb for import progress and operation feeds.
- **Printing** — invoices, POS receipts, and account statements all render as Vue pages that trigger the browser's native print dialog; no headless Chrome, no PDF generation.
- **Internationalization** — Arabic and English UI with full RTL support across every page.

## Architecture

### High-level layout

```
app/
├── Actions/          Single-responsibility business operations (e.g. SettleCustomerAdvanceAction)
├── Enums/            PaymentStatus, PaymentMethod, InvoiceStatus, ...
├── Events/           Broadcast events (ImportStatusUpdated, OperationFeed, ...)
├── Exports/          Excel/CSV export classes
├── Http/Controllers/ Grouped by domain: Catalog, Inventory, Invoicing, Sales, Contacts, ...
├── Http/Requests/    FormRequest classes for validation
├── Imports/          Maatwebsite/Excel import classes + Concerns
├── Jobs/             Queued work (ProcessImportJob, GenerateExportJob)
├── Models/           Eloquent models, all extend BaseModel
├── Policies/         Authorization policies
├── Queries/          Query objects (StatementQuery, PartyAccountQuery, ...)
├── Scopes/           Global scopes (TenantScope)
├── Services/         Cross-cutting services (CsvSampleGenerator, OperationFeed, ...)
├── Traits/           Reusable model/controller traits (BelongsToTenant, HandlesPartyAccount)
└── ValueObjects/     Domain values

resources/
├── js/Pages/         Inertia Vue pages mirroring controller domains (Invoices, Quotes, Pos, ...)
└── lang/             Translation files (en, ar)

routes/
├── web.php           Root domain routes (landing, tenant selection)
└── tenant.php        Tenant subdomain routes (the application surface)
```

### Multi-tenancy

- Tenants are subdomains. Routes in `routes/tenant.php` are bound to `{tenant}.namain.test` and gated by a tenant-resolution middleware.
- The `BelongsToTenant` trait on `BaseModel` adds:
  - A `tenant_id` foreign column (added by migration `add_tenant_id_to_all_tables`).
  - A `TenantScope` that constrains every query to the current tenant — and explicitly returns no rows if no tenant context is bound, to fail safe.
  - A `creating` hook that auto-fills `tenant_id` from the authenticated user's `current_tenant_id` or the bound `currentTenant` instance.
- Users can belong to multiple tenants via the `tenant_user` pivot, and switch via `tenant.switch`.

### Frontend

- Inertia.js connects the Laravel backend to Vue pages — no separate API layer, no manual routing.
- Tailwind utility classes only, no component library. A strict design system lives in `CLAUDE.md` covering colors, spacing, dark mode pairings, and RTL handling.
- Inline SVG icons from Heroicons; no icon font.
- All printable documents (invoices, receipts, statements, quotes) are Vue pages that call `window.print()` on mount — no PDF backend.

### Key conventions

- Models are unguarded by default (`Model::unguard()` in `BaseModel::booted`).
- Form requests live in `app/Http/Requests`. Controllers stay thin.
- Validation rules that need domain knowledge live in the request, not the controller (e.g. `StockRequest` rejects already-delivered invoices).
- Actions encapsulate non-trivial workflows; controllers delegate to them.

## Installation

### Requirements

- PHP 8.4 (the project's `composer.json` lock targets 8.4; 8.3 will hit unrelated symfony version conflicts)
- Composer 2
- Node.js 18+
- A relational database (MySQL/PostgreSQL/SQLite)
- Redis (for queues, broadcasting, cache)
- A local host that resolves wildcard subdomains (e.g. Laravel Herd, dnsmasq, or `/etc/hosts` entries for each tenant)

### Setup

```bash
# 1. Clone and install dependencies
git clone <repo-url> namain
cd namain
composer install
npm install

# 2. Environment
cp .env.example .env
php artisan key:generate

# Edit .env — at minimum set:
#   APP_DOMAIN=namain.test     (or whatever your local TLD is)
#   APP_URL=http://namain.test
#   DB_* credentials
#   QUEUE_CONNECTION=redis     (recommended)
#   BROADCAST_DRIVER=reverb

# 3. Database
php artisan migrate --seed

# 4. Frontend
npm run build           # production assets, or
npm run dev             # Vite dev server with HMR

# 5. Serve
#   With Laravel Herd: the project is already served at https://namain.test
#   Otherwise: php artisan serve (note: tenant subdomains require Herd/Valet/nginx)
```

### Running queue workers and broadcasting

```bash
# Queue worker — required for imports, exports, broadcasting
php artisan horizon         # (Horizon is configured for this project)

# Reverb broadcaster
php artisan reverb:start
```

## Testing

The test suite uses Pest. SQLite in-memory is configured in `phpunit.xml` for fast, isolated runs.

```bash
# Run everything
php artisan test --compact

# Filter by name or path
php artisan test --compact --filter=QuotesTest
php artisan test --compact tests/Feature/InvoicesControllerTest.php

# Architecture tests (Pest arch())
php artisan test --compact --testsuite=Architecture
```

The project also ships Cypress E2E tests under `tests/cypress/`:

```bash
npx cypress open
```

## Code style

```bash
# PHP (Laravel Pint)
vendor/bin/pint                       # format everything
vendor/bin/pint --dirty --format agent  # format only changed files

# JavaScript / Vue
npm run lint          # ESLint with --fix
npm run format        # Prettier
```

## Domain documentation

`CLAUDE.md` contains the working agreement for AI assistants on the project, but it doubles as living documentation for:

- The UI design system (colors, spacing, typography, dark mode, RTL).
- Laravel and Eloquent conventions used here.
- Skill guidance for common tasks (authentication, testing, broadcasting, Horizon, Tailwind).

Read it before making non-trivial changes.

## Contributing

1. Branch from `master`.
2. Write or update tests for any behavioral change — see `tests/Feature/` for examples.
3. Run `vendor/bin/pint --dirty --format agent` and `npm run lint` before opening a PR.
4. Make sure `php artisan test --compact` passes locally.
5. Open a pull request; CI runs the full suite on every push.
