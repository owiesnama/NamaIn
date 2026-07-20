# PRD — F1: Core entitlements engine

**Status:** Draft · **Phase:** F1 · **Depends on:** — · **PR grouping:** one PR

## 1. Problem

The app has no notion of a subscription plan or of features a tenant is entitled to. `Tenant` is bare (`id, name, slug, is_active`; `app/Models/Tenant.php`) and authorization today is purely per-tenant RBAC (`Role`/`Permission`, `$user->hasPermission()`). Before we can gate anything (F2/F3) or manage plans (F4), we need the **data model** and a **single, typed, testable resolution layer** that answers "is feature X enabled / what is the limit of feature Y for tenant T?". This phase builds that engine end-to-end but **gates nothing** — it is safe to merge with zero user-visible change.

## 2. Goals / Non-goals

**Goals**
- A code-defined feature catalog: `Feature` enum + `FeatureType` (Boolean | Limit) with per-case metadata (`type`, `group`, `labelKey`, `default`).
- Tables: `plans`, `plan_features`, `subscriptions`, `feature_tenant`.
- Models `Plan`, `Subscription`, `TenantFeatureOverride` (unguarded; **not** tenant-scoped) + factories/states.
- `Tenant::currentSubscription()` / `activePlan()`; an `AssignPlanToTenant` action that transactionally terminates the prior active subscription.
- `Entitlements` service (`for()`, `enabled`, `limit`, `usage`, `remaining`, `flush`) with per-tenant-keyed request memoization, resolving via override → plan → default and falling back to the default plan when no active subscription.
- `LimitUsage` registry mapping each limit feature to an **explicit-tenant** count query.
- `PlanSeeder` seeding Free / Basic / Pro.

**Non-goals**
- Any enforcement — no middleware, no validation rule, no route changes (F2).
- Any frontend prop or composable (F3).
- Any admin UI (F4).
- Billing / payment / pricing UI (deferred; `price/currency/interval` columns are schema-only).

## 3. Current state (from audit)

- **Tenancy:** custom single-DB. `app/Scopes/TenantScope.php:22` `resolveTenantId()` = `app()->bound('currentTenant') ? currentTenant->id : (auth()->check() ? auth()->user()->current_tenant_id : null)`; null → `whereRaw('1 = 0')`. `app/Traits/BelongsToTenant.php` auto-fills `tenant_id` from `auth()->user()->current_tenant_id` first, then `currentTenant` — the reason `Subscription`/override models must **not** use it (a dual-authed super-admin would stamp their own tenant).
- `Tenant` extends plain `Model` (not `BaseModel`), so it is unscoped — the template for our new billing models.
- **Permission catalog precedent:** `database/seeders/PermissionSeeder.php` defines grouped permission slugs in code — the same code-as-catalog pattern we mirror for features.
- **Per-tenant loop commands** rebind `app()->instance('currentTenant', $tenant)` (`ReconcileStockCommand`, `BackfillOpeningBalancesCommand`) — the reason the resolver memo must key by tenant id, not "once per request".
- No `plan`, `subscription`, `feature`, `cashier`, `pennant` anywhere.

## 4. Design & behavior

**Feature catalog.** `App\Features\Feature` (string-backed enum). Each case declares `type(): FeatureType`, `group(): string`, `labelKey(): string` (i18n key — never a literal), `default(): bool|int|null`. Initial catalog: booleans `bookings, pos, multi_warehouse, quotes, advanced_reports, exports, cheques`; limits `max_products, max_users, max_warehouses`.

**Resolution (`Entitlements`).** Public API:
```
Entitlements::for(Tenant $t)->enabled(Feature $f): bool
Entitlements::for(Tenant $t)->limit(Feature $f): ?int          // null = unlimited
Entitlements::for(Tenant $t)->usage(Feature $f): int           // via LimitUsage
Entitlements::for(Tenant $t)->remaining(Feature $f): ?int       // null if unlimited
Entitlements::enabled(Feature $f): bool                          // ambient tenant
Entitlements::flush(?Tenant $t = null): void
```
Ambient resolution replicates `TenantScope::resolveTenantId()`. If **no tenant is resolvable**, ambient calls **throw** `NoTenantContextException` (never silent-false). Explicit `for($tenant)` always works (used by admin preview in F4).

