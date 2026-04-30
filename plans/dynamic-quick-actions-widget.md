# Dynamic Quick Actions Dashboard Widget

## Context

The dashboard currently shows financial KPIs, charts, alerts, and top products/customers. There's no way for employees to quickly jump to their most-used actions. A cashier has to navigate the sidebar every time they want to open POS, and a finance person has to hunt for treasury. This widget gives each employee tailored shortcuts based on their role and personal usage patterns, placed right on the dashboard.

## Architecture

```
TrackQuickAction middleware (passively increments counts on route visits)
       |
       v
UserQuickActionCount (stores per-user action frequency)
       ^
       |
QuickActionRegistry (defines all possible actions + required permissions)
       |
QuickActionsQuery (merges registry + usage counts + user permissions)
       |
DashboardController (adds 'quick_actions' prop)
       |
Dashboard.vue -> QuickActionsWidget.vue
```

## Implementation Steps

### 1. Migration: `user_quick_action_counts` table

Create via `php artisan make:migration create_user_quick_action_counts_table`

Schema:
- `id` bigIncrements
- `tenant_id` foreignId → tenants
- `user_id` foreignId → users
- `action_slug` string (e.g. `pos.open-session`, `sales.create`)
- `count` unsignedInteger default 0
- `last_used_at` timestamp nullable
- `timestamps`
- Unique composite: `[tenant_id, user_id, action_slug]`

### 2. Model: `app/Models/UserQuickActionCount.php`

- Belongs to tenant (scoped like other models)
- `belongsTo(User::class)`
- Static `increment(int $tenantId, int $userId, string $slug): void` using upsert

### 3. Registry: `app/Services/QuickActionRegistry.php`

Pure PHP class. No DB dependency. Defines the catalog of ~15 quick actions:

| Slug | Label Key | Route | Permission | Role Defaults |
|------|-----------|-------|------------|---------------|
| `pos.open-session` | Open POS | `pos.index` | `pos.operate` | cashier #1 |
| `sales.create` | New Sale | `sales.create` | `sales.create` | cashier #2 |
| `purchases.create` | New Purchase | `purchases.create` | `purchases.create` | manager #2 |
| `treasury.index` | Treasury | `treasury.index` | `treasury.view` | manager #3, staff #2 |
| `products.index` | Products | `products.index` | `products.view` | staff #1 |
| `customers.index` | Customers | `customers.index` | `customers.view` | — |
| `suppliers.index` | Suppliers | `suppliers.index` | `suppliers.view` | — |
| `expenses.create` | New Expense | `expenses.create` | `expenses.create` | manager #4 |
| `payments.create` | Record Payment | `payments.create` | `payments.create` | cashier #3 |
| `inventory.index` | Inventory | `storages.index` | `inventory.view` | — |
| `reports.index` | Reports | `reports.index` | `reports.view` | owner #2, manager #1 |
| `cheques.index` | Cheques | `cheques.index` | `payments.manage-cheques` | — |
| `users.index` | Team | `users.index` | `users.view` | owner #3 |
| `settings.index` | Settings | `preferences.edit` | `settings.view` | owner #4 |

Methods:
- `all(): array` — full catalog
- `forPermissions(array $slugs): array` — filtered by what user can access
- `defaultsForRole(string $roleSlug): array` — ordered defaults per role
- `actionSlugForRoute(string $routeName): ?string` — reverse-map route name to action slug

### 4. Query: `app/Queries/QuickActionsQuery.php`

Follows existing `DashboardStatsQuery` pattern with `cacheKey()` and tenant scoping.

Method `forUser(User $user, int $limit = 6): array`:
1. Get user's permissions from current tenant role
2. Filter registry to accessible actions
3. Query `UserQuickActionCount` for top actions by `count DESC`
4. Fill remaining slots with role defaults (not already included)
5. Return array of action definitions (slug, label, route, icon)

Cache: 5 minutes per user+tenant.

### 5. Middleware: `app/Http/Middleware/TrackQuickAction.php`

