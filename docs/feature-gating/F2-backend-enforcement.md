# PRD — F2: Backend enforcement

**Status:** Draft · **Phase:** F2 · **Depends on:** F1 · **PR grouping:** one PR (first module only)

## 1. Problem

F1 gives us a trustworthy way to *ask* about entitlements but enforces nothing. This phase adds the **server-side gates** — the only real security boundary (frontend gating in F3 is cosmetic) — and proves the pattern end-to-end on **one module** (`bookings`, a boolean) and **one limit** (`max_products`). Later modules reuse this machinery in F5.

## 2. Goals / Non-goals

**Goals**
- A `feature:` route middleware that blocks routes for un-entitled tenants, with the locked failure behavior (403 for JSON/non-GET/Inertia-partial; Inertia **Upgrade** page for full-page GET).
- A `WithinPlanLimit` validation rule that blocks resource creation at a plan's numeric cap (422, localized).
- Apply both to the first module: gate `bookings` routes behind `feature:bookings`; enforce `max_products` on product creation.
- Establish the **precedence contract** in code: gates compose with existing `can:` permission middleware (need both).
- A minimal Upgrade Inertia page (polished in F3).

**Non-goals**
- Rolling out across all modules (F5).
- Frontend nav/button hiding + `useFeatures` composable (F3) — F2 users may still *see* a nav item and hit the gate; F3 hides it proactively.
- Admin management of plans (F4).

## 3. Current state (from audit)

- **Route groups:** `routes/tenant.php` — the authenticated app sits in a nested group `['auth:sanctum', jetstream.auth_session, EnsureTenantIsActive, EnsureUserIsActiveInTenant, EnsurePasswordIsChanged]` (~line 133). This is where `feature:` middleware slots in, and it must run **after** `ResolveTenant` (outer group) so `currentTenant` is bound.
- **Permission gate precedent:** routes already use `->middleware('can:reports.view')` (e.g. `routes/tenant.php:392`) — `feature:` mirrors this exactly and composes with it.
- **Bookings:** `resources/js/Pages/Bookings`, booking routes in `routes/tenant.php`; a `BookingPolicy`/controllers exist.
- **Products creation:** product store request/controller under the catalog module; `Product` model tenant-scoped via `BaseModel`.
- Middleware aliases registered in `bootstrap/app.php` (`withMiddleware`).

## 4. Design & behavior

**`EnsureFeatureIsActive` middleware** (alias `feature`). Signature `feature:bookings` (one or more comma-separated boolean feature keys). For each key it calls `Entitlements::enabled(Feature::from($key))` (ambient tenant). If any is disabled:
- **Inertia partial reload** (`X-Inertia` + `X-Inertia-Partial-Data`) or **non-GET** or **`expectsJson`** → `abort(403)` with a localized message.
- **Full-page GET** → `Inertia::render('Upgrade', [...feature, planName...])` with a 403 status.
Only **boolean** features are valid here; passing a `Limit` key throws (guards §1a-class misuse). Runs after `ResolveTenant`; if `currentTenant` is somehow unbound the ambient resolver throws `NoTenantContextException` → surfaced as a 500 in dev, caught to 403 in prod (fail closed).

**`WithinPlanLimit` rule.** `new WithinPlanLimit(Feature::MaxProducts)` on the store `FormRequest`. On validation it reads `Entitlements::for($tenant)->remaining($feature)`; `null` (unlimited) passes; `<= 0` fails with a localized "plan limit reached" message naming the cap. Authoritative count comes from `LimitUsage` (server-side), independent of any client display. **Race note:** two concurrent creates can both pass — accepted for v1 (advisory). For limits where over-shoot is unacceptable (`max_users`), F5 additionally enforces inside the domain action, not only the FormRequest.

**Precedence.** Gated booking routes become `->middleware(['can:bookings.view', 'feature:bookings'])`. Products store keeps its `can:products.create` plus the new rule. Documented: **feature-entitled (tenant) AND permission (role)** are both required; features never touch RBAC.

## 5. Data model / schema changes

None. F2 is behavior only.

## 6. Task specs

