# PRD — M3: Strategy setting + policy layer

**Status:** Draft · **Milestone:** M3 · **Depends on:** M2 · **PR grouping:** one PR (T3.1, T3.2, T3.4) + one **separate** hygiene PR (T3.3, prerequisite)

## 1. Problem

Today inventory behavior is hard-coded: positive stock enters (almost) only via purchase invoices, and `Storage::deductStock` blocks unconditionally when stock is short. Merchants split into two camps — one wants strict purchase-driven discipline, the other wants to manage quantities freely and even oversell into negative. We need a per-tenant setting that answers **"how do you want to manage your inventory?"** and a single policy layer that turns that answer into concrete validation rules, so enforcement isn't scattered across controllers/actions. This milestone introduces the setting and the policy contract but does **not** yet wire enforcement into the write paths (that is M4) — it ships behavior-neutral with every tenant defaulting to `purchase_driven` (exactly today's behavior).

## 2. Goals / Non-goals

**Goals**
- A per-tenant `inventory_strategy` (`purchase_driven` | `free_form`) with a nested `allow_overselling` boolean (only meaningful under `free_form`).
- A single `InventoryStrategy` policy layer that resolves the tenant's setting into rule methods — the one place M4 (and everything after) asks "can this deduction proceed?" / "may stock be manually increased?".
- Owner/admin settings UI to choose the strategy, with the overselling toggle shown only for `free_form`.
- Default `purchase_driven` for all existing and new tenants — no behavior change on ship.

**Non-goals**
- Wiring the strategy into `deductStock`/POS/adjustments or making the DB column signed (all M4).
- Recording a dated settings-change audit event (M6).
- Any costing/reporting change (M7/M8).

## 3. Current state (from audit)

- Preferences are a tenant-scoped key/value store: model `app/Models/Preference.php`, read helper `preference($key, $default)` (`bootstrap/helpers.php:60`), written via `app/Actions/UpdatePreferences.php` (`updateOrCreate` + `Cache::forget('preferences')`), UI `app/Http/Controllers/Core/PreferenceController.php` → `resources/js/Pages/Preferences/Show.vue`, gated to owner/admin.
- **Allowed keys are whitelisted** in `app/Http/Requests/PreferenceRequest.php:19` (`logo, invoicesHeadline, alerts, currency, pecentage`) — any key not listed is silently dropped by `$request->validated()`. There is **no unique `(tenant_id, key)` index** on `preferences`.
- Today's only hard enforcement point is `Storage::deductStock` (`app/Models/Storage.php:156-158`), which throws `InsufficientStockException` unconditionally; POS has its own advisory precheck (`ProcessPosCheckoutAction.php:67-81`). Net-new positive stock without an invoice is possible only via Adjustments (`RecordAdjustmentAction`).
- **Preferences cache key is not tenant-qualified:** `Cache::rememberForever('preferences', …)` at `ResolveTenant.php:33`, `HandleInertiaRequests.php:124`, `HandleLocale.php:29`, `User.php:181`, invalidated with the same flat key at `UpdatePreferences.php:26` — a cross-tenant staleness/leak risk on shared cache stores.

## 4. Design & behavior

Add two preference keys. `inventory_strategy` selects the primary mode; `allow_overselling` is a nested sub-setting that is only read/shown under `free_form` (under `purchase_driven` it is irrelevant and hidden — strict mode always blocks). The **ledger schema is identical in every configuration**; the strategy only changes which entry paths are permitted and how deductions validate.

Enforcement rules live behind an `InventoryStrategy` contract with two concrete implementations resolved from the tenant's setting:

- `PurchaseDrivenStrategy`: `allowsManualStockIncrease() = false`, `allowsOverselling() = false`, `assertCanDeduct()` throws when `requested > available`.
- `FreeFormStrategy`: `allowsManualStockIncrease() = true`, `allowsOverselling()` = the `allow_overselling` flag, `assertCanDeduct()` is a no-op when overselling is allowed, otherwise blocks at zero.

A resolver (bound in the container / a small factory) reads `preference('inventory_strategy', 'purchase_driven')` + `preference('allow_overselling', false)` and returns the right instance. This is the single seam M4 consumes. The settings UI presents a primary radio and a conditionally-visible overselling toggle.

## 5. Data model / schema changes

- **No table/column changes.** New behavior rides on the existing key/value `preferences` table via two new keys (`inventory_strategy`, `allow_overselling`).
- **Recommended (in T3.3 hygiene PR):** add a unique index on `preferences (tenant_id, key)` — none exists today, and strategy correctness depends on a single row per key per tenant. (Guarded/optional if legacy duplicate rows exist — dedupe first.)
- The signed-`stocks.quantity` migration is explicitly deferred to M4.

## 6. Task specs

### T3.1 — `InventoryStrategy` policy layer · **M**
- **Behavior:** contract `App\Services\Inventory\InventoryStrategy` with `allowsManualStockIncrease(): bool`, `allowsOverselling(): bool`, `assertCanDeduct(int $available, int $requested): void` (throws `InsufficientStockException`-family / a dedicated `OversellNotAllowedException` when blocking). Two implementations `PurchaseDrivenStrategy`, `FreeFormStrategy`. A resolver (`InventoryStrategyResolver` or container binding) maps the tenant's preferences → the concrete strategy; default `purchase_driven`.
- **Files:** *new* `app/Services/Inventory/InventoryStrategy.php` (contract), `PurchaseDrivenStrategy.php`, `FreeFormStrategy.php`, resolver/factory; register in a service provider (e.g. `app/Providers/AppServiceProvider.php`).
- **Edge cases:** missing/blank preference → default `purchase_driven`; `free_form` with `allow_overselling` unset → treat as `false` (block at zero); `allow_overselling=true` under `purchase_driven` → ignored (strict always blocks).
- **Acceptance criteria:** resolver returns `PurchaseDrivenStrategy` by default; each method returns the correct value for both strategies and both overselling states; `assertCanDeduct` throws only when it should.
- **Test plan:** unit tests for each strategy × method × overselling state; resolver test asserting default and each configured combination; assert `purchase_driven` ignores `allow_overselling`.

