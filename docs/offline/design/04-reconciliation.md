# Design 04 — Reconciliation, Device Management & Pilot (PRD-04)

> Status: Design for review · Owner: reconciliation / device-management / offline pilot ·
> Implements: [PRD-04](../prds/prd-04-reconciliation-and-pilot.md) · Phase 3
>
> Depends on [Design 01](01-sync-foundations.md) (`public_id`, `change_log(seq)`,
> `tenant_sync_state`, `registers`/`devices`, `register_serials`, per-register drawer via
> `treasury_accounts.register_id`) and [Design 02](02-sync-protocol.md)
> (`oversell_reconciliations`, `credit_breach_flags`, `sync_logs`, device health columns,
> the push pipeline, `ReplayPosSaleAction`, Sanctum device tokens & abilities).
>
> **Scope & repo:** every change here lands in the **cloud repo** — reconciliation inbox,
> resolution actions, device fleet UI, super-admin fleet view, robustness policies, pilot
> telemetry. The **only** cross-repo artifact is the *client wipe contract* (§4.2), which is
> an input for the PRD-03 offline-client repo, and two small additions to the PRD-02 wire
> contract (§4.3, §5.3) flagged as inputs. This PRD builds on upstream decisions and does
> **not** reopen them except where §8 records a dispute.

---

## Decisions at a glance

| # | Area | Decision | PRD FR |
|---|---|---|---|
| R1 | Item model | **One polymorphic inbox table `reconciliation_items`** (tenant-scoped BaseModel) that owns lifecycle/audit, with a `subject` morph to four concrete detail tables. Two of those tables are upstream (`oversell_reconciliations`, `credit_breach_flags`); two are new (`session_variances`, `parked_mutations`). | FR-1 |
| R2 | Who writes items | The **push pipeline** raises the inbox row in the *same transaction* that creates the concrete subject (via `RaiseReconciliationItem`). Never derived by scanning. | FR-1, §5 tech ctx |
| R3 | Lifecycle | `status ∈ {open, resolved}`; a typed `resolution` enum records *how* (acknowledge / adjust / transfer / collect / …); `resolved_by` + `resolved_at` audit. No separate "dismissed" status — acknowledge is a resolution. | FR-1, FR-2..4 |
| R4 | Oversell resolution | `ResolveOversellAction` dispatches to the **existing** `RecordAdjustmentAction` (adjust / shrinkage) or `TransferStockAction` (transfer); it never touches stock directly. | FR-2 |
| R5 | Credit-breach resolution | `ResolveCreditBreachAction`: acknowledge / collect (existing `RecordPaymentAction`) / raise limit (existing customer edit). Auto-closes on collect if balance falls to ≤ limit. | FR-3 |
| R6 | Session variance | Compare **declared closing float vs the drawer's `currentBalance()` computed by `register_id`** (consistent with `ClosePosSessionAction`), captured *before* its reconciliation adjustment. Any non-zero variance on an offline-originated close raises a `session_variances` item. | FR-4 |
| R7 | Permissions | New slugs `reconciliation.view`, `reconciliation.resolve`, `devices.view`, `devices.manage` in `PermissionSeeder`; `owner`+`manager` inherit automatically via `DefaultRolesService`. Route `can:` middleware + a `ReconciliationItemPolicy`. | FR-5 |
| R8 | Notifications | **In-app = the inbox itself** (an open-item count badge in nav — there is no bell/DB-notifications infra today, and building one is unwarranted). **Email = a scheduled daily digest** to `reconciliation.view` holders, tenant-locale bound. Per-event mail is deliberately avoided (kills the "oversell storm" failure mode). | FR-5, Q2 |
| R9 | Device dashboard | Tenant Inertia page `Devices/Index` + `Devices/Show`, columns from `devices` + `sync_logs`, derived **health state** (healthy / stale / offline / skewed / revoked). | FR-6 |
| R10 | Revoke vs retire | Two distinct flows: **Revoke** (lost/stolen → immediate token kill, unsynced flagged as loss, client wipes on next contact) and **Retire/Replace** (planned → drain outbox, then swap device onto the same register; serial sequence continues because the counter lives on the register). | FR-7, FR-8 |
| R11 | Super-admin fleet | Read-only `/__admin` page per existing admin patterns (`inertia('Admin/…')`, `AdminLayout`, `auth:admin`+`EnsureSuperAdmin`), no write actions except the offline feature flag toggle (audit-logged via `LogAdminAction`). | FR-9 |
| R12 | Clock skew | Store **as-reported `occurred_at` + server-receipt time**; order accounting by `change_log.seq`; reports use business (device) time; `|skew| > 5 min` sets `devices.clock_skew_seconds` and raises a *device-health* warning (dashboard, not inbox). | FR-10 |
| R13 | Backpressure | **Change-log floor: keep ≥ 30 days regardless of cursors** and never prune above `min(active device cursor)`. A device whose cursor fell below the retained horizon gets **`409 cursor_expired`** and re-snapshots. Also re-snapshot when the pull backlog exceeds the tenant's live-row count (re-snapshot is then cheaper). | FR-12, Q3 |
| R14 | Telemetry | **Internal super-admin page + a `report-reconciliation` export**, computed from `sync_logs` / `change_log` / `reconciliation_items`. No new dashboard stack. | FR-15 |
| R15 | Feature flag | **Per-tenant `tenants.offline_enabled` boolean** (default `false`), toggled by super-admin, gating web device enrollment and `POST /provision`. Controlled pilot rollout + kill switch. | Q4 |

---

## 0. Ground truth found in the code (what shapes or contradicts PRD-04)

Verified against the codebase; each drives a decision below.

1. **There is no in-app notification surface and no `notifications` table.** Every
   `app/Notifications/*` class is `mail`-only (`via()` returns `['mail']`); localization is
   `(new X)->locale(app()->getLocale())` and dispatch is `$user->notify(...)`. There is no
   bell/dropdown in `resources/js`; in-app feedback is flash messages only. PRD-04 FR-5 and
   open question 2 assume an "in-app vs mail" choice — **there is no in-app channel to choose.**
   R8 resolves this by making the reconciliation inbox *be* the in-app surface (open-item
   count badge), rather than standing up Laravel's database-notifications channel + a bell.
