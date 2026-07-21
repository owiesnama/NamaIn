# PRD-01 — Sync Foundations (Phase 0)

> Status: Draft · Owner: TBD · Depends on: — · Blocks: PRD-02, PRD-03, PRD-04

## 1. Problem

The cloud schema cannot support offline writers today. All primary keys are
auto-increment bigints (no UUID/ULID anywhere in app data), invoice serial numbers
embed the auto-increment `id` behind a unique constraint
(`app/Observers/InvoiceObserver.php`), there is no change tracking or tombstoning for
deletions, and idempotency exists only on `invoices`. Every downstream offline
component depends on fixing these.

These changes ship to the **cloud app** and must be invisible to current users: no
behavior change for the existing web flows.

## 2. Goals

- Every syncable row carries a globally unique, offline-mintable identity.
- Serial numbers can be generated on any register, offline, with zero collision risk.
- Every write to a syncable table is observable as an ordered change stream per tenant.
- Replaying any device mutation twice is always safe.
- Devices and registers exist as first-class records.

## 3. Non-goals

- No sync API endpoints (PRD-02).
- No local client work (PRD-03).
- No change to how the existing web UI creates or edits records, beyond the serial
  format.

## 4. Functional requirements

### Identity

- **FR-1** Every syncable table gains a `public_id` ULID column, unique per table,
  indexed, non-null. Syncable tables: invoices, transactions, transaction_receipts,
  payments, customers, customer_advances, suppliers, products, units, categories,
  stocks, stock_movements, stock_transfers, stock_transfer_lines, adjustments,
  storages, pos_sessions, treasury_accounts, treasury_movements, treasury_transfers,
  cheques, banks, expenses, recurring_expenses, quotes, quote_items, preferences.
- **FR-2** Internal integer PKs and existing FK columns are unchanged. `public_id` is
  the sync identity only; the design doc must define how pushed mutations referencing
  `public_id`s are resolved to local FKs on the server.
- **FR-3** ULIDs are assigned automatically on create (model-level, e.g. a `HasPublicId`
  trait on `app/Models/BaseModel.php`), so web-created and device-created rows are
  uniform. Backfill migration populates existing rows.
- **FR-4** ULID generation must work on the offline client with no coordination
  (ULID's timestamp+randomness satisfies this; no central sequence may be introduced).

### Registers & devices

- **FR-5** New `registers` table (tenant-scoped): belongs to a `storage` (sale point),
  has a short unique-per-tenant register code (e.g. `R1`, `R2`) used in serials, and an
  active flag. Existing cloud-web POS usage maps to a reserved cloud register so cloud
  and offline sales share one numbering scheme.
  *Correction (design phase):* a reserved register **per sale point** would collide on
  serials; the design uses a single tenant-wide `R0` — see
  `design/01-sync-foundations.md`.
- **FR-6** New `devices` table (tenant-scoped): a physical installation bound to one
  register, with provisioning status, last-seen/last-sync timestamps, and a revocation
  flag. Token linkage is defined in PRD-02; this PRD only creates the registry.

### Serial numbering

- **FR-7** Invoice serials become per-register series:
  `{INV|RET}-{SA|SU}-{yy}-{registerCode}-{seq}` where `seq` is a per-register,
  per-year counter — **not** derived from any table PK.
- **FR-8** The serial is assigned where the invoice is created (cloud for web sales,
  device for offline sales) and is final: it is printed on the customer receipt and
  never renumbered on sync.
- **FR-9** Uniqueness is enforced at the DB as unique
  `(tenant_id, serial_number)` — collisions are impossible by construction (register
  code in the serial), the constraint is a backstop.
- **FR-10** Existing serials keep working: old rows are not renumbered; lookups/search
  by serial must match both formats. Quote numbering (`app/Observers/QuoteObserver.php`,
  `Q-YY-{id}`) is migrated to the same per-register scheme only if quotes become
  offline-creatable; otherwise document it as cloud-only numbering.

### Change tracking

- **FR-11** A per-tenant, strictly monotonic change log: every create/update/delete of
  a syncable row appends an entry `(tenant_id, seq, table, public_id, operation,
  changed_at, source_device_id?)`. `seq` is the pull cursor for PRD-02.
- **FR-12** Deletions are syncable. Tables that hard-delete today either move to
  soft-delete or write a tombstone entry in the change log — decide per table in the
  design doc; the change stream must never silently lose a delete.
  *Correction (design phase):* `payments` soft-deletes; the actual hard-deleting
  syncable set is 12 tables — see `design/01-sync-foundations.md`, which is
  authoritative on this list.
- **FR-13** The change log must be written in the same DB transaction as the change
  itself (no observer that can fire outside the transaction), so a consumer at cursor N
  can never miss a change with seq < N. Beware: writes currently flow through Action
  classes and `DB::transaction` (see `app/Actions/`), plus some direct
  `increment`/`decrement` calls in `app/Models/Storage.php`.

### Idempotency

- **FR-14** Extend the existing invoice idempotency pattern
  (`unique(tenant_id, idempotency_key)` + replay-returns-existing, see
  `app/Actions/Pos/ProcessPosCheckoutAction.php`) to: payments, stock transfers,
  adjustments, expenses, POS session open and close.
- **FR-15** Replaying a mutation with a known idempotency key returns the original
  result and performs zero writes — including no duplicate change-log entries and no
  duplicate stock/treasury movements.

### Packaging spike

- **FR-16** Time-boxed spike: the application boots under NativePHP on SQLite —
  migrations run, a tenant can be bound, and the POS session page renders. Deliverable
  is a written verdict (works / works-with-changes / blocked) with the list of
  incompatibilities found (e.g. Redis-dependent code paths, Horizon, Reverb, cron).

## 5. Technical context

- Tenancy: single DB, `tenant_id` scoping via `app/Scopes/TenantScope.php` +
  `app/Traits/BelongsToTenant.php`; scope fails closed without a bound tenant.
- Money: integer minor units + `app/ValueObjects/Money.php` / `app/Casts/MoneyCast.php`.
- Stock engine: `app/Models/Storage.php` (`addStock`/`deductStock`/`setStockTo`), all
  under `DB::transaction` + `lockForUpdate`, ledgered in `stock_movements`.
- CI runs the suite on SQLite `:memory:` — new migrations must stay driver-portable
  (mysql/pgsql/sqlite), matching the existing driver-branching style.

## 6. Acceptance criteria

- A new row created from the web app has a ULID `public_id`, appears in the change log
  with the next tenant seq, and its serial (if an invoice) uses the register format.
- Backfill: 100% of existing syncable rows have `public_id`s; existing serials
  unchanged; all existing tests green.
- Deleting a syncable row (hard or soft) produces a change-log entry a consumer can
  observe.
- Submitting the same POS checkout, payment, transfer, adjustment, expense, or session
  open/close twice with one idempotency key produces exactly one of each record.
- Feature tests cover FR-7/8/9 serial generation for two registers in the same year
  and the year rollover.
- Spike verdict document exists (FR-16).

## 7. Open questions for the design doc

1. Change log storage: one table with per-tenant seq vs. per-tenant partitioning;
   retention/compaction policy.
2. Which hard-deleting tables become soft-delete vs. tombstone-only (FR-12).
3. Do `users`/`roles`/`permissions` sync to devices (needed for local login and `can:`
   checks) — and if so, are they in scope here or in PRD-02's snapshot only?
4. Register code format and who assigns it (auto `R{n}` vs. user-chosen).