Lightweight middleware that passively tracks route visits for personalization. Applied to trackable tenant routes (not all routes — only the ones that map to quick actions in the registry).

How it works:
- Runs **after** the response (using `$next($request)` then tracking) to avoid adding latency
- Resolves the current route name and checks if it maps to a registered action slug in `QuickActionRegistry`
- If matched: calls `UserQuickActionCount::increment()` for the authenticated user + current tenant
- The registry gains a new method: `actionSlugForRoute(string $routeName): ?string` to reverse-map route names to action slugs

Registration in `bootstrap/app.php`: add as a named middleware alias (`track-quick-action`), then apply it to relevant route groups in `routes/tenant.php` via `->middleware('track-quick-action')`.

Target routes: POS (`pos.index`), Sales create/index, Purchases create/index, Treasury index, Products index, Customers index, Suppliers index, Expenses create, Payments create, Inventory/Storages index, Cheques index, Team/Users index, Settings/Preferences.

This approach captures real usage patterns — a cashier opening POS 50 times from the sidebar builds up their personalization data naturally.

### 6. DashboardController integration

Add to `DashboardController::index()`:
```php
'quick_actions' => fn () => app(QuickActionsQuery::class)->forUser($request->user()),
```

**File:** `app/Http/Controllers/Core/DashboardController.php`

### 7. Vue component: `resources/js/Components/QuickActionsWidget.vue`

- Receives `actions` prop (array)
- Grid: `grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3`
- Each action: flat card with icon + label, using `<Link>` for navigation
- No tracking POST needed — middleware handles it passively on route visit
- Icon map: object mapping icon slugs to inline SVGs (Heroicons)
- Full dark mode + RTL support per design system

### 8. Dashboard.vue integration

Insert `<QuickActionsWidget>` between the page header and Zone 1 (KPI cards), inside `<div class="space-y-6">` at line ~141.

Add `quick_actions` to `defineProps`.

**File:** `resources/js/Pages/Dashboard.vue`

## Critical Files

| File | Action |
|------|--------|
| `app/Http/Controllers/Core/DashboardController.php` | Add `quick_actions` prop |
| `app/Queries/DashboardStatsQuery.php` | Reference for Query pattern |
| `resources/js/Pages/Dashboard.vue` | Integrate widget component |
| `app/Services/DefaultRolesService.php` | Reference for role slugs |
| `database/seeders/PermissionSeeder.php` | Reference for permission slugs |
| `resources/js/Composables/usePermissions.js` | Reuse in widget |
| `routes/tenant.php` | Apply tracking middleware to key routes |
| `bootstrap/app.php` | Register middleware alias |

## New Files

- `database/migrations/xxxx_create_user_quick_action_counts_table.php`
- `app/Models/UserQuickActionCount.php`
- `app/Services/QuickActionRegistry.php`
- `app/Queries/QuickActionsQuery.php`
- `app/Http/Middleware/TrackQuickAction.php`
- `resources/js/Components/QuickActionsWidget.vue`

## Testing

1. **Unit: `QuickActionRegistryTest`** — filters by permissions, returns role defaults, excludes inaccessible actions
2. **Unit: `UserQuickActionCountTest`** — increment new/existing, updates last_used_at, tenant scoping
3. **Feature: `QuickActionsQueryTest`** — personalization by frequency, role default fallback, permission filtering, max limit
4. **Feature: `TrackQuickActionMiddlewareTest`** — increments on matched route, ignores unmatched routes, requires auth, tenant scoping
5. **Feature: `DashboardQuickActionsTest`** — prop included, role-appropriate actions for cashier vs owner

## Verification

1. Run `php artisan migrate` to create the table
2. Run `php artisan test --compact --filter=QuickAction` to verify all tests pass
3. Run `npm run build` and visit dashboard — verify widget renders with role defaults
4. Click a quick action — verify navigation works and counter increments
5. Reload dashboard — verify the clicked action appears first
6. Test as different roles (cashier, owner) — verify different default actions shown
7. Run `vendor/bin/pint --dirty --format agent` for code style