2. **`ClosePosSessionAction` resolves the cash drawer by `sale_point_id`, not `register_id`.**
   `TreasuryAccount::where('sale_point_id', $session->storage_id)->ofType(Cash)->first()`.
   With per-register drawers (Design 01 §2.3), a single sale-point storage can host several
   registers' drawers, so this lookup is ambiguous for offline sessions. The replay path (§2.3)
   **must** resolve the drawer by `register_id`. Recorded in §8.
3. **`PosSession::expectedClosingFloat()` diverges from `ClosePosSessionAction`'s expected.**
   The model helper is `opening_float + cashSalesTotal()` (cash *invoices* only, ignoring
   drawer expenses/payouts). The action reconciles against `cashDrawer->currentBalance()`
   (all movements: opening float, sales, expenses, adjustments). FR-4 says "compare against
   synced payments … consistent with `ClosePosSessionAction`." We take the **drawer balance**
   (`currentBalance()`) as authoritative (R6) and treat the model helper as a display-only
   approximation. Recorded in §8.
4. **`Customer::scopeExceededCreditLimit()` does not actually compare balance to limit** — it
   only filters `credit_limit > 0`. The real breach signal comes from PRD-02's
   `credit_breach_flags` (computed at push time: `balance_after` vs cached `credit_limit`), so
   PRD-04 depends on the flag, not this scope. `credit_limit` is stored in minor units
   (`MoneyCast`); `credit_breach_flags.credit_limit`/`balance_after` are `bigInteger` minor
   units — invariant holds.
5. **Money is integer minor units end-to-end** (`Money` VO + `MoneyCast`); all treasury
   movement amounts are signed integers in minor units. Every amount in this doc is minor units.
6. **Admin audit is `LogAdminAction::handle($adminUserId, $action, ?Model $target, ?array
   $metadata)`** writing `admin_audit_logs` (polymorphic `target`). The super-admin feature-flag
   toggle (R15) uses it. Tenant-side reconciliation resolution audit lives *on the item*
   (`resolved_by`/`resolved_at`/`resolution`), matching how the tenant app records actor context.
7. **Export engine is a one-line registry extension** (`ExportRegistry::exports()` →
   `report-reconciliation`), backed by an `Export` class + a `Queries/Reports` class; the
   queued `GenerateExportJob` rebinds tenant + locale already. R14 reuses it verbatim.

None of these reopen a settled roadmap decision; they change how FR-1/FR-4/FR-5 are implemented.

---

## 1. Reconciliation item model (FR-1)

### 1.1 Decision: one polymorphic inbox over concrete detail tables

Upstream already committed two concrete tables (`oversell_reconciliations`,
`credit_breach_flags`), each with type-specific columns. We add a **single unifying inbox
table** that carries everything the inbox needs uniformly — listing, filtering, status,
audit, permission, notification — and a `subject` morph to the concrete detail row:

```php
Schema::create('reconciliation_items', function (Blueprint $table) {
    $table->id();
    $table->char('public_id', 26)->unique();
    $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();

    // Denormalized type for cheap listing/filtering without touching each subject table.
    $table->string('type', 24);                    // ReconciliationType enum value
    $table->morphs('subject');                      // subject_type / subject_id → detail row

    // The "who / which" (device time and sync origin), all nullable for robustness.
    $table->foreignId('device_id')->nullable()->constrained('devices');
    $table->foreignId('register_id')->nullable()->constrained('registers');
    $table->foreignId('actor_user_id')->nullable()->constrained('users'); // offline cashier

    // The "when": device/business time vs server sync time (FR-1, FR-10).
    $table->timestamp('occurred_at');               // as reported by the device (business time)
    $table->timestamp('detected_at');               // server receipt time (push landed)

    // Lifecycle + resolution audit (R3).
    $table->string('status', 12)->default('open');  // open | resolved
    $table->string('resolution', 24)->nullable();   // ResolutionKind enum (per-type set)
    $table->text('resolution_note')->nullable();
    $table->foreignId('resolved_by')->nullable()->constrained('users');
    $table->timestamp('resolved_at')->nullable();

    $table->timestamps();

    $table->index(['tenant_id', 'status', 'type']); // inbox: open items by type
    $table->index(['tenant_id', 'device_id']);      // device drill-down
});
```

`ReconciliationItem extends BaseModel` (tenant-scoped, unguarded, `HasPublicId`,
`RecordsChanges`). It is **cloud-only** — not pulled to devices in MVP (like its subjects).

The four subject types (`App\Enums\ReconciliationType`, TitleCase keys):

| `type` | Subject table | Origin |
|---|---|---|
| `Oversell` | `oversell_reconciliations` (Design 02 §6.1) | `sale.create` force-deduct |
| `CreditBreach` | `credit_breach_flags` (Design 02 §6.2) | credit `sale.create` |
| `SessionVariance` | `session_variances` (new, §1.2) | `pos_session.close` |
| `ParkedMutation` | `parked_mutations` (new, §1.2) | terminally-rejected push mutation |

**Why polymorphic inbox, not per-type UNION or a pure scan:**

- *Rejected — per-type tables unioned at query time.* A `UNION` across four differently-shaped
  tables for every inbox page (with paging, status filter, sort by `occurred_at`, per-device
  drill-down, an open-count badge on every request) is fragile and slow, and every new item
  type edits the query. One indexed table gives O(1) "open items" and a single notification/
  permission surface.
- *Rejected — one fully-polymorphic table with an inline JSON payload (no concrete tables).*
  Would duplicate/relocate the upstream `oversell_reconciliations`/`credit_breach_flags`
  schemas that Design 02's push pipeline already writes, forcing a reopen of upstream. Keeping
  concrete detail tables honors upstream and keeps type-specific FKs (product, invoice,
  customer) real and query-joinable.
