# Offline Version — Analysis & Roadmap

> Status: **Draft for review** · Created: 2026-07-03
>
> Direction: an offline-capable version of NamaIn that runs locally on native PHP and
> syncs data from the local device to the cloud. This document captures the current-state
> analysis and the phased roadmap. Design documents and PRDs will be derived from it.

---

## 1. Current-state analysis

### 1.1 Architecture snapshot

| Area | Current state |
|---|---|
| Stack | Laravel 13, Inertia v1 + Vue 3, Tailwind v3 |
| Tenancy | Custom single-database, `tenant_id` column scoping (`app/Scopes/TenantScope.php`, `app/Traits/BelongsToTenant.php`), tenant resolved by subdomain (`app/Http/Middleware/ResolveTenant.php`) |
| Database | MySQL/PostgreSQL in production; **SQLite already supported** (driver branches in queries, full test suite runs on SQLite `:memory:` in CI) |
| Auth | Fortify + Jetstream sessions; Sanctum installed but **dormant** (no token routes, `routes/api.php` is empty) |
| API | **None.** Everything is served through Inertia web routes (`routes/tenant.php`, `routes/web.php`) |
| Queues / realtime | Horizon (exports, imports, backups), Reverb + Echo (export/import status broadcasts only) |
| PWA groundwork | `public/manifest.json`, `public/sw.js` (navigation fallback to `/offline` only — no data caching), `resources/views/offline.blade.php` |

### 1.2 What works in our favor

1. **Money is portable.** All amounts are integer minor units with a single value object
   (`app/ValueObjects/Money.php`) and cast (`app/Casts/MoneyCast.php`) — unified by the
   money refactor (migration `2026_07_01_223745_unify_money_columns_to_minor_units.php`).
   No floating-point sync hazards.
2. **POS checkout already has idempotency.** `invoices` has a unique
   `(tenant_id, idempotency_key)` and `app/Actions/Pos/ProcessPosCheckoutAction.php`
   returns the existing invoice on replay. This is exactly the primitive an offline
   sale-queue replay needs.
3. **Stock has an append-only ledger.** `stock_movements` records signed quantities with
   before/after snapshots alongside the authoritative stored quantity in `stocks`.
   Offline sales can be replayed as movements server-side.
4. **All business writes go through Action classes**, not model events —
   `ProcessPosCheckoutAction`, `DeliverTransactionAction`, `TransferStockAction`,
   `RecordPaymentAction`, etc. A sync-ingest endpoint can reuse them unchanged.
5. **SQLite is anticipated in code.** Query helpers branch on `DB::getDriverName()`
   (sqlite/pgsql/mysql), and CI proves the app runs on SQLite.
6. **Tenant context can be bound outside HTTP.** `GenerateExportJob` / `ProcessImportJob`
   already rebind `app('currentTenant')` manually — the same pattern a local
   single-tenant runtime and a background sync worker need. `TenantScope` fails closed
   when no tenant is bound.
7. **Existing snapshot path.** Admin backups (`app/Actions/Admin/BackupTenantAction.php`)
   already export per-tenant data — a starting point for seeding a local database.

### 1.3 What works against us (Phase 0 drivers)

1. **No global IDs.** Every table — including `tenants` — uses auto-increment bigint
   primary keys. Zero UUID/ULID usage in app data. Two offline devices will mint
   colliding IDs, and every foreign key is a local sequential int.
2. **Invoice serial numbers embed the auto-increment `id`.**
   `app/Observers/InvoiceObserver.php` generates `INV-SA-26-{id}` on `created`, and
   `invoices.serial_number` has a DB unique constraint. Offline devices generating
   serials will collide with the cloud and with each other.
3. **No change tracking.** No version/revision columns, no changelog table, and several
   transactional tables hard-delete (payments, stock_movements, treasury_movements) —
   so there are no tombstones to sync deletions from cloud to device. Concurrency today
   is pessimistic `lockForUpdate()` inside one DB, which does not exist across devices.
4. **No API surface.** The entire sync API (auth, snapshot, pull, push) must be built.
   Sanctum is available as the substrate.
5. **Stock logic assumes one consistent DB.** On-hand is authoritative-stored (not
   ledger-derived), and POS checkout performs a live preflight
   (`app/Actions/Pos/PosPreflightAction.php`) plus auto-replenishment transfers when the
   sale point is short. Offline devices cannot see other devices' decrements.

---

## 2. Recommended architecture

Run the **same Laravel codebase locally on SQLite**, packaged for desktop with
**NativePHP** (or a bundled-PHP installer — packaging is a Phase 0 spike, not a
blocker), bound to a single tenant, with Horizon/Reverb/Telescope stripped.

- The local app is a **POS-first offline node**: a full read cache of products,
  customers, and prices; offline POS sessions and sales; and an **outbox** of local
  mutations that a background sync worker pushes to a new cloud sync API.
- The **cloud stays the source of truth**. Offline devices are edge writers for a
  constrained set of operations (POS sales), not full replicas.
- Reusing the Laravel codebase means the checkout/stock/payment Actions, localization
  (en/ar + RTL), permissions, and server-rendered receipt pages all work locally for
  free.

### Why not a PWA (IndexedDB + service worker)?

Lower install friction, but it would require rewriting the entire POS domain logic
(stock, pricing, units, payments, idempotency) in JavaScript and maintaining two
implementations. The native-PHP approach reuses the tested backend as-is. The existing
PWA shell remains useful for the graceful "you're offline" experience of the cloud app.

---

## 3. Roadmap

