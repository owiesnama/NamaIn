# Feature Gating & Subscriptions — PRDs

Product requirements for gating the NamaIn app on a **per-tenant, plan-driven** basis. A tenant's access to features is determined by its **subscription plan**, managed by super-admins in the `/__admin` panel. **v1 is entitlements-only: there is no payment gateway** — admins assign plans manually. Billing (self-serve checkout, webhooks, invoices, dunning) is deferred and will be wired later against the same tables.

> **Design headline:** Everything is greenfield — no `Plan`/`Subscription`/`Feature` models, no billing, no feature flags exist today (`Tenant` is bare: `id, name, slug, is_active`). We build a **plain typed `Entitlements` service** — deliberately **not** Laravel Pennant (a strict review found Pennant's differentiators would all be switched off here while its `active() === value !== false` rule and null-scope-silent-false behavior import entitlement-escalation footguns). Feature catalog = a code-defined PHP enum (mirrors the existing `PermissionSeeder` pattern). Entitlements (plan-level, "what the tenant bought") are **orthogonal** to the existing per-tenant RBAC permissions (role-level, "what the user may do"): an action requires **both**.

## Two-layer model (do not conflate)

| Layer | Question | Scope | Status |
|---|---|---|---|
| **Entitlements** (this initiative) | Does this *tenant's plan* include it? | per-tenant | new |
| **Permissions** (`Role`/`Permission`) | May this *user's role* do it? | per-user-in-tenant | exists, unchanged |

## Milestones

| PRD | Phase | One PR? | Depends on |
|---|---|---|---|
| [F1](F1-core-entitlements-engine.md) | Core entitlements engine (enums, tables, models, `Entitlements` service, seeder) | yes | — |
| [F2](F2-backend-enforcement.md) | Backend enforcement (`feature:` middleware, `WithinPlanLimit`, Upgrade page) on a first module | yes | F1 |
| [F3](F3-frontend-gating.md) | Frontend gating (shared prop, `useFeatures`, nav/button gating) | yes | F1 (F2 recommended) |
| [F4](F4-admin-management.md) | Admin: Plans CRUD + tenant assign-plan / override / preview | yes | F1 |
| [F5](F5-module-rollout.md) | Roll out `feature:` gates + limits across remaining modules | one PR per module group | F2, F3 |

**Critical path:** F1 → F2 → F5; F3 and F4 depend only on F1 and can proceed in parallel once F1 lands. F5 fans out per module and needs F2 (backend gates) + F3 (nav/button UX) in place.

## Locked decisions (from the design + strict review)

- **No Pennant.** Plain typed `Entitlements` service: `enabled(Feature): bool`, `limit(Feature): ?int` — strict types make boolean/limit confusion impossible.
- **Feature catalog is a code enum** `App\Features\Feature` (+ `FeatureType` = Boolean | Limit). Two kinds: boolean capabilities and numeric limits (quotas; `null` = unlimited).
- **Effective value** = live per-tenant override → else active-plan `plan_features` value → else `Feature::default()`. **No active subscription → fall back to the `is_default` (free) plan.** **No tenant resolvable at all → throw** (fail loud, never silent-false).
- **Tenant resolution for entitlements replicates `TenantScope::resolveTenantId()` exactly** (`app/Scopes/TenantScope.php:22`): bound `currentTenant` → else `auth()->user()->current_tenant_id` → else null/throw. `Subscription` and `TenantFeatureOverride` are **NOT** tenant-scoped (no `BelongsToTenant`) — like `Tenant` itself — to keep the admin panel (no bound tenant) correct and prevent wrong-tenant auto-fill.
- **Subscription lifecycle:** assign-plan **transactionally terminates** the prior active row (so overlap is impossible); `trialing` expiry is enforced in the query (`trial_ends_at < now` excluded) since no billing engine will transition it. v1 statuses: `active | trialing | canceled` only.
- **Gate failure:** 403 for JSON / non-GET / Inertia partial reloads; an Inertia **Upgrade** page for full-page GET.
- **Single `Entitlements::flush()`** clears the per-request memo after any plan/feature/subscription/override write; the memo is **keyed by tenant id** (safe inside per-tenant loop commands).
- **Provisioning:** no Free-subscription row is created at tenant signup — rely purely on the no-sub→default-plan fallback (one fewer code path).
- **Plan names are translatable** (ar + en), per the repo's localization rule and RTL/Arabic user base.

## Cross-cutting conventions (apply to every task)

- **TDD** — Pest test written first (`php artisan make:test --pest`); run `php artisan test --compact --filter=…`. 100% model coverage per repo rule.
- **Localization** — every user-facing string via `__()`; Arabic-first. Upgrade page, validation messages, plan names included.
- **RTL + dark mode** — every Vue element per `.ai/Design rules`.
- **Pint** — `vendor/bin/pint --dirty` on touched PHP before finalizing.
- **Tenant scoping is deliberate here** — `Plan`/`plan_features` are global catalog (no tenant_id); `Subscription`/`TenantFeatureOverride` carry `tenant_id` but are **explicitly unscoped** (see locked decisions).
- **Arch tests** — a Pest `arch()` test asserts every `Limit`-typed enum case has a `LimitUsage` mapping.

## PRD template

Each PRD follows the repo standard (see `docs/inventory-ledger/README.md`):

```
# PRD — Fx: <Title>
Status · Phase · Depends on · PR grouping
## 1. Problem
## 2. Goals / Non-goals
## 3. Current state (from audit, with file:line)
## 4. Design & behavior
## 5. Data model / schema changes
## 6. Task specs   → per task: Behavior · Files · Edge cases · Acceptance criteria · Test plan · Size
## 7. Edge cases (cross-task)
## 8. Test plan (summary)
## 9. Rollout & backwards compatibility
## 10. Open questions
```