- *Rejected — deriving the inbox by scanning for negative stock / over-limit balances.*
  Explicitly forbidden by PRD-04 §5 ("first-class model … not derived by scanning"). A scan
  can't attribute device/actor/occurred_at, misses already-corrected divergences, and can't
  carry resolution audit. The push pipeline has all the context at write time (R2).

### 1.2 Two new concrete subject tables

**`session_variances` (FR-4).** One row per offline session+drawer whose declared close
disagreed with the expected drawer balance.

```php
Schema::create('session_variances', function (Blueprint $table) {
    $table->id();
    $table->char('public_id', 26)->unique();
    $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
    $table->foreignId('device_id')->nullable()->constrained('devices');
    $table->foreignId('register_id')->constrained('registers');
    $table->foreignId('pos_session_id')->constrained('pos_sessions');
    $table->foreignId('treasury_account_id')->constrained('treasury_accounts'); // the drawer
    $table->bigInteger('expected_amount');    // drawer currentBalance() before adjustment (minor units)
    $table->bigInteger('declared_amount');    // closing_float the cashier counted (minor units)
    $table->bigInteger('variance_amount');    // declared - expected (signed minor units)
    $table->foreignId('adjustment_movement_id')->nullable()->constrained('treasury_movements');
    $table->timestamp('occurred_at');         // device close time
    $table->timestamps();
});
```

**`parked_mutations`.** A push mutation the server **terminally** rejected (non-retriable
`RejectionReason` — e.g. `ValidationFailed`, `SessionClosed`, `TenantMismatch`). Retriable
rejections (`UnknownReference`) are **not** parked — the device re-pushes after the missing
mutation lands (Design 02 §5.2). Parking stores the raw envelope so the owner/support can
inspect, and so a fix-and-replay or discard is possible.

```php
Schema::create('parked_mutations', function (Blueprint $table) {
    $table->id();
    $table->char('public_id', 26)->unique();
    $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
    $table->foreignId('device_id')->nullable()->constrained('devices');
    $table->string('mutation_type', 40);       // sale.create, expense.create, ...
    $table->string('idempotency_key');
    $table->string('rejection_reason', 32);    // RejectionReason enum value
    $table->text('rejection_message')->nullable();
    $table->json('envelope');                  // the full Mutation DTO as received (audit/replay)
    $table->timestamp('occurred_at');          // mutation.occurred_at (device time)
    $table->timestamps();
    $table->unique(['tenant_id', 'idempotency_key']); // one parked row per mutation
});
```

### 1.3 Written by the push pipeline (R2)

A single tenant-scoped action, `RaiseReconciliationItem`, is called by the push handlers
*inside the per-mutation transaction*, right after the concrete subject is created:

```php
$item = $this->raiseReconciliationItem->for(
    subject: $oversellRow,                // any of the four subject models
    type: ReconciliationType::Oversell,
    device: $device,
    register: $register,
    actor: $actorUser,
    occurredAt: $mutation->occurredAt,    // business time from the envelope
);   // detected_at = now(); status = open
```

- **Oversell / credit breach:** Design 02 §6 already creates the subject inside
  `ReplayPosSaleAction`; PRD-04 extends those two creation sites to also call
  `RaiseReconciliationItem` (same transaction → the inbox row commits atomically with the
  sale, so it can never be missed and never double-counted on replay — the idempotency gate
  makes the whole mutation a no-op on re-push).
- **Session variance:** raised by the `pos_session.close` handler (§2.3).
- **Parked mutation:** raised by the push loop when a mutation resolves to a *terminal*
  `Rejected` outcome (its own tiny transaction; the rejected business mutation itself wrote
  nothing).

Because items are change-logged (`RecordsChanges`), *the tenant's own web app* sees new items
immediately; no polling of raw tables.

---

## 2. Resolution flows (FR-2..4)

All three flows share the inbox shell: a permission-gated `Reconciliation/Index.vue` list
(filter by type/status/device, badge = open count) and a `Reconciliation/Show.vue` detail with
the linked records and a resolution panel. UI follows the design system (cards, status pills,
`__()` strings, RTL). Resolution posts to a type-specific action; each action **wraps the
existing primitive**, then flips the item to `resolved` with `resolution`, `resolution_note`,
`resolved_by = actor`, `resolved_at = now()` in one transaction, and mirrors `resolved_at/
resolved_by` onto the subject where the upstream schema has those columns (oversell, credit
breach — see §8).

### 2.1 Oversell resolution (FR-2)

**Item shows:** product, storage, `oversold_qty`, `on_hand_before`, **current on-hand** (live,
typically negative), the offending invoice (serial + link), device/register/cashier, device
time + sync time.

**Owner picks one resolution** (`ResolutionKind`):

| Resolution | Existing primitive | Effect |
|---|---|---|
| `Adjust` | `RecordAdjustmentAction($storage, $product, $countedQty, type: 'adjustment', $actor, notes)` | Owner does a physical count; sets true on-hand. Corrects the negative through the audited stock ledger. |
| `Transfer` | `TransferStockAction($stockTransfer, $actor)` | Bring units from another storage to cover the shortfall; creates a real `stock_transfer` + movements. |
| `Shrinkage` | `RecordAdjustmentAction(..., type: 'shrinkage', ...)` | Accept the loss; write the missing units off (e.g. set to `0`) as shrinkage. |

`ResolveOversellAction` never mutates `stocks` directly — it calls the Action, which produces
`stock_movements` and its own `change_log` entries (so other devices see the corrected level on
pull). Then it marks the item resolved. **Acceptance (PRD-04 §6):** a two-register oversell
produces exactly one item; resolving via any path leaves stock, movements, and the item's audit
trail consistent — guaranteed because on-hand is authoritative-stored and every correction is a
ledgered Action.

### 2.2 Credit-breach resolution (FR-3)

**Item shows:** the customer account (+ current balance vs `credit_limit`), the offending
invoice(s), device/cashier, times.

