# PRD — F4: Admin management

**Status:** Draft · **Phase:** F4 · **Depends on:** F1 · **PR grouping:** one PR (may split Plans CRUD vs Tenant assignment)

## 1. Problem

Plans, their feature/limit values, and per-tenant assignments exist only as seeded/DB data with no UI. Super-admins need to **manage plans** and **assign/override** them per tenant from the existing `/__admin` panel — this is the "features are manageable by the admin panel" requirement. Without it, every plan change is a seeder edit or manual SQL.

## 2. Goals / Non-goals

**Goals**
- Plans CRUD in `/__admin`: list, create, edit (toggle boolean features, set limit values from the code registry), activate/deactivate, set default, delete (guarded).
- Tenant detail: assign/change plan (via `AssignPlanToTenant`), set a trial, add/remove per-tenant overrides (grant/revoke/limit with optional expiry), and a **read-only effective-entitlements preview** (`Entitlements::for($tenant)`).
- Every mutation audited via the existing `LogAdminAction`, and calls `Entitlements::flush()` so same-request previews are fresh.

**Non-goals**
- Billing / pricing UI, invoices, checkout (deferred; `price/currency/interval` stay hidden/schema-only).
- Merchant-facing self-serve plan selection (that arrives with billing).
- Changing the feature catalog from the UI — features are code (enum); admins manage *plans*, not the catalog.

## 3. Current state (from audit)

- **Admin panel:** custom Vue/Inertia at `/__admin`, guarded by `EnsureSuperAdmin` (`User.role === 'admin'` on the `admin` guard). Controllers `app/Http/Controllers/Admin/*` (incl. `TenantsController`, `Tenants/Show.vue`), layout `AdminLayout.vue`, audit via `app/Actions/Admin/LogAdminAction` + `AdminAuditLog`.
- **No bound `currentTenant`** in the admin panel — the reason `Entitlements::for($tenant)` (explicit scope) and `LimitUsage` (explicit-tenant counts) were designed for this context in F1; and why `Subscription`/override models are unscoped.
- **Assign action:** `AssignPlanToTenant` (F1) already encapsulates transactional plan swap.

## 4. Design & behavior

**Plans CRUD** — new `Admin/PlansController` + `Admin/Plans/{Index,Edit}.vue`. The edit form renders **all enum cases grouped by `Feature::group()`**: booleans as toggles, limits as number inputs (blank = unlimited). Saving writes `plan_features` (upsert per key; omit → no row → falls to `Feature::default()` at read). Translatable `name`/`description` (ar + en). Guardrails: cannot delete a plan with live subscriptions (FK `restrictOnDelete` + a friendly pre-check); exactly one `is_default` (setting a new default clears the old, transactionally).

**Tenant assignment** — extend `Admin/TenantsController@show` + `Tenants/Show.vue` with a "Subscription & Features" panel:
- Current plan + status + trial/renewal dates; **Change plan** (→ `AssignPlanToTenant`, optional trial).
- **Overrides** list with add/remove: pick a feature, grant/revoke (boolean) or set a limit, optional `expires_at`; writes `feature_tenant` (**upsert** over any expired row on the unique key). Expired overrides shown but visibly marked.
- **Effective entitlements preview** (read-only): every feature's resolved value via `Entitlements::for($tenant)`, showing where each came from (override / plan / default) — invaluable for support.

All writes: wrap in the admin audit (`LogAdminAction`) and call `Entitlements::flush($tenant)` after commit so the preview reflects the change in the same request.

## 5. Data model / schema changes

None new (uses F1 tables). Possibly a `plans.sort`-driven ordering already present.

## 6. Task specs

### T4.1 — Plans CRUD controller + routes · **M**
- **Behavior:** resource routes under `/__admin` (`admin.plans.*`), guarded by `EnsureSuperAdmin`; index/create/store/edit/update/destroy; store/update validate `feature_key`s ∈ enum and values against each `Feature::type()`.
- **Files:** *new* `app/Http/Controllers/Admin/PlansController.php`; `routes/web.php` (`__admin` group); *new* `app/Http/Requests/Admin/PlanRequest.php`.
- **Edge cases:** reject unknown/typed-mismatched feature keys; delete blocked when live subscriptions exist (friendly 422, not a raw FK error); setting default clears prior default transactionally.
- **Acceptance:** CRUD works; invalid feature keys/values rejected; default invariant holds; delete-with-subscriptions blocked.
- **Test plan:** feature tests for each action incl. validation rejections, default-swap, delete-guard; authorization test (non-admin 403).

