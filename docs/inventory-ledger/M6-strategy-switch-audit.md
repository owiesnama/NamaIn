# PRD — M6: Strategy-switch flow + audit event

**Status:** Draft · **Milestone:** M6 · **Depends on:** M3 · **PR grouping:** one PR

## 1. Problem

Tenants must be able to change their inventory strategy (`purchase_driven` ↔ `free_form`) or flip `allow_overselling` at any point in their operating life. Two things are missing today to make that safe and explainable. First, there is **no per-tenant record of when a setting changed** — so a report showing a period where sales suddenly stopped blocking (or a negative balance that appeared and then stopped growing) has no way to say "the rule changed here." Second, the switch must be unambiguously **forward-only**: flipping to a stricter mode must not retroactively rewrite history or force reconciliation of balances that are already negative. This milestone adds a durable, dated audit trail for strategy changes and pins down the forward-only semantics.

## 2. Goals / Non-goals

**Goals**
- Persist a dated, per-tenant event every time `inventory_strategy` or `allow_overselling` changes (old value → new value, who, when).
- Guarantee switches are forward-only: historical movements untouched, balances at switch time carry over as-is.
- Make the events queryable so reports can annotate rule changes mid-history.

**Non-goals**
- The strategy setting itself and its enforcement (M3 defines the keys/policy; M4 enforces overselling). This milestone only records *changes* to those keys.
- Reconciling or "fixing" negative balances that exist at switch time — they carry over by design.
- A general-purpose settings-audit framework for all preferences (scope is the two inventory keys; the table is generic enough to extend later, but only these keys are wired now).
- Building report UI that consumes the events (optional note in §4; the negative-stock report is M7).

## 3. Current state (from audit)

- Preferences are written by `app/Actions/UpdatePreferences.php:11-27` via `Preference::updateOrCreate(['key'=>$key], ['value'=>$value])` then `Cache::forget('preferences')`. There is **no history capture** — the old value is overwritten in place with no trace.
- **There is no per-tenant settings audit trail.** `AdminAuditLog` (`app/Models/AdminAuditLog.php`) extends plain `Model`, has **no `tenant_id`**, and is written only via `app/Actions/Admin/LogAdminAction.php` for platform super-admin actions. It must **not** be reused for tenant setting history.
- M3 introduces the `inventory_strategy` (default `purchase_driven`) and `allow_overselling` preference keys and the `InventoryStrategy` policy layer. This milestone hooks the write path that changes them.
- Target-design semantics: switching applies **going forward only**; historical movements are untouched; negative balances existing at the time of a change (e.g. overselling turned off, or `free_form → purchase_driven`) **carry over as-is** — the stricter rules only prevent *new* negatives.

## 4. Design & behavior

Introduce a dedicated, tenant-scoped, append-only `tenant_settings_history` table and model. In `UpdatePreferences`, after computing each key's new value but before/around persisting it, compare against the current value; when the value actually changes **and** the key is one of the tracked inventory keys, insert a history row capturing `old_value`, `new_value`, `changed_by` (acting user), and `created_at`. The preference write itself is unchanged (still `updateOrCreate`), so enforcement and reads are unaffected — this milestone is purely additive observation.

Forward-only is a property of the surrounding system, reaffirmed here: no code in this milestone touches `stock_movements` or `stocks.quantity`. Turning on a stricter mode simply changes what M4's `deductStock` allows from that moment on; balances already negative remain negative until an ordinary compensating movement (late purchase, write-off, correction) raises them. The history row is the durable marker that lets M7 and the P&L reports explain a rule boundary in the timeline.

Optionally (noted, not required this milestone): reports may overlay these dated events as annotations so a reader can see "overselling disabled on 2026-08-01" against a balance chart.

## 5. Data model / schema changes

- **New table** `tenant_settings_history`: `id`, `tenant_id` (FK, indexed), `key` (string), `old_value` (text nullable), `new_value` (text nullable), `changed_by` (FK users, nullable), `created_at` (timestamp; no `updated_at` — rows are immutable). Index `['tenant_id', 'key']` for per-key history lookups.
- **New model** `App\Models\TenantSettingsHistory` extending `BaseModel` (so it inherits `TenantScope` + auto-`tenant_id`), append-only (guard on `updating`/`deleting`, no `SoftDeletes`), `belongsTo(User::class, 'changed_by')`.
- No existing table altered. Fully additive and reversible.