| Resolution | Existing primitive | Effect |
|---|---|---|
| `Acknowledge` | — | Owner accepts the breach (e.g. trusted customer). No financial change; item resolved with note. |
| `Collect` | `RecordPaymentAction($invoice, $customer, $amount, $method, PaymentDirection::In, ...)` | Records a payment; treasury movement + balance drop through the existing flow. If the recomputed balance ≤ `credit_limit`, auto-set `resolution = Collect` and close; otherwise leave open with the payment noted. |
| `RaiseLimit` | existing customer update (`credit_limit`, minor units) | Owner lifts the limit to match reality; item resolved. |

`ResolveCreditBreachAction` orchestrates and records the resolution; the money always flows
through `RecordPaymentAction`/customer edit (never a bespoke write). Balance is read via the
existing `account_balance` attribute (§0.4).

### 2.3 Offline session cash variance (FR-4)

**The comparison (R6).** For a pushed `pos_session.close`, the handler (a thin
`ReplayCloseSessionAction` wrapping the existing `ClosePosSessionAction` semantics):

1. Resolves the drawer **by `register_id`** (the register from the session/mutation), *not* by
   `sale_point_id` — see §0.2/§8. This is the per-register drawer from Design 01 §2.3.
2. Captures `expected = drawer.currentBalance()` **before** any reconciliation adjustment
   (identical definition to today's `ClosePosSessionAction`: opening float + all movements).
3. Runs the close: sets `closing_float = declared`, records the reconciliation treasury
   `ManualAdjustment` for `declared - expected` (exactly as `ClosePosSessionAction` does today,
   so the drawer ends balanced and the money invariant holds).
4. If `declared !== expected`, writes a `session_variances` row (`expected_amount`,
   `declared_amount`, `variance_amount`, `adjustment_movement_id`) **and** raises a
   `SessionVariance` inbox item.

So the cash flow is **unchanged** from cloud sessions — the variance is still absorbed into a
drawer adjustment — but offline closes additionally *surface* the variance to a human (today's
cloud behavior silently absorbs it). This is additive and consistent with
`ClosePosSessionAction`.

**Scope decision:** variance items are raised only for **offline-originated** closes
(`device_id` present). Cloud `R0` sessions keep today's silent-absorb behavior so web UX is
unchanged. (A tenant preference could later opt cloud sessions in; out of scope.)

**Resolution:**

| Resolution | Primitive | Effect |
|---|---|---|
| `Acknowledge` | — | Accept the counted amount (the adjustment already balanced the drawer). Default. |
| `AdjustDrawer` | `RecordTreasuryAdjustmentAction($drawer, $newBalance, $note, $actor)` | If the count itself was wrong, re-correct the drawer to the true amount through the existing adjustment action. |

---

## 3. Permissions & notifications (FR-5)

### 3.1 Permissions (via the existing seeding path)

Add two groups to `PermissionSeeder::permissions()` (slugs follow `{group}.{action}`):

```php
'reconciliation' => [
    'reconciliation.view'    => 'View reconciliation items',
    'reconciliation.resolve' => 'Resolve reconciliation items',
],
'devices' => [
    'devices.view'   => 'View device fleet',
    'devices.manage' => 'Provision, revoke and replace devices', // referenced by PRD-02 provisioning
],
```

- `devices.manage` is the permission PRD-02 already assumed for web enrollment — it is
  formally introduced here.
- **Default assignment is automatic:** `DefaultRolesService::rolePermissions()` derives
  `owner` (all slugs) and `manager` (all except `users.assign-role`, `roles.manage`) from the
  master list, so both inherit all four new slugs with **zero edits**. `cashier`/`staff` are
  explicit allow-lists and are intentionally *not* granted reconciliation/device permissions
  (resolving divergences and managing the fleet is an owner/manager job). Re-running
  `seedForTenant()` (idempotent `sync()`) rolls the new slugs out to existing tenants.

**Enforcement.** Route middleware `can:reconciliation.view` / `can:reconciliation.resolve` /
`can:devices.view` / `can:devices.manage` (resolved by the `Gate::after` slug fallback), plus a
`ReconciliationItemPolicy` (`viewAny`/`resolve`) for controller `authorize()` calls, matching
the app's dual style. Owner short-circuits via `Gate::before`. The frontend gates UI with the
existing `usePermissions().can('reconciliation.resolve')` composable.

### 3.2 Notifications (open question 2)

**Channels available today: mail only** (§0.1). Decision:

- **In-app = the reconciliation inbox.** A permission-gated **open-item count badge** in the
  app nav (like other count badges) is the real-time in-app signal; the inbox list is the
  "notification center." We deliberately do **not** stand up Laravel's `database` notifications
  channel + a bell dropdown — the inbox already is that surface, and adding parallel infra is
  YAGNI.
- **Email = a scheduled daily digest**, not per-event. A per-event email is exactly wrong for
  the **oversell-storm** failure mode (FR-16): one bad afternoon across two registers could mint
  dozens of oversell items and flood inboxes. A once-daily digest to `reconciliation.view`
  holders summarizing *open* items by type (counts + the few most severe) gives push-awareness
  without the storm. Implemented as an Artisan command scheduled `->daily()`, mirroring
  `cheques:notify-for-due`: iterate tenants, bind tenant + set locale from the tenant
  `Preference` (so the digest is per-tenant-localized despite no `HasLocalePreference`), collect
  open items, `->notify((new ReconciliationDigestNotification($summary))->locale($tenantLocale))`
  to each holder. Device-health warnings (skew/offline) ride the **same** digest section.

**Matrix:**

| Event | In-app (inbox badge) | Email |
|---|---|---|
| New oversell item | ✅ immediate | digest (daily) |
| New credit-breach item | ✅ immediate | digest (daily) |
| New session-variance item | ✅ immediate | digest (daily) |
| New parked-mutation item | ✅ immediate | digest (daily) |
| Device revoked / offline > threshold / clock-skew warning | device dashboard badge | digest (daily) |

All notification strings and inbox/dashboard UI are localized en/ar via `__()` (backend) and
the Vue `__()` helper (frontend), RTL-verified. *Rejected — per-event mail:* storm-prone.
*Rejected — building a DB-notifications bell:* new infra for no gain over the inbox badge.

---

## 4. Device fleet management (FR-6..9)

### 4.1 Tenant device dashboard (FR-6)

Inertia pages `Devices/Index.vue` (fleet table) and `Devices/Show.vue` (one device + its
`sync_logs` timeline), gated by `can:devices.view`; management actions gated by
`can:devices.manage`. Controller follows the standard tenant list pattern (`->when()` filters,
`->paginate()->withQueryString()`, `inertia('Devices/Index', …)`).

**Columns** (from `devices` + latest `sync_logs`, all already populated by PRD-02 §8):

| Column | Source |
|---|---|
| Register / label | `registers.code` + `label` |
| Device name | `devices.name` |
| Status | `devices.status` (pending/active/revoked) badge |
| Health | **derived** (below) |
| Last seen | `devices.last_seen_at` |
| Last push / pull | `devices.last_push_at` / `last_pull_at` |
| Pending outbox age | `now() − devices.oldest_pending_at` (device-reported, §8.2 of D02) |
| Pending count | `devices.pending_count` |
| App / protocol version | `devices.app_version` / from `sync_logs`/heartbeat |
| Clock skew | `devices.clock_skew_seconds` (§5.1) |

**Derived health state** (a computed accessor, shown as a status pill per the design system):

- `revoked` — `status = revoked`.
- `skewed` — `|clock_skew_seconds| > 300`.
- `offline` — `last_seen_at` older than *N* minutes (config, default 30) while `active`.
- `stale` — active + seen recently but `pending_count > 0` and `oldest_pending_at` older than
  *M* minutes (default 15): outbox not draining.
- `healthy` — active, seen recently, outbox empty/fresh.

### 4.2 Revoke flow (FR-7) — end to end

`RevokeDeviceAction` (gated `devices.manage`):

1. `device.update(['status' => Revoked, 'revoked_at' => now()])`.
2. **Immediate token invalidation:** delete the device's Sanctum tokens
   (`$device->tokens()->delete()`). PRD-02's `EnsureDeviceActive` middleware then 401s any
   in-flight sync call; combined with the deleted token, API access dies at once (PRD-02 FR-2).
3. **Flag potential unsynced loss:** snapshot the last device-reported `pending_count` into
   `devices.revoked_unsynced_count` (new nullable column) so the dashboard shows "≈ N items may
   be lost." The cloud cannot know the true outbox depth after revocation — this is the last
   known value (honest, labeled approximate).
4. Audit: the resolution/action is recorded on the device timeline; a super-admin-visible event.

**Client wipe contract (the single cross-repo artifact — input to PRD-03).** So the offline
client can distinguish *revocation* (wipe) from a *transient* auth failure (retry), the sync
API responds to a **revoked** device with a first-class, unambiguous status:

> Any `/api/sync/v1/*` call from a revoked device → **`403 { "error": "device_revoked" }`**
> (distinct from `401` token-missing/expired, which the client treats as "re-auth / retry").

On `device_revoked` the client (PRD-03 FR-20): stops syncing, surfaces a localized
"this device was deactivated; *N* unsynced items cannot be recovered" screen (N = local outbox
count), requires confirmation, then **wipes the local SQLite** (no DB encryption exists —
Design 03 §4.3 / D2; the unsynced outbox is first exported to the encrypted support file per
Design 03 §7.3). Because the token is
already dead, the client cannot push the residual outbox — this is *why* revoke is reserved for
lost/stolen devices and why the planned path is Retire/Replace (§4.3). This contract is the only
thing PRD-04 hands to the client repo.

### 4.3 Replace-register flow (FR-8) — serial continuity

`ReplaceDeviceAction` (gated `devices.manage`) — the **planned** device swap, distinct from
revoke:

1. **Drain first.** Refuse to proceed (or warn hard) unless the outgoing device's last-reported
   `pending_count = 0`. The store is instructed to let the old device finish syncing (this is
   also the FR-13 rollback primitive: "unsynced work pushed before revert").
2. Retire the old device: `status = revoked`, tokens deleted (now safe — outbox empty).
3. Provision a successor **onto the same `register_id`** (new `Device` row, `status = pending`,
   fresh pairing code), reusing PRD-02 provisioning.
4. **Serial sequence continues automatically:** `register_serials` is keyed
   `(tenant_id, register_id, series, year)` (Design 01 §3.2) — it belongs to the *register*, not
   the device. The successor binds the same register, so the counter simply continues; no reset,
   no gap.

**One coordination point for PRD-02/03 (flagged as input, §9).** Devices *mint serials locally*
(Design 01 §3, Design 02 §5.4). A **replacement** device joining a register that already has
sales must start **above** the register's current `last_seq`, or it will collide. Therefore the
**snapshot must include the register's `register_serials` rows** (current `last_seq` per series/
year) so the successor resumes the sequence. This is a small addition to Design 02's snapshot
entity set (§2.2 there lists the register but not its serial counters). For a brand-new register
`R{n}` the counters are absent → the device starts at `1`, unchanged.

