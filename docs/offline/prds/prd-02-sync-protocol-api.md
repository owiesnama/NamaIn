# PRD-02 — Sync Protocol & Cloud API (Phase 1)

> Status: Draft · Owner: TBD · Depends on: PRD-01 · Blocks: PRD-03, PRD-04

## 1. Problem

There is no API surface at all — `routes/api.php` is empty and every feature is an
Inertia web route. Offline devices need a first-class, versioned API to provision
themselves, bootstrap a local database, pull cloud changes, and push local mutations —
with exactly-once semantics and a defined conflict policy for every entity, under
multi-device concurrency.

## 2. Goals

- A device can go from fresh install → provisioned → fully seeded local DB → staying
  current via incremental pull → pushing local work, using only this API.
- Every mutation a device pushes lands **exactly once**, in a valid order, or is
  rejected with a reason the device can act on.
- Conflict behavior is explicit per entity — nothing is left to "whatever the ORM does".

## 3. Non-goals

- No client-side implementation (PRD-03).
- No reconciliation/admin UI (PRD-04) — this PRD only records the facts those UIs need.
- No general-purpose public API; endpoints are for device sync only and are versioned
  separately (`/api/sync/v1/...`).

## 4. Functional requirements

### Auth & provisioning

- **FR-1** Device provisioning: an authenticated tenant user with a new
  `devices.manage` permission initiates enrollment (pairing code or signed URL); the
  device exchanges it for a Sanctum device token bound to one `device` record →
  register → storage → tenant (registry from PRD-01 FR-5/6).
- **FR-2** Device tokens are scoped to the sync API only (token abilities) — they must
  not grant access to web routes or other tenants' data. Revoking a device (PRD-04)
  invalidates its token immediately.
- **FR-3** Sync requests carry the device identity; all pushed rows record
  `source_device_id` for audit and reconciliation.

### Snapshot (bootstrap)

- **FR-4** Snapshot endpoint returns a consistent point-in-time export of the tenant's
  syncable dataset for the device's scope, plus the change-log cursor the snapshot was
  taken at, so the first pull continues seamlessly from the snapshot.
- **FR-5** Snapshot contents (minimum): products + units + categories + stocks (for
  the device's storage), customers + advances + balances, suppliers (read-only
  reference), storages, registers, treasury account of the register's drawer,
  preferences, users/roles/permissions needed for local login and authorization,
  translation catalogs if not bundled with the app.
- **FR-6** Snapshots are generated as a queued job (reuse the export/backup
  infrastructure patterns — `app/Jobs/GenerateExportJob.php`,
  `app/Actions/Admin/BackupTenantAction.php`) and downloaded as a file; the endpoint
  supports polling for readiness and resumable download.

### Pull (cloud → device)

- **FR-7** Incremental pull: `GET changes?cursor={seq}` returns ordered change-log
  entries with row payloads, filtered to the device's scope, paged, with a new cursor.
  Cursor per device is tracked server-side (last-acked) for observability but owned by
  the client.
- **FR-8** Pull payloads use `public_id` for identity and for all references (FKs are
  translated to `public_id`s at the boundary).
- **FR-9** Deletions arrive as tombstone entries (PRD-01 FR-12) the client can apply.
- **FR-10** Scope filter: a device receives only its tenant, and within it only data
  relevant to its storage/register where entity semantics allow (design doc defines
  the per-entity scope matrix; default is whole-tenant for reference data).

### Push (device → cloud)

- **FR-11** Batched push: an ordered array of mutations, each with a client-generated
  idempotency key, a `public_id`, a mutation type, and a payload. The batch is applied
  in order; each mutation gets an individual result (applied / already-applied /
  rejected {reason}) so a partially-failed batch never blocks the rest.
- **FR-12** Mutation types for MVP scope: `pos_session.open`, `pos_session.close`,
  `sale.create` (invoice + lines + payment + stock deduction as ONE atomic mutation),
  `customer.create`, `expense.create`. Sales replay through
  `app/Actions/Pos/ProcessPosCheckoutAction.php` (or a sibling action) so serials,
  stock movements, payments, and treasury movements stay consistent with cloud-created
  sales.