### Phase 0 — Sync-ready foundations
*Cloud-app changes that are valuable even without offline. Everything else depends on these.*

- **Global identity:** add a `public_id` ULID column (unique, indexed) to every
  syncable table. Keep int PKs internally; ULIDs become the sync identity. New rows get
  ULIDs everywhere, including from the current web app.
- **Serial numbering:** decouple invoice serials from the PK. Options: per-register
  scheme (`INV-SA-26-{register}-{seq}`) or server-assigned-on-sync with a provisional
  local number. Business/compliance decision — see Open Decisions.
- **Change tracking:** per-tenant monotonic `server_seq` via a changelog/outbox table on
  syncable models, plus tombstones (soft-delete or a deletions table) for anything
  hard-deleted today.
- **Idempotency everywhere:** extend idempotency keys beyond invoices to payments,
  stock transfers, adjustments, and POS session open/close.
- **Packaging spike:** prove the app boots under NativePHP on SQLite — `migrate` runs
  and the POS page renders locally.

**Exit criteria:** every syncable row has a ULID; serials no longer derive from the PK;
a changelog records every write with a per-tenant sequence; packaging spike verdict.

### Phase 1 — Sync protocol + cloud API
- Stand up the API on Sanctum device tokens: device registration/provisioning, tenant
  **snapshot download** (bootstrap the local SQLite), incremental **pull**
  ("changes since cursor" per entity), batched **push** (mutations with idempotency
  keys, mapped through the existing Action classes).
- Define the conflict policy per domain:
  - **Catalog / customers / preferences** — server wins; pull-only on devices at first.
  - **Sales / payments / POS sessions** — append-only creates from devices; no offline
    edits of synced records.
  - **Stock** — server recomputes from replayed movements; overselling is reconciled on
    sync (surfaced, not silently blocked).

**Exit criteria:** a device can provision, download a snapshot, pull increments, and
push a batch of sales that land exactly once.

### Phase 2 — Local client MVP (multi-device, POS + expenses)
- **Local runtime profile:** single-tenant binding, SQLite, sync queue, no
  Horizon/Reverb; local login against cached credentials with periodic online re-auth.
- **Offline scope:** open/close POS session, cash and credit sales to cached customers
  (credit checked against cached limits, breaches flagged on sync), walk-in sales,
  receipt printing (server-rendered receipt page works locally), expense entry against
  the register's drawer, local stock decrement with "unsynced" awareness.
- **Multi-device from v1:** several registers per store may be offline concurrently.
  Each register has its own serial series, its own drawer (treasury account), and its
  own sync cursor; stock overselling across registers is reconciled on sync, not
  prevented.
- **Explicitly out of scope for the MVP:** purchases/goods receiving, treasury
  transfers, recurring-expense generation, reports, imports/exports.
- **Outbox + sync worker:** background push with retry/backoff, plus a visible
  sync-status UI (pending count, last sync time, conflicts).

**Exit criteria:** a full offline day of POS sales and expenses on two or more devices
in the same store syncs cleanly to the cloud when connectivity returns, with any
oversell surfaced for reconciliation.

### Phase 3 — Hardening + reconciliation
- Conflict-surfacing UI in the cloud app (oversold stock, rejected mutations).
- Session/cash reconciliation for offline-created POS sessions and drawer movements.
- Clock-skew handling, resumable/partial sync, device revocation and remote wipe.
- **Pilot with one real store.** Define sync SLOs (e.g. a sale reaches the cloud less
  than one minute after connectivity returns).

### Phase 4 — Expand offline scope
- Offline returns against synced invoices; offline stock adjustments and goods
  receiving.
- Read-only local reports from the local dataset (the report engine's queries in
  `app/Queries/Reports/` already run on SQLite).

---

## 4. Decisions (settled 2026-07-03)

| # | Decision | Outcome |
|---|---|---|
| 1 | **Packaging** | NativePHP desktop app running the existing Laravel codebase on SQLite. |
| 2 | **Invoice numbering** | Per-register serial series, e.g. `INV-SA-26-R2-00145`. The number printed offline is final; register code guarantees no collisions. |
| 3 | **MVP offline scope** | POS **+ expenses**. Purchases/goods receiving deferred to Phase 4. |
| 4 | **Credit sales offline** | Allowed against cached credit limit and balance; breaches are flagged on sync for follow-up, not blocked retroactively. |
| 5 | **Multi-device per store** | **Multi-device from v1.** Concurrent offline registers per store; oversell reconciliation, per-register drawers, and per-device cursors are v1 requirements. |
| 6 | **Repo layout** | Three deliverables: (a) cloud changes land as PRs on this repo; (b) a **sync lib** in its own repo — shared protocol package (contracts, mutation envelopes, client sync engine) consumed by both sides; (c) the **offline app** (NativePHP shell + local runtime profile) in its own repo. Design docs must define the package boundaries and how the offline app reuses this codebase. |

---

## 5. Derived documents

PRDs live in [`docs/offline/prds/`](prds/README.md):

1. **PRD-01 — Sync foundations** (Phase 0): ULID identity, per-register serials,
   changelog/tombstones, idempotency coverage, device & register registry.
2. **PRD-02 — Sync protocol & cloud API** (Phase 1): provisioning, snapshot, pull/push
   contracts, conflict policy per entity.
3. **PRD-03 — Offline client** (Phase 2): NativePHP runtime profile, offline POS +
   expenses UX, outbox and sync worker, sync-status UI.
4. **PRD-04 — Reconciliation, device management & pilot** (Phase 3): conflict surfacing,
   cash/session reconciliation, device lifecycle, pilot rollout.