### T4.2 — Plans admin UI · **M**
- **Behavior:** `Admin/Plans/Index.vue` (list + status/default badges) and `Edit.vue` (grouped toggles/number inputs from the enum, translatable name/description). Flat, RTL, dark-mode per `.ai/Design rules` and `AdminLayout`.
- **Files:** *new* `resources/js/Pages/Admin/Plans/Index.vue`, `Edit.vue`.
- **Edge cases:** limit input blank = unlimited; boolean off = no row; bilingual name inputs; design compliance.
- **Acceptance:** admin can edit features/limits and save; UI matches conventions.
- **Test plan:** Pest smoke/visit tests for both pages; a save round-trip feature test.

### T4.3 — Tenant subscription + overrides panel · **L**
- **Behavior:** extend `TenantsController@show` to pass current subscription, plans list, overrides, and the effective-entitlements preview; add endpoints for change-plan, add-override, remove-override. Wire `Tenants/Show.vue` panel.
- **Files:** `app/Http/Controllers/Admin/TenantsController.php` (+ maybe a dedicated `TenantSubscriptionController`/`TenantOverridesController` for SRP); `resources/js/Pages/Admin/Tenants/Show.vue`; requests under `app/Http/Requests/Admin/`.
- **Edge cases:** override upsert over expired rows (no 500); override on a boolean vs limit validated by `Feature::type()`; `AssignPlanToTenant` leaves exactly one live subscription; `Entitlements::flush($tenant)` after each write; preview correct with no bound `currentTenant`; `LimitUsage` counts explicit-tenant.
- **Acceptance:** admin can change plan, grant/revoke/limit-override with expiry, and the preview updates in-request; audit rows written.
- **Test plan:** feature tests: change-plan (single live row), add/remove override (incl. re-add over expired), preview correctness in admin context, audit logging, flush freshness, authorization.

### T4.4 — Effective-entitlements preview component · **S**
- **Behavior:** read-only table of every feature: resolved value + source (override/plan/default), grouped. Consumes T4.3 data.
- **Files:** *new* `resources/js/Pages/Admin/Tenants/EntitlementsPreview.vue` (or a component under Admin).
- **Edge cases:** clearly mark overridden and expired-override features; RTL/dark; localized.
- **Acceptance:** preview matches `Entitlements::for($tenant)` for a known fixture.
- **Test plan:** covered via T4.3 render assertions.

## 7. Edge cases (cross-task)

- **Admin context has no `currentTenant`** — every read must use explicit `for($tenant)` / explicit-tenant counts (regression risk if a dev reaches for ambient calls).
- **Dual-authenticated super-admin** (also a merchant with a `current_tenant_id`) — override/subscription writes must land on the **target** tenant; asserted because the models are unscoped and must not auto-fill.
- Deleting/deactivating the default plan — blocked or forces choosing a new default first.

## 8. Test plan (summary)

- Plans CRUD + validation + invariants + delete-guard + authz (T4.1); Plans UI round-trip (T4.2); tenant change-plan/overrides/preview/audit/flush + admin-context correctness + wrong-tenant guard (T4.3); preview fidelity (T4.4).

## 9. Rollout & backwards compatibility

Additive admin tooling; no tenant-facing change. Can ship independently of F2/F3 (only needs F1). One PR, optionally split Plans-CRUD vs Tenant-assignment if review size demands.

## 10. Open questions

- Do we expose `price/currency/interval` fields now (read-only, for planning) or hide entirely until billing? Lean: **hide** (YAGNI) — schema-only.
- Should changing a tenant's plan mid-cycle be immediate (v1) or scheduled? Lean: **immediate** for entitlements-only v1; scheduling arrives with billing.