## 6. Task specs

### T6.1 — Tenant settings history table + model · **S**
- **Behavior:** create the `tenant_settings_history` table and `TenantSettingsHistory` model. Tenant-scoped via `BaseModel`; append-only; `changer()` relation to `User`.
- **Files:** *new* migration under `database/migrations/`; *new* `app/Models/TenantSettingsHistory.php`.
- **Edge cases:** `old_value` is null on the very first time a key is set (no prior value). Values are stored as text to match the `preferences.value` text column and to survive booleans/enums stringified consistently. Do not reuse `AdminAuditLog` (global, no `tenant_id`).
- **Acceptance criteria:** migration creates the table with `tenant_id` FK + `['tenant_id','key']` index; model auto-scopes to the current tenant, auto-fills `tenant_id`, and throws on update/delete; `down()` drops the table.
- **Test plan:** unit test — creating a row auto-fills `tenant_id`; a second tenant cannot read the first tenant's rows (scope); update/delete throw.

### T6.2 — Record strategy-switch events · **S**
- **Behavior:** in `UpdatePreferences`, for the tracked keys (`inventory_strategy`, `allow_overselling`), detect a real change (current value ≠ new value) and write a `TenantSettingsHistory` row (`key`, `old_value`, `new_value`, `changed_by = auth()->id()`) within the same operation. Non-tracked keys and no-op writes record nothing.
- **Files:** `app/Actions/UpdatePreferences.php:11-27`.
- **Edge cases:** writing the same value again (no change) must **not** create a row; setting a key for the first time records `old_value = null`; the switch must not trigger any stock reconciliation or balance mutation — verify no `stock_movements`/`stocks` write occurs; keep the existing `Cache::forget('preferences')` behavior (and align with M3.T3.3's tenant-qualified cache key if landed).
- **Acceptance criteria:** changing `inventory_strategy` from `purchase_driven` to `free_form` records one history row with correct old/new/actor/timestamp; flipping `allow_overselling` records one row; re-saving an unchanged value records none; a product with a negative balance at switch time still has that exact balance after the switch (no reconciliation).
- **Test plan:** feature test toggling each tracked key asserting exactly one history row with expected fields; a no-op save asserts zero rows; a regression test asserting an existing negative `stocks.quantity` is unchanged across a `free_form → purchase_driven` switch and no new `stock_movements` row is created.

## 7. Edge cases (cross-task)

- First-ever set of a key → `old_value` null (not an error).
- Idempotent saves (unchanged value) must never spam the history table.
- A switch to a stricter mode is *not* a data-repair operation: negative balances persist and are only resolved by ordinary compensating movements; the history row exists precisely so reports can explain those pre-existing negatives.
- Depends on M3 having introduced the two keys; if M3's `PreferenceRequest` whitelist is missing a key, that key never reaches `UpdatePreferences` and no event is recorded — covered by M3, called out here as a dependency.

## 8. Test plan (summary)

- Model scoping + immutability unit test (T6.1).
- Change-detection feature tests for both tracked keys, including no-op and first-set cases (T6.2).
- Forward-only regression: negative balance and ledger untouched across a strategy switch (T6.2).

## 9. Rollout & backwards compatibility

Fully additive and reversible — a new table plus an observation hook in `UpdatePreferences`. No behavior change to preferences, enforcement, or stock. Existing tenants have no history rows until they next change a tracked setting, which is correct (their standing default is `purchase_driven`, established in M3). Ship as one PR after M3.

## 10. Open questions

- Should we backfill a synthetic "initial `purchase_driven`" history row for every existing tenant at migration time, or leave history empty until the first real change? (Lean: leave empty; the M3 default already documents the starting state.)
- Do we generalize this to record **all** preference changes (not just the two inventory keys) now, or keep it inventory-scoped and widen later? (Lean: keep scoped; table shape already supports widening.)
- Should report annotations (overlaying switch events on balance/P&L charts) be part of M7, or a later enhancement? (Lean: later enhancement.)
