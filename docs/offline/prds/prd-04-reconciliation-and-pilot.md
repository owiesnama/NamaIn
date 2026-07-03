# PRD-04 — Reconciliation, Device Management & Pilot (Phase 3)

> Status: Draft · Owner: TBD · Depends on: PRD-02, PRD-03

## 1. Problem

Multi-device offline selling *guarantees* divergence: registers oversell shared stock,
credit customers exceed limits across devices, offline sessions close with cash the
cloud hasn't seen, and devices get lost, stolen, or replaced. PRD-02 records these
facts; this PRD makes them **visible and actionable** for store owners and gets the
whole system through a real-store pilot.

## 2. Goals

- Every divergence the sync layer records reaches a human who can resolve it, in the
  cloud app, in their language.
- Device fleet management is a first-class tenant feature (and visible to super-admin).
- One real store runs on the offline client for a pilot period, meeting defined SLOs.

## 3. Non-goals

- No new offline capabilities (that's Phase 4).
- No automated conflict *resolution* beyond what PRD-02 defines — this PRD surfaces
  and routes; humans decide.

## 4. Functional requirements

### Reconciliation center (cloud app)

- **FR-1** A reconciliation inbox in the tenant app listing open items by type:
  oversell events (PRD-02 FR-13), credit-limit breaches (PRD-02 FR-14), parked/rejected
  device mutations, and session cash variances (FR-4). Each item: what happened, which
  device/register/user, when (device time and sync time), linked records.
- **FR-2** Oversell resolution flow: the owner sees the negative/forced stock position
  and resolves via existing primitives — stock adjustment
  (`app/Actions/Stock/RecordAdjustmentAction.php`), stock transfer, or
  acknowledge-as-shrinkage. Resolution is recorded against the reconciliation item
  (who/when/how).
- **FR-3** Credit-breach flow: item links the customer account and the offending
  invoices; resolution is acknowledge, collect payment (existing payment flow), or
  adjust the credit limit.
- **FR-4** Offline session reconciliation: sessions opened/closed offline sync with
  their declared closing counts; the cloud compares against synced payments for that
  session and drawer, and raises a variance item when they disagree — consistent with
  however cloud sessions handle closing variance today (reuse
  `app/Actions/Pos/ClosePosSessionAction.php` semantics).
- **FR-5** Items are permission-gated (`reconciliation.view` / `.resolve`), and
  notifications for new items follow existing notification conventions
  (`app/Notifications/`, localized).

### Device management

- **FR-6** Tenant device dashboard: all registers/devices with status (provisioned,
  active, revoked), last seen, last push/pull, pending-outbox age as last reported,
  app/protocol version.
- **FR-7** Revoke device: immediate token invalidation (PRD-02 FR-2); the client wipes
  on next launch (PRD-03 FR-20). Unsynced data at revocation time is flagged on the
  device record (count last reported) so the owner knows what may be lost.
- **FR-8** Replace-register flow: retire a device and provision a successor onto the
  same register without breaking the serial sequence (next `seq` continues).
- **FR-9** Super-admin (`/__admin`) visibility: per-tenant device fleet and sync health,
  read-only, in the existing admin panel patterns.

### Sync robustness (hardening items surfaced by pilot-readiness review)

- **FR-10** Clock skew: device timestamps are recorded as-reported plus server receipt
  time; ordering for accounting uses server sequence, and reports use business
  (device) time. Extreme skew (> threshold) raises a device-health warning.
- **FR-11** Resumable snapshot download and partial pull recovery are verified under
  flaky-network simulation (builds on PRD-02 FR-6).
- **FR-12** Backpressure: a device offline for weeks (huge pull backlog / pruned change
  log) is handled by an explicit re-snapshot path, not an infinite pull.

### Pilot

- **FR-13** Pilot plan: one real store, ≥2 registers, defined duration (e.g. 4 weeks),
  with a rollback story (store can revert to cloud-web POS at any time without data
  loss — everything synced stays; unsynced work is pushed before revert).
- **FR-14** SLOs measured during pilot: (a) a sale reaches the cloud < 1 minute after
  connectivity returns (p95); (b) zero lost or duplicated sales; (c) reconciliation
  items resolved < 48h; (d) crash-free sessions target for the desktop app.
- **FR-15** Pilot telemetry: sync audit trail (PRD-02 FR-19) is queryable enough to
  compute the SLOs; a lightweight internal dashboard or report suffices.
- **FR-16** Support playbook: documented procedures for the failure modes (device
  lost, oversell storm, stuck outbox, upgrade-required deadlock), written for support
  staff, not engineers.

## 5. Technical context

- Reconciliation items should be a first-class model (tenant-scoped BaseModel) written
  by the push pipeline (PRD-02) — not derived by scanning for negative stock after the
  fact.
- Existing surfaces to reuse: admin activity log (`ActivityLog`), export engine for
  reconciliation reports, notification patterns, `can:` gates driven by tenant roles.
- Cash/session semantics: `pos_sessions` + drawer `treasury_accounts` +
  `treasury_movements` — the variance comparison must respect the money invariant
  (integer minor units end-to-end).

## 6. Acceptance criteria

- Simulated two-register oversell produces exactly one reconciliation item, resolvable
  through FR-2, leaving stock, movements, and the item's audit trail consistent.
- Revoking a device kills API access (feature test) and the client wipe path is
  verified; replacing a register continues its serial sequence (feature test).
- A device offline past the change-log horizon successfully re-snapshots and continues.
- Pilot artifacts exist: plan (FR-13), SLO definitions + measurement queries (FR-14/15),
  support playbook (FR-16).
- All reconciliation/device UI strings localized (en/ar), RTL verified.

## 7. Open questions for the design doc

1. Reconciliation item taxonomy: one polymorphic model vs. per-type tables.
2. Who gets notified for which item types by default (owner vs. any `reconciliation.*`
   holder), and via which channels (in-app only vs. mail).
3. Change-log retention window vs. re-snapshot threshold (with PRD-02).
4. Pilot store selection criteria, and whether a feature flag gates offline
   provisioning per tenant during the pilot.
