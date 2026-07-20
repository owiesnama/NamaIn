# PRD — F3: Frontend gating

**Status:** Draft · **Phase:** F3 · **Depends on:** F1 (F2 recommended) · **PR grouping:** one PR

## 1. Problem

With F2, an un-entitled tenant can still *see* a nav item or action button and only discovers the wall when they click it (403 / Upgrade page). That is a poor merchant experience. This phase adds the **soft gate**: entitlements are shared to the client and the UI proactively hides or disables features not in the tenant's plan, and shows purposeful upgrade affordances. This is UX only — the security boundary remains the server (F2/F5).

## 2. Goals / Non-goals

**Goals**
- Share an `entitlements` prop from Inertia (booleans + limit caps; cheap/eager).
- A `useFeatures()` composable mirroring `usePermissions()` — `hasFeature()`, `limit()`, and an `atLimit()`/`remaining()` helper for pages that fetch usage.
- Gate nav items and primary action buttons on `hasFeature()`; show an inline upgrade hint where a feature is off.
- Polish the Upgrade page from F2 (link to plans / contact, clear CTA).
- A lazy path for **usage counts** (the "12 / 50 used" display) so we don't run N COUNT queries every request.

**Non-goals**
- Server enforcement (F2/F5) — this layer is cosmetic and must never be the only gate.
- Admin UI (F4).
- Per-module rollout of gates (F5 wires each module's nav/buttons as it gates them; F3 establishes the composable + shared prop + the bookings example).

## 3. Current state (from audit)

- **Shared props:** `app/Http/Middleware/HandleInertiaRequests.php` `share()` exposes `user.permissions` (flat slug array, lazy), `currentTenant`, `preferences`, `flash`, `locale`, etc. `entitlements` slots in here.
- **Permission composable precedent:** `resources/js/Composables/usePermissions.js` — `can()`, `hasRole()`, `isOwner()` reading `page.props.user.permissions`. `useFeatures()` mirrors it exactly.
- **Usage in views:** `resources/js/Pages/**` and `Layouts/AppLayout.vue` gate nav/actions with `can(...)` (e.g. `Customers/Index.vue`, `Users/Index.vue`). Same call sites gain `hasFeature(...)`.

## 4. Design & behavior

**Shared prop.** `HandleInertiaRequests::share()` adds (lazily):
```
entitlements: {
  features: { bookings: true, pos: false, ... },   // every boolean Feature, resolved
  limits:   { max_products: 50, max_users: 3, ... } // caps only; null = unlimited
}
```
Computed from `Entitlements::for(currentTenant)` — booleans + caps are cheap (one memoized load). **Usage counts are excluded** (they are N COUNT queries); pages that need them fetch via a lazy Inertia prop or a tiny endpoint. When there is no tenant (central/admin pages), the prop is `null`/omitted (guarded).

**Composable.** `useFeatures()`:
```
hasFeature(key): boolean          // features[key] === true
limit(key): number|null           // limits[key]
remaining(key, used): number|null // limit - used (null if unlimited)
atLimit(key, used): boolean
```
Nav/buttons use `v-if="hasFeature('bookings') && can('bookings.view')"` — mirroring the precedence contract (feature AND permission). Where hiding entirely is wrong (discoverability of upgrades), show the item **disabled with an upgrade hint** instead.

**Upgrade affordance.** A small reusable `<FeatureLockHint>` (flat, RTL/dark) for inline "not in your plan — upgrade" messaging, plus the polished `Upgrade.vue`.

## 5. Data model / schema changes

None.

## 6. Task specs

### T3.1 — Share `entitlements` prop · **M**
- **Behavior:** add the lazy `entitlements` block to `share()` per §4; resolve booleans + caps from `Entitlements::for(currentTenant)`; omit/null when no tenant.
- **Files:** `app/Http/Middleware/HandleInertiaRequests.php`.
- **Edge cases:** central/admin/no-tenant requests must not throw (guard with `app()->bound('currentTenant')`); lazy so it costs nothing on requests that don't read it; no usage counts here.
- **Acceptance:** tenant request exposes correct `features`/`limits`; no-tenant request omits it; single resolver load (no N+1).
- **Test plan:** feature tests asserting prop shape/values for entitled vs un-entitled tenant; no-tenant request has no `entitlements`; query-count assertion.

### T3.2 — `useFeatures()` composable · **S**
- **Behavior:** per §4, reading `page.props.entitlements`; null-safe defaults (missing prop → everything false / limits null).
- **Files:** *new* `resources/js/Composables/useFeatures.js`.
- **Edge cases:** prop absent (central pages) → `hasFeature` returns false, `limit` returns null, no crash.
- **Acceptance:** returns correct booleans/limits; safe with missing prop.
- **Test plan:** component/unit test (or a Pest browser smoke) covering present and absent prop.

### T3.3 — Nav + button gating (bookings example) · **M**
- **Behavior:** gate the bookings nav entry and booking action buttons on `hasFeature('bookings') && can(...)`; add `<FeatureLockHint>` where an upgrade nudge is warranted.
- **Files:** `resources/js/Layouts/AppLayout.vue` (nav), bookings Vue pages; *new* `resources/js/Components/FeatureLockHint.vue`.
- **Edge cases:** RTL + dark mode compliance; don't hide items the tenant *does* have; entitled tenant sees no hints.
- **Acceptance:** un-entitled tenant: bookings nav/actions hidden or hinted; entitled tenant: unchanged.
- **Test plan:** browser/smoke test for both tenant states; design-conventions check.

### T3.4 — Polish Upgrade page + lazy usage path · **S**
- **Behavior:** finish `Upgrade.vue` (CTA to plans/contact, feature label, current plan); document/implement the lazy usage fetch pattern for limit displays (a partial-reload prop or small `GET` endpoint returning `{used, limit}` for a given feature).
- **Files:** `resources/js/Pages/Upgrade.vue`; a usage endpoint/controller or lazy prop; a sample "X / Y used" display on the products page.
- **Edge cases:** usage endpoint must use `LimitUsage` (explicit-tenant) and respect permissions; unlimited → hide the "/ Y" cap.
- **Acceptance:** Upgrade page complete + localized; products page shows accurate used/limit lazily.
- **Test plan:** Upgrade render test; usage-endpoint feature test (correct count, auth, unlimited case).

## 7. Edge cases (cross-task)

- **Never trust the client:** hidden buttons are not a boundary — F2/F5 server gates are. A test should assert a route stays 403 even when the client "would have" hidden it.
- Central-domain / `/__admin` pages have no tenant entitlements — all consumers must tolerate the absent prop.
- Locale switching: labels via `__()` / translations; caps are Western-digit numbers per the money/date format standard.

## 8. Test plan (summary)

- Prop shape/values + no-tenant omission + query count (T3.1); composable null-safety (T3.2); nav/button gating both states (T3.3); Upgrade + lazy usage (T3.4). Plus a cross-cutting test that server gates hold regardless of client state.

## 9. Rollout & backwards compatibility

Purely additive UI. With F2 merged, this removes the "click then hit a wall" experience for bookings; other modules get the same treatment as they are gated in F5. One PR.

## 10. Open questions

- Hide vs disable-with-hint as the default for un-entitled nav items — lean: **hide** secondary items, **disable+hint** headline upsell features (bookings, POS). Confirm per feature during F5.
- Lazy usage: partial-reload prop vs dedicated endpoint — pick one convention here and reuse.