### 4.4 Super-admin fleet view (FR-9)

Read-only, in the existing `/__admin` patterns (§0.6): route under the `__admin` group in
`routes/web.php` guarded by `auth:admin` + `EnsureSuperAdmin`, controller
`Admin/DeviceFleetController@index/show` returning `inertia('Admin/DeviceFleet/Index', …)` with
`AdminLayout`, a hand-rolled table, `->when()` filters + `->paginate()->withQueryString()`, and
a nav entry in `AdminLayout.vue`'s sidebar. It shows, per tenant: device count, active/revoked
split, last sync time, aggregate open-reconciliation-item count, and each device's health
(reusing the §4.1 derivation). **No tenant-data writes** — the only mutating admin control on
this surface is the offline feature flag toggle (§6.5), which is `LogAdminAction`-audited.

---

## 5. Sync robustness (FR-10..12)

### 5.1 Clock-skew policy (FR-10)

- **Record both times.** Every synced record and every `reconciliation_item` stores
  `occurred_at` (device-reported business time, from the mutation envelope) and a server-receipt
  time (`detected_at` / row `created_at`). Nothing overwrites the device time.
- **Order accounting by `change_log.seq`** (server sequence), never by device time — the
  monotonic per-tenant cursor (Design 01 §4.3) is the authoritative order for money/stock.