### T2.1 — `EnsureFeatureIsActive` middleware · **M**
- **Behavior:** per §4; alias `feature` registered in `bootstrap/app.php`; accepts multiple keys (all must be enabled); rejects `Limit` keys; branches response on Inertia-partial / non-GET / expectsJson vs full-page GET.
- **Files:** *new* `app/Http/Middleware/EnsureFeatureIsActive.php`; `bootstrap/app.php` (alias).
- **Edge cases:** Inertia partial reload is a GET with headers — must 403, not render a full page (would break the client); unbound tenant → fail closed; unknown/limit key → throw (developer error).
- **Acceptance:** disabled feature → 403 (json/partial/non-GET) or Upgrade page (full GET); enabled → passes; limit key rejected.
- **Test plan:** feature tests across the response-branch matrix; a test that a `Limit` key throws; ordering test (runs after `ResolveTenant`).

### T2.2 — Upgrade Inertia page (minimal) · **S**
- **Behavior:** `resources/js/Pages/Upgrade.vue` — a flat, RTL/dark-mode-compliant page ("This feature isn't in your plan") showing the feature label and current plan, localized. Minimal in F2; F3 polishes and links to plans.
- **Files:** *new* `resources/js/Pages/Upgrade.vue`.
- **Edge cases:** must render under RTL and dark mode; strings localized.
- **Acceptance:** renders with the passed feature/plan props; passes design conventions.
- **Test plan:** a Pest smoke/visit test that the gated full-page route renders the Upgrade component.

### T2.3 — `WithinPlanLimit` rule + products limit · **M**
- **Behavior:** rule per §4; applied to the product store `FormRequest`.
- **Files:** *new* `app/Rules/WithinPlanLimit.php`; the product store request.
- **Edge cases:** unlimited (`null`) passes; exactly-at-cap fails; message localized and names the cap; count via `LimitUsage` (authoritative).
- **Acceptance:** at cap → 422 with localized message; below cap → passes; unlimited → passes.
- **Test plan:** feature tests: create at/below/over cap; unlimited plan; message localization.

### T2.4 — Gate the bookings module · **S**
- **Behavior:** add `feature:bookings` alongside existing `can:` on the bookings route group.
- **Files:** `routes/tenant.php` (bookings group).
- **Edge cases:** ensure every booking sub-route (create/cancel/etc.) is covered — no ungated backdoor; verify placement inside the authenticated (post-`ResolveTenant`) group.
- **Acceptance:** tenant without `bookings` gets 403/Upgrade on all booking routes; entitled tenant unaffected.
- **Test plan:** feature tests: entitled vs un-entitled tenant across booking routes; a **route-coverage test** asserting no booking route lacks the gate (reusable pattern for F5).

## 7. Edge cases (cross-task)

- Middleware ordering vs `ResolveTenant`/`EnsureTenantIsActive` — gates must resolve tenant first.
- `expectsJson` vs Inertia — Inertia requests do not set `Accept: application/json`; branch on Inertia headers explicitly.
- A disabled feature for a tenant on the **default (free) plan** behaves identically to any other un-entitled tenant (F1 fallback).

## 8. Test plan (summary)

- Middleware response-branch matrix + ordering + limit-key-rejection (T2.1); Upgrade render (T2.2); limit rule at/below/over/unlimited + localization (T2.3); bookings gated for un-entitled + route-coverage pattern (T2.4).

## 9. Rollout & backwards compatibility

Bookings and product-limit enforcement go live for existing tenants — so the **`PlanSeeder` defaults must include `bookings` and a `max_products` cap on the tiers existing tenants map to**, or existing merchants lose access. Verify tier assignment/back-fill (assign all current tenants a plan, or rely on the default-plan fallback including these features) **before** merging. One PR.

## 10. Open questions

- Should the default (free) plan include `bookings`? Decide with the F1 seeder tiers so no current tenant regresses.
- For `max_products`, do existing over-cap tenants (already above a new limit) get grandfathered? Lean: the rule blocks *new* creates only; it never deletes — so over-cap tenants keep their products but can't add until under cap or upgraded. Confirm this is the desired behavior.