`activePlan(Tenant $t)`: the tenant's latest subscription with status `active|trialing`, `ends_at` null-or-future, and (if `trialing`) `trial_ends_at` null-or-future; else `Plan::where('is_default', true)`. Effective value for a feature = live override (`feature_tenant`, `expires_at` null-or-`> now`) → else that plan's `plan_features` value → else `Feature::default()`. Value is cast **strictly**: `Boolean` → `(bool)`, `Limit` → `?int`. All of a tenant's `plan_features` + overrides load **once**, memoized by tenant id (via `once()` on a method taking the tenant as an explicit argument), so resolving N features = 1 load. `flush()` clears the memo (and is called by F4 after writes).

**Usage.** `LimitUsage` maps each `Limit` feature to a closure returning the tenant's current count, run with `withoutGlobalScope(TenantScope::class)->where('tenant_id', $t->id)` so it is correct even in admin-preview context (no bound tenant). E.g. `max_products → Product::where('tenant_id', $t->id)->count()`.

**Assign plan.** `AssignPlanToTenant` action: inside a DB transaction, mark any current active/trialing subscription `canceled` with `ends_at = now()`, then create the new `active` (or `trialing`) row. Guarantees at most one live subscription per tenant.

## 5. Data model / schema changes

- **`plans`**: `id`, `key` (unique), `name` (translatable — json or `spatie`-style pair per repo convention), `description` (translatable, nullable), `is_active` (bool), `is_default` (bool; **partial unique index where is_default = true**), `sort` (int), `price` (nullable), `currency` (nullable), `interval` (nullable), timestamps. Global catalog — **no `tenant_id`**.
- **`plan_features`**: `id`, `plan_id` (FK, `restrictOnDelete`), `feature_key` (string), `value` (**json**, nullable), unique `(plan_id, feature_key)`. Global — no `tenant_id`.
- **`subscriptions`**: `id`, `tenant_id` (FK), `plan_id` (FK, `restrictOnDelete`), `status` (`active|trialing|canceled`), `trial_ends_at` (nullable), `starts_at`, `ends_at` (nullable), timestamps. History table; carries `tenant_id` but the model is **unscoped**.
- **`feature_tenant`**: `id`, `tenant_id` (FK), `feature_key` (string), `value` (json, nullable), `expires_at` (nullable), unique `(tenant_id, feature_key)`. Unscoped model; writes are **upserts** (an expired row on the unique key must not 500).
- All additive; fully reversible.

## 6. Task specs

### T1.1 — `Feature` + `FeatureType` enums · **M**
- **Behavior:** `FeatureType` enum (`Boolean`, `Limit`). `Feature` string-backed enum with the initial catalog; methods `type()`, `group()`, `labelKey()`, `default()`. `Feature::booleans()` / `Feature::limits()` helpers.
- **Files:** *new* `app/Features/Feature.php`, `app/Features/FeatureType.php`.
- **Edge cases:** `labelKey()` must return translation keys, not literals. Every case must have a `default()` (booleans → `false`; limits → a sensible int or `null`=unlimited).
- **Acceptance:** every case resolves a type/group/labelKey/default; no duplicate backing values.
- **Test plan:** unit test iterating all cases asserting metadata present and typed; assert booleans() ∪ limits() = all cases, disjoint.

### T1.2 — Migrations · **M**
- **Behavior:** four additive migrations per §5, with the `is_default` partial-unique index and FK `restrictOnDelete`.
- **Files:** *new* migrations under `database/migrations/`.
- **Edge cases:** partial unique on `is_default` (Postgres partial index / MySQL generated-column trick — match the DB in use); json `value` columns nullable.
- **Acceptance:** `migrate` + `migrate:rollback` clean; constraints enforced (second `is_default=true` insert fails).
- **Test plan:** migration test asserting tables/columns/indexes; a test asserting two default plans cannot coexist.

### T1.3 — Models + factories · **M**
- **Behavior:** `Plan`, `Subscription`, `TenantFeatureOverride` — unguarded (`unguard()` in `boot`), **no `BelongsToTenant`**. Relations: `Plan hasMany planFeatures / subscriptions`; `Subscription belongsTo plan, tenant`; casts for translatable name, json values, datetime `trial_ends_at/starts_at/ends_at/expires_at`, status enum. Factories with states: `Plan::factory()->default()`, `Subscription::factory()->active()/trialing()/canceled()/expired()`, override `->expired()`.
- **Files:** *new* `app/Models/Plan.php`, `Subscription.php`, `TenantFeatureOverride.php`, factories.
- **Edge cases:** models must remain unscoped even though they hold `tenant_id`; assert no global scope applied.
- **Acceptance:** 100% model coverage; a `Subscription` created from an admin context (no bound tenant) persists the intended `tenant_id`, not the actor's.
- **Test plan:** model unit tests for relations/casts/states; explicit test that these models are NOT tenant-scoped.