- **Reports use business (device) time** (`occurred_at`) so a sale reads on the day it happened
  in the store, regardless of skew.
- **Skew measurement + threshold.** Each request sends `X-Client-Time` (already natural
  alongside PRD-02's version headers); the server stores `devices.clock_skew_seconds =
  server_now − client_time` on push/heartbeat (a coarse estimate — round-trip is unobservable
  server-side, so the threshold is generous). `|skew| > 300s (5 min)` → device health `skewed`
  (§4.1) + inclusion in the daily digest (§3.2). Five minutes tolerates ordinary unsynced clocks
  while catching a wrong date/timezone that would misfile reports.

### 5.2 Flaky-network verification (FR-11)

The protocol is already resumable by construction: snapshot download supports HTTP `Range`
(Design 02 §2.3) and pull is cursor-based and idempotent (re-pull from an older cursor re-sends
the same latest state). PRD-04's job is to **verify** it under fault injection, not add
mechanism:

- **Pest feature tests with a fault-injecting transport:** (a) truncate the snapshot download at
  a random byte, resume via `Range`, assert the reassembled archive hashes equal the whole; (b)
  interrupt a paged pull mid-stream, resume from `next_cursor`, assert no gap and no double-apply
  (apply the same page twice → identical local state, proving idempotency); (c) drop the response
  after the server committed a push but before the client saw results → client re-pushes → the
  idempotency gate returns `already_applied` with zero new writes / zero new `change_log` rows.
- These map directly to PRD-04 §6 acceptance (resumable snapshot / partial pull / re-push
  no-dup). No Dusk needed — the transport is injectable at the client-core boundary (Design 01
  §8).

### 5.3 Backpressure & retention vs re-snapshot (FR-12, open question 3)

Design 01 §4.4 compacts the change log bounded by `min(active device cursor)`. PRD-04 pins the
concrete window and the re-snapshot trigger, coordinating with that design:

**Retention window — keep ≥ 30 days, and never prune above the min active device cursor.**
The daily compaction job collapses superseded `(table, public_id)` entries only when they are
both **older than 30 days** *and* **below `min(devices.last_acked_seq)`** among active devices.
Rationale: 30 days covers realistic offline gaps (a device down for a long weekend, a seasonal
closure, a shipped-and-reconnected register) so those devices always resume by cheap incremental
pull; the min-cursor guard means a still-lagging device never loses entries it hasn't seen.

**Re-snapshot trigger — `409 cursor_expired`.** If a device pulls with a `cursor` **below the
oldest retained `seq`** for its tenant (its needed entries were pruned — i.e. it was offline past
the horizon), `GET /pull` returns:

> **`409 { "error": "cursor_expired", "min_cursor": <oldest_retained_seq> }`**

The client treats this as terminal-for-incremental: it re-runs the snapshot bootstrap (Design 02
§2), discarding its synced (never its unsynced) local data, then continues pulling from the new
manifest cursor. **Second trigger — backlog cheaper than state:** even within 30 days, if a
device's pending pull count (`tenant_next_seq − cursor` after scope filtering) **exceeds the
tenant's live syncable row count**, the server may answer `cursor_expired` because a fresh
snapshot (O(current state)) is smaller than replaying the backlog. This bounds a
weeks-offline device to one snapshot instead of an "infinite pull" (PRD-04 §6 acceptance).

*Rejected — unbounded change-log retention:* the log grows without limit and pull backlog for a
long-absent device becomes pathological. *Rejected — no 30-day floor (prune purely by min
cursor):* a single lagging/parked device would pin the log forever; the 30-day floor + the
re-snapshot escape hatch de-risks that.

---

## 6. Pilot (FR-13..16)

### 6.1 Pilot plan skeleton (FR-13)

- **Store selection criteria:** exactly one real store; **≥ 2 registers** actually operated
  concurrently (to exercise oversell/multi-drawer from v1); genuine intermittent connectivity
  (a store that *needs* offline, so the feature is truly tested); an engaged owner willing to be
  a design partner and resolve reconciliation items promptly; clean-enough stock/customer data;
  moderate daily volume (enough sales to matter, few enough to hand-audit). Gated by the
  per-tenant flag (§6.5).
- **Duration:** 4 weeks. Week 0 = supervised dry-run (provision 2 devices, a half-day of test
  sales, a forced offline window, verify sync + one deliberate oversell reconciled end-to-end).
  Weeks 1–4 = live, daily check-ins in week 1, then twice-weekly.
- **Rollback story:** the store can revert to cloud-web POS at any time **without data loss.**
  Procedure: (1) bring each device online and let the outbox drain to `pending_count = 0`
  (visible on the dashboard); (2) Retire each device via §4.3 (drain-first); (3) resume selling
  on cloud-web against `R0`. Everything synced stays; unsynced work is pushed *before* revert.
  If a device is unrecoverable, Revoke it (§4.2) and accept the flagged unsynced loss — the
  reason revoke and retire are separate flows.

### 6.2 SLOs + exact measurement (FR-14)

All computed from `sync_logs`, `change_log`, `sync_idempotency`, and `reconciliation_items`. Two
small telemetry fields are added to make (a) and (d) measurable (flagged inputs, §9): the push
envelope carries `client_pushed_at` (when the worker began the push, ≈ connectivity-return since
the worker fires on reconnect), and `heartbeat` carries client-reported `crash_count` /
`session_count`.

| SLO | Definition | Measurement |
|---|---|---|
| **(a) Sale latency < 1 min p95** | time from connectivity-return to cloud-landing | For each pushed `sale.create`: `latency = sync_logs.created_at − envelope.client_pushed_at`. p95 over the pilot. (`client_pushed_at` ≈ reconnect because the worker pushes immediately on reconnect.) |
| **(b) Zero lost / duplicated sales** | every device sale lands exactly once | *Dup:* count invoices per `idempotency_key` in `sync_idempotency` where `mutation_type='sale.create'` — must be 1 (guaranteed by the gate; assert = 0 duplicates). *Lost:* device-reported total sales (heartbeat counter) − `applied` sale mutations for that device = 0. |
| **(c) Reconciliation items resolved < 48h** | responsiveness of the human loop | p95 of `resolved_at − detected_at` over `reconciliation_items` in the pilot window. |
| **(d) Crash-free sessions ≥ target** | desktop stability | `1 − Σ crash_count / Σ session_count` from heartbeats (client-reported; the cloud can't observe desktop crashes directly). |

### 6.3 Telemetry approach (FR-15) — internal report, not a dashboard

Decision: a **read-only super-admin page** (`Admin/PilotHealth`) that runs the §6.2 queries for
a selected tenant/date-range, plus the **`report-reconciliation` export** for item-level detail
(open/resolved, type, device, `occurred_at`→`resolved_at`). Registering the export is one line
in `ExportRegistry::exports()` (`'report-reconciliation' => ReconciliationReportExport::class`)
backed by a `Queries/Reports/ReconciliationReportQuery`; the queued `GenerateExportJob` already
binds tenant + locale. No new dashboard/metrics stack — the pilot is one store; SLOs are computed
on demand from the audit trail that PRD-02 §8 already records. *Rejected — a live metrics
dashboard (charts/streaming):* disproportionate for a single-store, 4-week pilot.

### 6.4 Support playbook outline (FR-16)

Written for support staff (plain language, no code), one page per failure mode:

- **Device lost/stolen.** Symptoms → open Device fleet → **Revoke** the device (§4.2); explain
  the "N items may be lost" flag and that the device wipes itself on next launch; if a
  replacement is coming, use **Replace** instead once the old one is recovered/drained.
- **Oversell storm.** Symptoms (many oversell items after a busy offline stretch) → reassure
  (sales are safe, nothing was blocked) → resolve items in bulk order: physical count →
  **Adjust**, or **Transfer** to cover, or **Shrinkage** to write off; watch the digest, not
  per-item mail.
- **Stuck outbox.** Symptoms (dashboard health `stale`, pending age climbing) → check
  connectivity → check `upgrade_required` (§below) → check for **parked mutations** (inbox) and
  their reason → escalate to engineering only if the outbox won't drain with connectivity.
- **Upgrade-required deadlock.** Symptoms (`426 upgrade_required`, device still sells offline but
  won't sync) → confirm the desktop app auto-update ran / push the new build → the store keeps
  selling offline meanwhile; once updated, the outbox drains normally.

### 6.5 Feature flag (open question 4)

Decision: a **per-tenant `tenants.offline_enabled` boolean** (migration, default `false`).
There is no existing feature-flag mechanism (§0), and this mirrors the existing `tenants.is_active`
pattern — minimal and obvious.

- **Gates:** web device enrollment UI + the `devices.manage` enrollment endpoint check it, and
  `POST /api/sync/v1/provision` refuses (`403 offline_disabled`) when the tenant flag is off. An
  already-provisioned device keeps working if the flag is later turned off (don't strand a live
  pilot mid-day); turning it off only blocks *new* enrollment. Revoke is the tool to actually
  stop a device.
- **Control:** super-admin toggles it on the tenant admin page; the toggle is audited via
  `LogAdminAction` (`action: 'tenant.offline_enabled' | 'tenant.offline_disabled'`, target =
  tenant). This gives the team a controlled rollout + kill switch during the pilot.

---

## 7. Answers to PRD-04 open questions (§7)

1. **Item taxonomy — polymorphic vs per-type.** **One polymorphic inbox
   (`reconciliation_items`)** owning lifecycle/audit, with a `subject` morph to four concrete
   detail tables (two upstream, two new). Not per-type UNION, not a derived scan, not an inline
   JSON blob replacing the upstream tables. (§1.)
2. **Who is notified, by which channel.** All holders of **`reconciliation.view`** (owner +
   manager by default), via **in-app inbox badge (real-time) + a daily email digest**; no
   per-event mail (oversell-storm safe); no new bell/DB-notifications infra (the inbox is the
   in-app surface). (§3.2.)
3. **Change-log retention vs re-snapshot threshold.** **Keep ≥ 30 days and never prune above the
   min active device cursor**; a device below the retained horizon (or whose backlog exceeds the
   tenant's live-row count) gets **`409 cursor_expired`** and re-snapshots. (§5.3.)
4. **Pilot selection + feature flag.** One store, ≥ 2 concurrently-run registers, real
   intermittent connectivity, engaged owner, clean data; **yes — a per-tenant
   `tenants.offline_enabled` flag** gates provisioning during the pilot, super-admin controlled,
   audit-logged. (§6.1, §6.5.)

---

## 8. Disputes / corrections with upstream design

Not reopening settled decisions; these are consistency notes the reviewer should ratify.

1. **`ClosePosSessionAction` drawer lookup is `sale_point_id`-scoped, not `register_id`-scoped.**
   With per-register drawers (Design 01 §2.3), an offline session close **must** resolve the
   drawer by `register_id`, else two registers at one sale point share a drawer and their
   variances/adjustments cross-contaminate. PRD-04's `ReplayCloseSessionAction` resolves by
   `register_id`; we do **not** alter the cloud web action (R0 has one drawer, unaffected). Ask:
   confirm the offline-path drawer resolution switch (Design 01 §2.3 said "lands with PRD-02")
   also covers session close, not just checkout.
2. **`PosSession::expectedClosingFloat()` ≠ the drawer balance.** The model helper counts cash
   invoices only; the authoritative expected (and what `ClosePosSessionAction` reconciles
   against) is `drawer.currentBalance()`, which also reflects expenses/payouts. FR-4's
   "compare against synced payments" is implemented against the **drawer balance** (R6) for
   consistency with the action; the model helper stays a display approximation. Recommend not
   using `expectedClosingFloat()` for the variance figure.
3. **`oversell_reconciliations` / `credit_breach_flags` carry `resolved_at` / `resolved_by`
   that now duplicate the inbox lifecycle.** The `reconciliation_items` row is the **single
   source of truth** for status/resolution. To honor the upstream schema without divergence, the
   resolution actions **mirror** `resolved_at`/`resolved_by` onto those subject rows in the same
   transaction. Recommendation (non-blocking): treat those two columns as a denormalized mirror,
   or drop them in a later migration since the inbox owns lifecycle. `session_variances` /
   `parked_mutations` intentionally carry **no** resolution columns (inbox-only).
4. **`Customer::scopeExceededCreditLimit()` doesn't compare balance to limit** (§0.4). PRD-04
   relies on PRD-02's `credit_breach_flags` (computed at push), not this scope. No change forced,
   but the scope is misleading and should not be used for breach detection.

---

## 9. Inputs for other design docs

- **PRD-02 (protocol) — two small additive contract items, both backward-compatible within
  `/v1`:**
  1. **Snapshot must include the register's `register_serials` rows** so a *replacement* device
     resumes the local serial sequence without collision (§4.3). New registers have none → start
     at 1.
  2. **`409 cursor_expired { min_cursor }`** as a first-class pull status for the re-snapshot
     path (§5.3); and a **`403 device_revoked`** status distinct from `401` for revoked devices
     (§4.2); and **`403 offline_disabled`** from `/provision` when `tenants.offline_enabled` is
     false (§6.5). Telemetry fields: push envelope `client_pushed_at`, heartbeat `crash_count` /
     `session_count` (§6.2).
- **PRD-03 (offline client) — the client wipe contract (the one cross-repo artifact):** on
  `403 device_revoked`, stop syncing, warn "N unsynced items unrecoverable," confirm, wipe the
  local SQLite (PRD-03 FR-20; unsynced outbox exported first per Design 03 §7.3). On
  `409 cursor_expired`, discard synced local data
  (never unsynced) and re-bootstrap from a fresh snapshot. The successor device seeds its local
  serial counter from the snapshot's `register_serials` rows. Retire/Replace expects the client
  to drain the outbox to `pending_count = 0` before the swap.
- **PRD-04 → cloud app:** the daily digest command binds tenant + locale from `Preference`
  (there is no `HasLocalePreference`); the reconciliation export is one `ExportRegistry` line.

---

## 10. Implementation notes (suggested PR slicing)

All PRs land in the **cloud repo** except where noted. Each keeps existing web behavior
unchanged (the reconciliation surface is additive; the drawer-by-register resolution is
sync-path-only).

1. **PR-1 — reconciliation model + inbox raise.** `reconciliation_items`, `session_variances`,
   `parked_mutations` migrations + models (BaseModel, `HasPublicId`, `RecordsChanges`),
   `ReconciliationType`/`ResolutionKind` enums, `RaiseReconciliationItem` action, and wiring the
   oversell/credit-breach creation sites (Design 02 §6) + the push loop's terminal-rejection path
   to raise items. Tests: a pushed two-register oversell yields exactly one item; a terminal
   rejection parks; re-push is a no-op (no duplicate item).
2. **PR-2 — session variance replay.** `ReplayCloseSessionAction` (drawer by `register_id`,
   capture expected before adjustment, raise `SessionVariance`). Tests: offline close with/without
   variance; money invariant (drawer balances); cloud R0 close raises nothing.
3. **PR-3 — permissions + inbox UI + resolution actions.** `PermissionSeeder` groups,
   `ReconciliationItemPolicy`, `can:` routes, `Reconciliation/{Index,Show}.vue`, nav badge,
   `ResolveOversellAction` / `ResolveCreditBreachAction` / `ResolveSessionVarianceAction` (all
   wrapping existing primitives). Tests: permission gating; each resolution path leaves records +
   audit consistent; en/ar strings.
4. **PR-4 — notifications.** `ReconciliationDigestNotification` (mail) + scheduled
   `reconciliation:digest` command (tenant + locale binding). Tests: digest content, per-tenant
   locale, only `reconciliation.view` holders.
5. **PR-5 — device fleet (tenant).** `Devices/{Index,Show}.vue`, health derivation,
   `RevokeDeviceAction` (+ token kill, `revoked_unsynced_count`, `403 device_revoked` on the sync
   guard), `ReplaceDeviceAction` (drain-first, same-register provision), `devices.manage` gating.
   Tests: revoke → API 401/403 (feature test); replace continues the serial sequence; offline
   flag gate.
6. **PR-6 — robustness.** `X-Client-Time` skew capture + `clock_skew_seconds`, `409
   cursor_expired` pull path + 30-day retention/compaction floor, flaky-network fault-injection
   tests. Tests per §5.2 acceptance.
7. **PR-7 — super-admin fleet + feature flag.** `tenants.offline_enabled` migration + toggle
   (`LogAdminAction`-audited), `Admin/DeviceFleetController`, `Admin/DeviceFleet/*.vue`, sidebar
   entry. Read-only except the flag.
8. **PR-8 — pilot telemetry.** `Admin/PilotHealth` page (SLO queries), `report-reconciliation`
   export + query, `client_pushed_at`/heartbeat telemetry fields, and the support-playbook doc.

PR-1 → PR-3 are ordered; PR-4/5/6/7/8 depend on PR-1 but are otherwise parallelizable. The
**client wipe contract** (§4.2) and the two snapshot/telemetry additions (§9) are handed to the
PRD-02/PRD-03 repos, not implemented here.