- **FR-13** Server-side application of a pushed sale must tolerate stock shortfall:
  when concurrent devices oversold, the sale is still recorded (the customer already
  left with the goods) — stock goes negative or is force-deducted, and an **oversell
  reconciliation record** is created (consumed by PRD-04). The existing
  `InsufficientStockException` path must not reject the push.
- **FR-14** Credit-limit breaches on pushed credit sales are recorded as flags
  (consumed by PRD-04), never rejected.
- **FR-15** Referential ordering: a batch may reference rows created earlier in the
  same batch (e.g. new customer then their sale) via `public_id`. Mutations referencing
  unknown `public_id`s are rejected individually with a retriable error code.
- **FR-16** Replay safety end-to-end: re-pushing an entire batch after a network
  failure produces zero duplicates (leans on PRD-01 FR-14/15).

### Conflict policy (normative)

- **FR-17** Per-entity policy:
  | Entity class | Policy |
  |---|---|
  | Catalog, customers/suppliers master data, preferences, roles | Server wins. Devices pull; MVP devices do not edit them offline (customer **create** is the one exception). |
  | Invoices, transactions, payments, POS sessions, expenses | Append-only creates from devices. No offline edits or deletes of synced records in MVP. |
  | Stock | Server is authoritative. Device decrements are provisional; the server recomputes on push replay; oversell is reconciled, not prevented (FR-13). |
  | Treasury (drawer) | Each register writes only its own drawer account; cross-register conflicts are impossible by construction. |
- **FR-18** Same-`public_id` create arriving twice (device retry vs. new record) is
  disambiguated by idempotency key, not by payload comparison.

### Operational

- **FR-19** All sync endpoints are rate-limited per device, log to a sync audit trail,
  and expose per-device health (last pull, last push, pending age) for PRD-04's device
  dashboard.
- **FR-20** Protocol versioning: client sends its protocol + app version; the server
  can respond "upgrade required" as a first-class status the client handles.

## 5. Technical context

- Sanctum v4 is installed and unused — `User` already has `HasApiTokens`;
  `personal_access_tokens` table exists.
- Existing idempotent-replay precedent: `ProcessPosCheckoutAction` (lockForUpdate on
  `(tenant_id, idempotency_key)`).
- Tenant binding outside HTTP exists (`bindTenantContext()` in
  `app/Jobs/GenerateExportJob.php` / `ProcessImportJob.php`) — reuse for queued
  snapshot generation and any async push processing.
- Money crosses the wire as integer minor units, matching storage.

## 6. Acceptance criteria

- Feature tests (Pest) cover: provision → snapshot → pull → push happy path; batch
  replay after simulated network failure (zero duplicates); two devices pushing sales
  that oversell the same product (both sales recorded, oversell record created); credit
  limit breach flagged not rejected; revoked device gets 401 on every endpoint;
  cross-tenant access attempts fail closed.
- A seeded demo tenant can be fully bootstrapped onto a second database from snapshot +
  pulls alone, and match the cloud dataset.
- Endpoint contracts are documented (request/response schemas) in a design doc that
  PRD-03's client work can build against without reading server code.

## 7. Open questions for the design doc

1. Transport format for snapshot: SQLite file vs. JSONL per entity (JSONL is
   driver-neutral; SQLite file is instant to install).
2. Push processing: synchronous within the request vs. accepted-then-processed
   (async changes the client contract; recommend synchronous for MVP batch sizes).
3. Pull scope matrix details — which entities are storage-scoped vs. tenant-wide
   (e.g. do devices see other storages' stock levels for the replenishment hints the
   POS UI shows today?).
4. Expense attachments (receipt images): multipart in the push vs. separate upload
   endpoint; size limits.
5. How users authenticate **on the device** (cached bcrypt hashes from snapshot vs.
   device-local PIN per user) — decide jointly with PRD-03.