### T3.2 — Preference keys · **S**
- **Behavior:** add `inventory_strategy` (`required|in:purchase_driven,free_form`, default `purchase_driven`) and `allow_overselling` (`boolean`, default `false`) to the whitelist so `UpdatePreferences` persists them; expose current values to the settings page.
- **Files:** `app/Http/Requests/PreferenceRequest.php:19` (rules), `app/Actions/UpdatePreferences.php` (already generic — verify boolean casting), `app/Http/Controllers/Core/PreferenceController.php` (pass current values to Inertia).
- **Edge cases:** boolean coercion from checkbox ('0'/'1'/'true'); `allow_overselling` submitted while `purchase_driven` → persist but never read under strict (harmless); unknown values rejected by `in:` rule.
- **Acceptance criteria:** both keys round-trip through save; invalid `inventory_strategy` rejected; defaults apply when absent.
- **Test plan:** feature test posting each strategy + toggle asserting persisted `Preference` rows and validation rejects bad values.

### T3.3 — (SEPARATE PR, prerequisite) Tenant-qualify preferences cache key · **M**
- **Behavior:** replace the flat `'preferences'` cache key with a per-tenant key `"preferences:{tenantId}"` at every read/write/invalidation site so tenants never share cached preferences (correctness precondition for reading the strategy via cache).
- **Files:** `app/Http/Middleware/ResolveTenant.php:33`, `app/Http/Middleware/HandleInertiaRequests.php:124`, `app/Http/Middleware/HandleLocale.php:29`, `app/Models/User.php:181`, `app/Actions/UpdatePreferences.php:26`.
- **Edge cases:** no resolved tenant (central/admin context) → skip cache or use a sentinel; invalidation must forget the correct tenant key after a save; existing warm caches under the old flat key become orphaned (harmless, expire/ignored).
- **Acceptance criteria:** two tenants with different preferences never read each other's cached values; saving a preference invalidates only that tenant's cache.
- **Test plan:** feature regression test: set differing preferences for two tenants, assert cross-tenant isolation of cached reads; assert invalidation after update.

### T3.4 — Settings UI · **M**
- **Behavior:** on `Preferences/Show.vue`, add an "inventory strategy" section — a primary radio/segmented control (`purchase_driven` vs `free_form`) with helper copy framed as "how do you want to manage your inventory?", and an `allow_overselling` toggle rendered **only when** `free_form` is selected (hidden/disabled under `purchase_driven`). Localized (`__()`), RTL, dark-mode per `.ai/Design rules`.
- **Files:** `resources/js/Pages/Preferences/Show.vue` (+ any shared toggle/radio component under `resources/js/Components`).
- **Edge cases:** switching to `purchase_driven` should hide (and not submit a misleading) overselling value; ensure the toggle state persists correctly on reload; all strings translatable including the two mode descriptions.
- **Acceptance criteria:** overselling toggle hidden under purchase_driven and visible under free_form; selection saves and reloads correctly; no hard-coded English.
- **Test plan:** Inertia/feature test asserting the page receives current strategy props and save round-trips; (optional) a Dusk/browser check that the toggle shows only for free_form.

## 7. Edge cases (cross-task)

- No resolved tenant (admin/central routes) → strategy resolver and cache must degrade to the safe default without querying tenant preferences.
- Legacy duplicate `preferences` rows for the same key (no unique index today) → dedupe before adding the unique index in T3.3, else the migration fails.
- Because M4 is not yet shipped, choosing `free_form` in the UI has **no runtime effect** on deductions until M4 lands — acceptable for this milestone; call it out in the PR description.

## 8. Test plan (summary)

- Strategy unit tests: every strategy × method × overselling state, plus resolver default (T3.1).
- Preference persistence + validation feature test (T3.2).
- Cross-tenant cache isolation + invalidation regression test (T3.3).
- Settings page prop/round-trip test (T3.4).
- Regression: existing preference and stock feature tests still green (no behavior change this milestone).

## 9. Rollout & backwards compatibility

Fully additive and behavior-neutral: default `purchase_driven` reproduces today's behavior exactly, and enforcement isn't wired until M4, so shipping M3 changes nothing for existing tenants. T3.3 must land first (separate hygiene PR) so the strategy can be read through a correctly-scoped cache. Ship T3.1/T3.2/T3.4 as one PR after T3.3.

## 10. Open questions

- Store `allow_overselling` as its own key vs. encoding a single richer `inventory_strategy` value (e.g. `free_form_oversell`)? (Lean: two keys — matches the nested-toggle UI and keeps the strict case clean.)
- Should the strategy resolver cache the resolved instance per request (it reads two preferences) or resolve lazily each call? (Lean: resolve once per request via container binding.)
- Do we add the unique `(tenant_id, key)` index now (T3.3) or as a standalone data-hygiene migration? (Lean: include in T3.3 with a dedupe step.)
