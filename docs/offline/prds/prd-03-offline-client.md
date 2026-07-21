# PRD-03 — Offline Client, NativePHP (Phase 2)

> Status: Draft · Owner: TBD · Depends on: PRD-01, PRD-02 · Blocks: PRD-04

## 1. Problem

Stores lose the ability to sell the moment connectivity drops. We need a desktop
application a cashier can run at a register that works fully offline for POS and
expense entry, and syncs with the cloud transparently when connectivity returns —
running the **same Laravel codebase** so business logic, localization (en/ar + RTL),
and receipt rendering are not reimplemented.

## 2. Goals

- A register keeps selling with zero connectivity: session open/close, cash/bank/credit
  sales, walk-in and named customers, receipt printing, expense entry.
- Sync is invisible when things work and legible when they don't (clear pending/error
  states, never a data-loss surprise).
- One codebase: the offline client is a runtime profile of the existing app, not a fork.

## 3. Non-goals

- Purchases/goods receiving, treasury transfers, quotes, reports, imports/exports,
  recurring-expense generation, and admin features are **not** available offline
  (hidden, not broken, in the local UI).
- No offline edits/deletes of already-synced records (append-only, per PRD-02 FR-17).
- No mobile build in this phase.

## 4. Functional requirements

### Runtime profile

- **FR-1** NativePHP desktop packaging of the existing app on SQLite, per the Phase 0
  spike verdict (PRD-01 FR-16). Local profile disables: Horizon, Reverb/Echo,
  Telescope, mail sending, broadcasts (`QUEUE_CONNECTION=sync` already degrades jobs
  inline).
- **FR-2** Single-tenant binding: the provisioned tenant is bound as `currentTenant`
  at boot (the pattern in `app/Jobs/GenerateExportJob.php::bindTenantContext()`), with
  no subdomain routing. `TenantScope`'s fail-closed behavior must still hold.
- **FR-3** A `runtime` abstraction (config/service) lets code branch
  cloud-vs-local where unavoidable; grep-able and kept to a minimum. Features hidden
  offline are driven by this, not by deleting routes.
- **FR-4** Auto-update channel for the desktop app, and the app refuses to sync when
  the server demands an upgrade (PRD-02 FR-20) while still allowing offline selling.

### Provisioning & local auth

- **FR-5** First-run wizard: sign in online → pick register (from PRD-01 registry) →
  download snapshot (PRD-02 FR-4..6) → seeded local DB → ready. Resumable if
  interrupted.
- **FR-6** Local login works offline for users included in the snapshot, honoring
  tenant roles/permissions for the existing `can:` gates. Mechanism (cached password
  hashes vs. device PIN) is decided with PRD-02 open question 5.
- **FR-7** Users deactivated in the cloud lose local access after the next sync
  (pull applies user/role changes).

### Offline POS

- **FR-8** POS session lifecycle (`app/Actions/Pos/Open|ClosePosSessionAction.php`)
  works offline against the register's drawer treasury account.
- **FR-9** Checkout runs the existing `ProcessPosCheckoutAction` locally: stock
  deducted from local `stocks`, movements ledgered, payment + treasury movement
  recorded, serial minted from the register series (PRD-01 FR-7). The preflight call
  (`app/Actions/Pos/PosPreflightAction.php`) runs against local data;
  cross-storage replenishment hints degrade gracefully when data is stale.
- **FR-10** Credit sales offline: allowed for named customers against cached credit
  limit and last-synced balance; the UI shows the limit check is based on data as-of
  last sync. Breach resolution is a cloud-side concern (PRD-02 FR-14, PRD-04).
- **FR-11** Receipt printing offline: the existing server-rendered receipt page
  (`invoices.receipt` route) renders locally. Receipts show the final serial.
- **FR-12** Local stock display distinguishes "last synced" vs. "after my unsynced
  sales", and shows data-staleness (time since last successful pull) — because sibling
  registers may be depleting the same stock invisibly.

### Offline expenses

- **FR-13** Expense entry offline against the register's drawer: amount, category,
  note, optional receipt attachment stored locally and uploaded on sync. Expenses enter
  the existing cloud approval workflow on push (they sync as pending approval where the
  tenant uses approvals).

### Outbox & sync worker

- **FR-14** Every local business mutation is captured in an outbox (ordered, with
  idempotency keys and `public_id`s) in the **same local transaction** as the mutation.
- **FR-15** A background sync worker (a) pushes outbox batches (PRD-02 FR-11) with
  retry/backoff, (b) pulls changes on an interval and applies them to the local DB,
  (c) reconciles push results (applied / already-applied / rejected) and parks rejected
  mutations for user attention rather than blocking the queue.
- **FR-16** Pulled changes never clobber unsynced local work: apply order is
  pull-then-replay-outbox semantics or field-level guard — defined in the design doc
  (MVP's append-only writes make this tractable: local creates are never targets of
  pulls).
- **FR-17** Sync-status UI, always visible in the local app chrome: online/offline,
  pending outbox count, last successful push/pull, and an errors screen listing parked
  mutations with human-readable reasons (localized).
- **FR-18** Durability: the local SQLite is WAL-mode, crash-safe; killing the app
  mid-checkout or mid-sync never loses a committed sale and never double-sends
  (idempotency keys survive restart).

### Data lifecycle

- **FR-19** Local retention: synced historical data older than a configurable window
  can be pruned locally; the device is not an archive. Unsynced data is never pruned.
- **FR-20** Deprovisioning: a "detach device" flow wipes local data after confirming
  the outbox is empty; PRD-04's remote revocation forces the same wipe on next launch.

## 5. Technical context

- POS frontend: `resources/js/Pages/Pos/Session.vue` — cart is already client-side;
  checkout is Inertia post + axios preflight; idempotency key currently
  `Date.now().toString()` (must become a real UUID/ULID as part of outbox work).
- Receipts: `window.open(route('invoices.receipt', id))` — server-rendered, works
  locally unchanged.
- SQLite compatibility is proven by CI; watch driver-specific SQL in
  `app/Queries/Reports/` (out of scope offline) and `DB::dateFormat` helpers.
- Localization: JSON catalogs shared via `HandleInertiaRequests` + `__()` — bundled
  data, works offline; keep the `cache()->rememberForever` path functional on the
  local profile.

## 6. Acceptance criteria

- **The exit test (from the roadmap):** two provisioned devices in one store run a full
  day offline — sessions, cash + credit sales, expenses, receipts — then reconnect;
  everything lands in the cloud exactly once, with correct serials per register,
  correct drawer movements per register, and any oversell surfaced (PRD-02 FR-13).
- Kill-the-app chaos tests: force-quit during checkout and during push; no lost or
  duplicated sales after restart.
- Offline local login works; a user deactivated in the cloud is locked out after sync.
- The local UI hides online-only modules and shows the sync-status surface (FR-17)
  with localized strings in en and ar (RTL verified).
- Pest feature tests cover the outbox capture, worker retry, parked-rejection flow;
  packaging smoke test documented for the NativePHP build.

## 7. Open questions for the design doc

1. Sync worker mechanics inside NativePHP: scheduled tick vs. long-running child
   process; behavior when the app is closed (sync only while app runs?).
2. Local auth mechanism (with PRD-02): cached hashes vs. PIN; lockout policy offline.
3. Pull interval and whether to use a lightweight "changes available" signal when
   online instead of pure polling.
4. Thermal-printer integration: keep browser print dialog (current behavior) or add
   native printing via NativePHP APIs in MVP.
5. How much cross-storage stock visibility the offline POS shows (ties to PRD-02 scope
   matrix, open question 3).