### T1.4 — `Tenant` subscription helpers + `AssignPlanToTenant` · **M**
- **Behavior:** `Tenant::subscriptions()` (hasMany), `currentSubscription()` (query per §4), `activePlan()`. `AssignPlanToTenant($tenant, $plan, trial?)` action: transactional terminate-prior-then-create.
- **Files:** `app/Models/Tenant.php`; *new* `app/Actions/Subscriptions/AssignPlanToTenant.php`.
- **Edge cases:** trialing past `trial_ends_at` excluded from `currentSubscription()`; `ends_at == now()` boundary (`> now` excludes it) — test explicitly; assigning when a prior active exists must leave exactly one live row.
- **Acceptance:** at most one live subscription after any assign; expired trials never returned as current.
- **Test plan:** feature tests: assign-over-existing leaves one live row (transactional); expired-trial fallback; `ends_at` boundary.

### T1.5 — `Entitlements` service · **L**
- **Behavior:** the public API in §4; per-tenant-keyed memoized load of plan_features + overrides; strict casting by `FeatureType`; ambient tenant resolution mirroring `TenantScope`; `NoTenantContextException` when unresolvable; `flush()`.
- **Files:** *new* `app/Features/Entitlements.php`, `app/Features/Exceptions/NoTenantContextException.php`; bind as singleton in a `FeatureServiceProvider`.
- **Edge cases:** falsy stored values (`0`, `"0"`, `false`, `null`, absent row) for a **boolean** feature must resolve `false` where intended (the strict-cast regression); memo must not leak across tenants in a rebind loop; a `Limit` never returns `false` (only `int|null`).
- **Acceptance:** override beats plan beats default; no-sub → default plan; unresolvable tenant throws; N features = 1 load.
- **Test plan:** **falsy-value matrix** (each falsy encoding → intended bool); override-wins + expiry boundary; no-sub fallback; unresolvable-throws; query-count assertion (1 load for N features); tenant-loop rebinding returns fresh values.

### T1.6 — `LimitUsage` registry · **S**
- **Behavior:** map every `Limit` feature → explicit-tenant count closure (`withoutGlobalScope` + `where tenant_id`). Consumed by `Entitlements::usage/remaining`.
- **Files:** *new* `app/Features/LimitUsage.php`.
- **Edge cases:** counts must be correct with **no** bound `currentTenant` (admin preview). Missing mapping for a limit case is a bug.
- **Acceptance:** usage correct in both tenant-request and no-tenant (admin) contexts.
- **Test plan:** unit test per limit; **arch test**: every `Feature::limits()` case has a `LimitUsage` entry.

### T1.7 — `PlanSeeder` (Free / Basic / Pro) · **S**
- **Behavior:** seed three plans with `plan_features` rows; mark Free `is_default`. Idempotent (`updateOrCreate` by `key`).
- **Files:** *new* `database/seeders/PlanSeeder.php`; register in `DatabaseSeeder`.
- **Edge cases:** exactly one `is_default`; feature keys validated against the enum; re-running doesn't duplicate.
- **Acceptance:** after seed, three plans exist, one default, feature values map to real enum cases.
- **Test plan:** seeder test asserting counts, single default, all `feature_key`s ∈ enum.

## 7. Edge cases (cross-task)

- **Enum-vs-DB drift:** a `plan_features`/`feature_tenant` row whose `feature_key` is no longer an enum case must be **ignored** at read time (resolver iterates the enum, not the rows). Admin write-side validation (F4) rejects unknown keys.
- **No default plan configured:** `activePlan()` returns null → resolution falls to `Feature::default()` for everything. Acceptable but should be surfaced (log/warn); seed guarantees a default exists.
- **Dual cache is gone** — only the resolver memo exists (no Pennant), single `flush()` invalidates it.

## 8. Test plan (summary)

- Enum metadata + partition (T1.1); migrations + partial-unique (T1.2); model relations/casts + not-scoped (T1.3); subscription helpers + transactional assign + boundaries (T1.4); **falsy matrix + resolution precedence + throw + query-count + rebind** (T1.5); usage in both contexts + arch mapping (T1.6); seeder invariants (T1.7). 100% model coverage.

## 9. Rollout & backwards compatibility

Fully additive; nothing is gated. Safe to deploy ahead of F2–F5 — it is their prerequisite. One PR, off a fresh branch from `master`.

## 10. Open questions

- Translatable-name storage: json column vs a `HasTranslations`-style trait — pick whatever the codebase already uses for translatable admin content (verify during T1.3).
- `max_*` sensible defaults for the Free tier — proposed in `PlanSeeder`, tunable in F4's admin UI.
