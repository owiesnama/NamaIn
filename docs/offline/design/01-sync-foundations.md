# Design 01 — Sync Foundations (PRD-01)

> Status: Design for review · Owner: server-side offline · Implements: [PRD-01](../prds/prd-01-sync-foundations.md) · Phase 0
>
> Scope: cloud-app schema and model changes that make the existing Laravel/MySQL/pgsql app
> a valid source-of-truth for offline edge writers. Everything here ships to the cloud app
> and must be **invisible to current web users except the invoice serial format**.

---

## Decisions at a glance

| # | Area | Decision | PRD FR |
|---|---|---|---|
| D1 | Global identity | `public_id` ULID column on every syncable table via a `HasPublicId` trait on `BaseModel`; ULIDs minted model-side on create, backfilled for existing rows. Also added to `users`, `roles`, `permissions` (needed by sync references). | FR-1..4 |
| D2 | FK strategy | Int PKs and int FK columns unchanged. `public_id` is the wire identity only; a boundary resolver maps public_id → int id (PRD-02). | FR-2 |
| D3 | Registers | New tenant-scoped `registers` table. Register **code is unique per tenant**, system-assigned `R{n}`; one reserved cloud register `R0` per tenant (storage-nullable) owns all cloud-web numbering. Device registers `R1..Rn` bind to a sale-point storage. | FR-5 |
| D4 | Devices | New tenant-scoped `devices` table (registry only; token linkage in PRD-02). | FR-6 |
| D5 | Serials | Per-register, per-year, per-series counter table `register_serials`; serial allocated inside the sale transaction via `lockForUpdate`, never from a PK. Single-writer per register by construction. | FR-7..10 |
| D6 | Change log | One `change_log` table per DB; **per-tenant monotonic `seq` allocated from a locked `tenant_sync_state` counter row** (serializes appends per tenant → cursor can never skip). Identity-only entries (no payload); pull resolves payloads live. | FR-11, FR-13 |
| D7 | Capture mechanism | Two channels into `change_log` **inside the same transaction**: (A) a `RecordsChanges` Eloquent trait for the Eloquent path; (B) explicit `ChangeLog::record()` at the finite set of raw-SQL sites. Guaranteed by an **architecture test** that fails CI if any syncable table is mutated outside a covered site. | FR-13 |
| D8 | Deletions | **Tombstone-via-changelog for all** hard-deleting tables (no schema churn); deletes must flow through Eloquent or an explicit record call; client mirrors FK cascades locally. No table is converted to soft-delete solely for sync. | FR-12 |
| D9 | Idempotency | Central `sync_idempotency` table keyed `unique(tenant_id, idempotency_key)` storing mutation type + result public_id + status. Existing `invoices.idempotency_key` stays for the current web POS path. | FR-14, FR-15 |
| D10 | sync-lib | New package `namain/sync-protocol`, namespace `NamaIn\SyncProtocol\`, PHP `^8.4`. Holds the wire contract (DTOs, enums, path/version constants, ULID + idempotency-key helpers). No framework, no DB. | roadmap #6 |

---

## 0. Ground truth found in the code (and where it contradicts the PRDs)

Verified against the codebase — these correct assumptions the PRDs made:

1. **`payments` does NOT hard-delete.** `app/Models/Payment.php` uses `SoftDeletes, WithTrashScope`
   and the table has `deleted_at`. PRD-01 FR-12 lists it as hard-deleting — it is wrong. No
   schema change needed for payments.
2. **The real hard-deleting syncable set is 12 tables**, not 3:
   `transaction_receipts, categories, stocks, stock_movements, stock_transfers,
   stock_transfer_lines, adjustments, pos_sessions, treasury_movements, treasury_transfers,
   quote_items, preferences`. (§5 handles each.)
3. **Eloquent observers are insufficient for the change log.** Several runtime paths mutate
   syncable tables through the raw query builder and never fire model events:
   - `stocks` — every mutation in `app/Models/Storage.php` (`addStock/deductStock/setStockTo`)
     is `DB::table('stocks')->insert/increment/decrement/insertOrIgnore` + a pivot `update`.
     `stocks` is *never* written through Eloquent.
   - `products.average_cost` — `Product::recalculateAverageCost()` raw `update`.
   - `quote_items` — `StoreQuoteAction`/`UpdateQuoteAction` use relation `->insert()`/`->delete()`.
   - `stock_transfer_lines` — `StockTransfersController::store()` relation `->insert()`.
   - `transactions` — `UpdateInvoiceAction::replaceTransactions()` mass `->delete()`.
   This is the single most important design constraint and drives D7.
4. **Three write actions lack their own `DB::transaction`**: `StoreQuoteAction`,
   `UpdateQuoteAction`, `UpdateExpenseAction` (also `ReverseTransactionAction` and
   `RecordTreasuryAdjustmentAction` rely on a caller's transaction). To make the change-log
   append atomic with the write, PRD-01 wraps these (behavior-neutral).
5. **`stocks` is a `Pivot`, not a `BaseModel`** (`app/Models/Stock.php`), so it has no
   `BelongsToTenant` auto-fill and no model events; `tenant_id`/`public_id` must be set
   explicitly at the raw insert sites.
6. **`Role` uses `BelongsToTenant` (tenant-scoped); `Permission` is global reference data.**
   `users` are global and linked to tenants via the `tenant_user` pivot (`role_id`, `is_active`).
   This shapes the local-login snapshot (D1 extends public_id to these).

None of these change the settled roadmap decisions; they change how we implement them.

---

## 1. Identity — `public_id` ULID (FR-1..4)

### 1.1 The `HasPublicId` trait

A trait mixed into `BaseModel` (so every business model inherits it) plus explicitly onto
`User`, `Role`, `Permission` (which do not extend `BaseModel`).

```php
namespace App\Traits;

use Illuminate\Support\Str;

trait HasPublicId
{
    public static function bootHasPublicId(): void
    {
        static::creating(function ($model): void {
            if (empty($model->public_id)) {
                $model->public_id = strtolower((string) Str::ulid());
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'public_id'; // opt-in; web routes keep binding on id unless changed
    }

    public function resolveRouteBinding($value, $field = null)
    {
        return $this->where($field ?? 'id', $value)->firstOrFail();
    }
}
```

- `Str::ulid()` is timestamp + 80 bits randomness → globally unique with **no coordination**
  (FR-4). Lowercased Crockford base32, 26 chars, stored as `char(26)`.
- We do **not** flip web route-model-binding to `public_id` in Phase 0 (would change URLs);
  `getRouteKeyName` override is deliberately *not* applied to existing web models. Keep the
  trait to identity duties only. (Shown above for completeness; the shipped trait contains
  only `bootHasPublicId`.)

`BaseModel::booted()` already calls `static::unguard()`; the trait's boot hook composes with it.

### 1.2 Column + index (portable across mysql/pgsql/sqlite)

One migration per logical group; the pattern per table:

```php
Schema::table('invoices', function (Blueprint $table) {
    $table->char('public_id', 26)->nullable()->after('id');
    // unique added AFTER backfill (see 1.3) to avoid null-collision on partial fills
});
```

`char(26)` is portable (sqlite treats it as TEXT). No functional index needed — plain unique.

### 1.3 Backfill strategy

Backfill runs **after** the nullable column exists, **before** the unique constraint, in a
dedicated migration per table (chunked, driver-portable, tenant-agnostic — public_id is not
tenant-scoped, it is globally unique):

```php
public function up(): void
{
    do {
        $rows = DB::table('invoices')->whereNull('public_id')->limit(2000)->pluck('id');
        foreach ($rows as $id) {
            DB::table('invoices')->where('id', $id)
                ->update(['public_id' => strtolower((string) Str::ulid())]);
        }
    } while ($rows->isNotEmpty());
}
```

Then a final migration:

```php
$table->char('public_id', 26)->nullable(false)->change();
$table->unique('public_id'); // global unique per table
```

- ULIDs generated in a tight loop are monotonic-ish but uniqueness comes from randomness, not
  ordering — safe.
- Uniqueness is **global per table**, not per tenant: a device-minted ULID and a cloud-minted
  ULID must never collide across the whole table. `unique('public_id')`.
- Idempotent: re-running the backfill only touches `whereNull`.

**Note on `stocks`:** it is a `Pivot` written by raw SQL. Its migration adds `public_id`, its
backfill is the same loop, and the raw insert sites in `Storage.php` are patched to mint
`public_id => Str::ulid()` (see §4.2). Its sync identity is naturally the pair
`(storage.public_id, product.public_id)`; `public_id` is carried for FR-1 uniformity and used
as the upsert key on the client.

### 1.4 Which tables (FR-1 list + additions)

All 27 FR-1 tables get `public_id`. **Additions beyond FR-1**, required because sync payloads
and pushed mutations reference them by public_id: `users`, `roles`, `permissions`. Rationale:
- `created_by`, `opened_by`, `approved_by`, `role_id` FKs cross the wire as public_ids;
- local login and `can:` checks need these rows in the snapshot keyed by public_id.

`tenants` also gets `public_id` (device provisioning and token scoping reference the tenant by
public_id). `personal_access_tokens`, `sessions`, log/backup tables are **not** syncable.

### 1.5 Rejected alternatives

- **UUIDv4 PKs replacing int PKs** — a schema-wide rewrite of every FK; breaks the existing
  `sale_point_id`/`invoice_id`/… int columns and all tests. Rejected: PRD-01 FR-2 forbids it.
- **UUIDv7 instead of ULID** — equivalent guarantees; ULID chosen because Laravel ships
  `Str::ulid()` first-class and it is already the idiom for lexicographic time-ordering.
- **Composite natural keys as sync identity** (e.g. serial for invoices) — not all tables have a
  natural key; inconsistent. Rejected.

---

## 2. Registers & devices (FR-5, FR-6)

### 2.1 `registers`

```php
Schema::create('registers', function (Blueprint $table) {
    $table->id();
    $table->char('public_id', 26)->unique();
    $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
    $table->foreignId('storage_id')->nullable()->constrained('storages'); // null = reserved cloud register
    $table->string('code', 8);            // 'R0','R1'... system-assigned, unique per tenant
    $table->string('label')->nullable();  // user-facing friendly name
    $table->boolean('is_cloud')->default(false); // true only for the reserved R0
    $table->boolean('is_active')->default(true);
    $table->timestamps();
    $table->unique(['tenant_id', 'code']);
});
```

- **Code is unique per tenant and system-assigned** (`R{n}`), never user-chosen — this is what
  makes serial collisions impossible by construction. `label` is the editable friendly name.
  (Resolves PRD-01 open question §7.4.)
- **One reserved cloud register `R0` per tenant** (`is_cloud = true`, `storage_id` nullable). All
  cloud-web-created invoices — POS *and* non-POS sales/purchases — are attributed to `R0`. This
  keeps web numbering a single per-tenant series and is the minimal deviation from today.
- Device registers (`R1..Rn`) bind to exactly one sale-point `storage_id`.
- **Deviation from FR-5 wording** ("reserved register *per sale point*"): a per-sale-point `R0`
  would collide across sale points (same code, different storage → duplicate serial). We make
  `R0` tenant-wide instead. The FR intent — cloud and offline share one numbering scheme, codes
  disjoint — is fully met. Recorded here for the reviewer.

**Backfill:** a data migration seeds one `R0` per existing tenant and assigns
`registers.storage_id = null, is_cloud = true`.

### 2.2 `devices`

Registry only; Sanctum token linkage is PRD-02.

```php
Schema::create('devices', function (Blueprint $table) {
    $table->id();
    $table->char('public_id', 26)->unique();
    $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
    $table->foreignId('register_id')->constrained('registers');
    $table->string('name');                        // "Front counter iPad"
    $table->string('status')->default('pending');  // pending|active|revoked  (enum-backed)
    $table->string('pairing_code_hash')->nullable();
    $table->timestamp('pairing_expires_at')->nullable();
    $table->timestamp('provisioned_at')->nullable();
    $table->timestamp('last_seen_at')->nullable();
    $table->timestamp('last_pull_at')->nullable();
    $table->timestamp('last_push_at')->nullable();
    $table->unsignedBigInteger('last_acked_seq')->default(0); // observability; client owns cursor
    $table->timestamps();
});
```

- A device → one register → one storage → one tenant. Enforced by the FK chain; TenantScope
  binds from `device.tenant_id` at request time (PRD-02), failing closed.
- `status` enum (`App\Enums\DeviceStatus`) with TitleCase keys per PHP rules.
- `registers` and `devices` are themselves syncable-ish (a device pulls its own register and its
  siblings in the store), so both carry `public_id` and emit change-log entries.

### 2.3 Per-register drawer

Today a cash drawer (`treasury_accounts` with `sale_point_id`) is **per sale-point storage**, and
`OpenPosSessionAction`/`ProcessPosCheckoutAction` look it up by `sale_point_id`. Multi-device
requires **per-register drawers** (roadmap #5). Design:

- Add `treasury_accounts.register_id` (nullable FK). A register's cash drawer is the treasury
  account with that `register_id`.
- The existing single per-sale-point cash drawer is backfilled to `R0`'s register_id (cloud
  drawer). New device registers get their own cash drawer provisioned at enrollment (PRD-02).
- POS lookups change from `where('sale_point_id', …)` to `where('register_id', …)` **only inside
  the sync/offline path**; the cloud-web path continues to resolve `R0`'s drawer, so web behavior
  is unchanged. This is a small, additive column in Phase 0.
- *Ratified amendment (review):* the lookup switch ships as a shared **`DrawerResolver`** used by
  the checkout **and** session open/close Actions (register-based in sync/local contexts,
  sale-point-based for cloud web), landing in the cloud Actions rather than push-handler-private
  code — the local runtime and the push replay both consume it (Design 03 D4, Design 04 §8.1).

`treasury_transfers`/cross-register reconciliation stay out of MVP (each register writes only its
own drawer → no cross-register treasury conflict by construction, PRD-02 FR-17).

---

## 3. Serial numbering (FR-7..10)

### 3.1 Format

`{INV|RET}-{SA|SU}-{yy}-{code}-{seq}` — e.g. `INV-SA-26-R2-00145`, `RET-SA-26-R0-00007`.
`seq` is zero-padded to 5 (backstop; grows if exceeded). `code` is the register code.

### 3.2 Counter table

```php
Schema::create('register_serials', function (Blueprint $table) {
    $table->id();
    $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
    $table->foreignId('register_id')->constrained('registers');
    $table->string('series', 8);      // 'INV-SA','INV-SU','RET-SA','RET-SU'
    $table->unsignedSmallInteger('year'); // 2-digit or 4-digit; store 4-digit
    $table->unsignedBigInteger('last_seq')->default(0);
    $table->timestamps();
    $table->unique(['tenant_id', 'register_id', 'series', 'year']);
});
```

### 3.3 Allocation (inside the creating transaction)

A `SerialNumberGenerator` service, called from a **replacement for `InvoiceObserver`** (the
observer's PK-based generation is removed):

```php
public function next(Register $register, string $series, int $year): int
{
    return DB::transaction(function () use ($register, $series, $year) {
        $row = DB::table('register_serials')
            ->where(['tenant_id' => $register->tenant_id, 'register_id' => $register->id,
                     'series' => $series, 'year' => $year])
            ->lockForUpdate()->first();

        if (! $row) {
            DB::table('register_serials')->insert([...]+['last_seq' => 1]);
            return 1;
        }
        DB::table('register_serials')->where('id', $row->id)->update(['last_seq' => $row->last_seq + 1]);
        return $row->last_seq + 1;
    });
}
```

The serial string is assembled and written to `invoices.serial_number` **during** invoice
creation (inside `ProcessPosCheckoutAction` / `StoreInvoiceAction`), not in an after-`created`
observer, so it is set atomically and never null. Invoices gain a `register_id` column
(nullable FK; cloud fills `R0`, devices fill their own).

### 3.4 Concurrency & correctness

- **Cloud vs device never contend on the same register.** A physical register is owned by exactly
  one writer: `R0` is cloud-only (no device binds to it); `R1..Rn` each bind to exactly one
  device. So cross-node coordination is unnecessary — the counter is single-writer per register.
- **Within a node**, concurrent cloud web cashiers all hit `R0`; `lockForUpdate` on the
  `register_serials` row serializes them. On a device, a single POS process is the only writer.
- **Year rollover**: keyed by `year`; the first sale of a new year inserts `(year, seq=1)`. No
  reset logic — a fresh row per year.
- **Uniqueness backstop**: `invoices` keeps `unique(tenant_id, serial_number)` (FR-9). By
  construction collisions can't happen; the constraint catches bugs.

### 3.5 Legacy serials & quotes (FR-10)

- Old rows keep `INV-SA-26-{id}`; not renumbered. Search/lookup already matches
  `serial_number` as a plain string (`Invoice::$searchable`), so both formats match with no code
  change.
- **Quotes stay cloud-only numbering.** MVP offline scope is POS + expenses (no quotes offline),
  so `QuoteObserver` (`Q-YY-{id}`) is left as-is and documented as cloud-only. If quotes become
  offline-creatable (Phase 4), migrate them to `Q-{yy}-{code}-{seq}` via the same generator.

### 3.6 Rejected alternatives

- **Serial from a global sequence assigned on sync** — would make the offline-printed number
  provisional and require renumbering; violates FR-8 (number is final on the receipt). Rejected
  per settled decision #2.
- **UUID-in-serial** — unreadable on a receipt; humans need short running numbers. Rejected.

---

## 4. Change log — the same-transaction guarantee (FR-11, FR-13)

### 4.1 Table

```php
Schema::create('change_log', function (Blueprint $table) {
    $table->id();                                   // global monotonic; not the cursor
    $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
    $table->unsignedBigInteger('seq');              // per-tenant cursor (see 4.3)
    $table->string('table_name', 64);
    $table->char('public_id', 26);                  // identity of the changed row
    $table->string('operation', 8);                 // create|update|delete
    $table->foreignId('source_device_id')->nullable()->constrained('devices');
    $table->foreignId('actor_user_id')->nullable();
    $table->timestamp('changed_at');
    $table->unique(['tenant_id', 'seq']);
    $table->index(['tenant_id', 'seq']);            // pull: where tenant_id=? and seq>? order by seq
    $table->index(['tenant_id', 'table_name', 'public_id']); // compaction
});
```

**Entries carry identity only, no row payload.** Pull (PRD-02) resolves the current payload live
from the table by `public_id`; deletes are emitted as tombstones (identity + `operation=delete`).
Rationale: avoids duplicating/growing payloads, makes "latest wins" trivial (fetch current row),
and compaction is a simple collapse to max `seq` per `(table_name, public_id)`.

### 4.2 Capture mechanism (D7) — how NO change escapes the log

The problem (from §0.3): most writes are Eloquent, but `stocks`, `products.average_cost`,
`quote_items`, `stock_transfer_lines`, and a `transactions` mass-delete are **raw**. A single
mechanism cannot portably catch both cheaply. We use **two explicit channels + a build-enforced
invariant**:

**Channel A — Eloquent (`RecordsChanges` trait on `BaseModel`).**

```php
trait RecordsChanges
{
    public static function bootRecordsChanges(): void
    {
        static::created(fn ($m)  => ChangeLog::record($m->getTable(), $m->public_id, 'create', $m->tenant_id));
        static::updated(fn ($m)  => ChangeLog::record($m->getTable(), $m->public_id, 'update', $m->tenant_id));
        static::deleted(function ($m) {
            // SoftDeletes 'deleted' fires on soft delete too → treat both as a syncable delete/tombstone
            ChangeLog::record($m->getTable(), $m->public_id, 'delete', $m->tenant_id);
        });
        static::restored(fn ($m) => ChangeLog::record($m->getTable(), $m->public_id, 'update', $m->tenant_id));
    }
}
```

These events fire **synchronously inside** the surrounding `DB::transaction` (all Action writes
are wrapped), so the `change_log` insert commits atomically with the change. Soft-delete emits a
`delete` (tombstone) — correct: the device should stop showing the row.

**Channel B — explicit `ChangeLog::record()` at the finite raw sites.** PRD-01 patches exactly
these, each already (or newly) inside a transaction:

| Site | Table | Action in PRD-01 |
|---|---|---|
| `Storage::addStock/deductStock/setStockTo` | `stocks` | mint `public_id` on raw insert; call `ChangeLog::record('stocks', $publicId, …)` after the raw mutation |
| `Product::recalculateAverageCost` | `products` | `ChangeLog::record('products', $product->public_id, 'update', …)` (or route via model `update`) |
| `StoreQuoteAction`/`UpdateQuoteAction` `quote_items` | `quote_items` | convert relation `->insert()`/`->delete()` to `createMany()` / model deletes (fires Channel A) — behavior-neutral |
| `StockTransfersController::store` lines | `stock_transfer_lines` | convert `->insert()` to `createMany()` |
| `UpdateInvoiceAction::replaceTransactions` | `transactions` | replace mass `->delete()` with per-model `delete()` (or explicit tombstone records) |

Where conversion to Eloquent is behavior-neutral (quotes, transfer lines, transaction delete),
prefer it — it moves the site to Channel A and shrinks Channel B to essentially just `stocks` and
`products.average_cost` (the two genuinely hot/raw paths). The three transaction-less actions
(`StoreQuoteAction`, `UpdateQuoteAction`, `UpdateExpenseAction`) are wrapped in `DB::transaction`.

**The guarantee (enforcement).** A Pest **architecture test** asserts the invariant:

> No source file outside an explicit allow-list contains a mutating query-builder call
> (`DB::table('<syncable>')->insert|update|delete|increment|decrement|insertOrIgnore`, or a
> relationship `->insert(`/`->delete(`) against a syncable table.

The allow-list is exactly the Channel-B sites, each of which is covered by an adjacent
`ChangeLog::record`. CI fails if a new raw write to a syncable table appears anywhere else. This
converts "we believe nothing escapes" into a compile-time-ish invariant and is the concrete
mechanism that satisfies FR-13. A companion feature test asserts that a sample of each syncable
write (via every Action) produces exactly one `change_log` row with the next `seq`.

### 4.3 Per-tenant `seq` allocation — why not the auto-increment id

The cursor must guarantee: *a consumer that has seen up to `seq = N` will never later discover a
change with `seq < N`.* A global auto-increment `id` **cannot** guarantee this: ids are assigned
at insert but transactions commit out of order, so a reader can observe id=101 (committed) while
id=100 is still in-flight, advance its cursor past 101, and miss 100 when it commits. That is a
silently-dropped change.

**Decision:** allocate `seq` from a **per-tenant locked counter**:

```php
public static function record(string $table, string $publicId, string $op, int $tenantId): void
{
    $seq = DB::table('tenant_sync_state')->where('tenant_id', $tenantId)
        ->lockForUpdate()->value('next_seq');            // row created lazily / at tenant creation
    DB::table('tenant_sync_state')->where('tenant_id', $tenantId)->update(['next_seq' => $seq + 1]);
    DB::table('change_log')->insert([...]+['seq' => $seq]);
}
```

`tenant_sync_state (tenant_id PK, next_seq bigint)`. The `lockForUpdate` is held until the
surrounding transaction commits, so change-log appends **serialize per tenant** — later `seq`
values cannot become visible before earlier ones. Cursor skipping is therefore impossible. This
directly answers PRD-01 open question §7.1.

Throughput: this serializes only *writes within one tenant* (a single POS store), which are low
volume; different tenants never contend (separate rows). Acceptable.

**Lock-ordering discipline (review finding).** The tenant counter lock is held until commit, so
it interleaves with business-row locks (`stocks`, `register_serials`, invoice rows). If one code
path locks a stock row and then records a change (counter lock), while a concurrent path records
first and then locks stock, MySQL/pgsql can deadlock. Implementation rule: acquire the
`tenant_sync_state` row lock **first**, at the top of every syncable-write transaction (a
`ChangeLog::lockTenant()` helper called by the Action/transaction wrapper), before any
business-row lock. Global lock order becomes tenant-counter → business rows — deadlock-free —
and it merely makes explicit the per-tenant write serialization this design already accepts.
The change-log architecture test should also assert this ordering convention.

### 4.4 Retention / compaction (open question §7.1)

- **Compaction job** (scheduled): for each tenant, collapse superseded entries — keep only the
  max `seq` per `(table_name, public_id)` among entries **below the minimum active device
  cursor** (`min(devices.last_acked_seq)`), and delete the rest. A device that never fell behind
  loses nothing; the current state is always resolvable live.
- Tombstones for `public_id`s that predate every device's first snapshot can be pruned.
- Never compact above the minimum device cursor (a lagging device still needs the run).

### 4.5 Rejected alternatives

- **DB triggers writing the log** — not portable across mysql/pgsql/sqlite (CI runs sqlite
  `:memory:`), can't capture `source_device_id`/actor context, and are invisible to code review.
  Rejected.
- **A DB query listener parsing SQL** — brittle table/PK extraction, fires for reads too, no
  clean tenant/public_id mapping. Rejected.
- **Global auto-increment `id` as the cursor** — the commit-reorder gap above silently drops
  changes. Rejected.
- **Payload stored inline in the log** — bloats the table, duplicates truth, complicates
  "latest wins"; fetch-live is simpler. Rejected (tombstones excepted — they *are* the payload).

---

## 5. Deletions — per-table decision (FR-12)

**Uniform policy (D8): tombstone-via-changelog for every table; no table is converted to
soft-delete for sync alone.** A `delete` change-log entry (Channel A on `deleted`, or Channel B
explicit) is the tombstone; the client removes the row by `public_id`. Rationale: minimal schema
churn, keeps append-only ledgers append-only, and one client apply path.

Enumerated decision for the 12 hard-deleting syncable tables:

| Table | Deleted how today | Sync treatment |
|---|---|---|
| `stock_movements` | Eloquent (rare) | Tombstone via Channel A. Ledger; effectively never deleted. |
| `treasury_movements` | Eloquent | Tombstone via Channel A. Append-only ledger. |
| `treasury_transfers` | Eloquent | Tombstone via Channel A. Out of MVP write scope. |
| `stock_transfers` | Eloquent | Tombstone via Channel A. |
| `stock_transfer_lines` | raw `->insert`; delete via cascade | Convert writes to Eloquent (Channel A); **client mirrors the `stock_transfers→lines` cascade locally**. |
| `adjustments` | Eloquent `create`; rarely deleted | Tombstone via Channel A. |
| `pos_sessions` | Eloquent; effectively never deleted | Tombstone via Channel A. |
| `transaction_receipts` | Eloquent | Tombstone via Channel A. |
| `categories` | Eloquent | Tombstone via Channel A. |
| `quote_items` | raw; delete via cascade | Convert to Eloquent; client mirrors `quotes→items` cascade. Cloud-only in MVP. |
| `preferences` | Eloquent (key/value) | Tombstone via Channel A. |
| `stocks` | raw only; not deleted in practice | Channel B explicit; a stock row is upserted, never deleted, so a delete is a no-op case. |

**FK cascade caveat.** 24 migrations declare `cascadeOnDelete`. A DB cascade deletes children
without firing Eloquent events → escapes both channels. Mitigation: **the client mirrors
parent→child cascades locally** — when a tombstone for a parent arrives, it removes the parent's
children. Only two parent→child pairs matter for syncable data with hard-deleting children:
`stock_transfers → stock_transfer_lines` and `quotes → quote_items`. All other syncable parents
(invoices, customers, products, storages…) **soft-delete**, so no DB cascade fires for them. This
is documented for PRD-02/03 rather than reworking every FK. `payments` (soft-delete, per §0.1)
needs no special handling.

---

## 6. Idempotency (FR-14, FR-15)

### 6.1 Central table

```php
Schema::create('sync_idempotency', function (Blueprint $table) {
    $table->id();
    $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
    $table->foreignId('device_id')->nullable()->constrained('devices');
    $table->string('idempotency_key');            // client-generated (ULID-based, see sync-lib)
    $table->string('mutation_type', 40);          // 'sale.create', 'expense.create', ...
    $table->char('result_public_id', 26)->nullable(); // the entity the mutation produced
    $table->string('status', 12);                 // applied|rejected
    $table->json('result')->nullable();           // serialized MutationResult for exact replay
    $table->timestamps();
    $table->unique(['tenant_id', 'idempotency_key']);
});
```

### 6.2 How it is used

The PRD-02 push handler wraps every mutation:

1. `SELECT ... FOR UPDATE` on `(tenant_id, idempotency_key)`.
2. If a row exists → return its stored `result` verbatim, **perform zero writes** (no Action
   invoked → no duplicate change-log entries, no duplicate stock/treasury movements). Satisfies
   FR-15 exactly.
3. Else run the Action inside the transaction, then insert the idempotency row with the result.

Because the check and the write share one transaction and the unique index is the arbiter, a
concurrent duplicate loses on the unique constraint and is retried into branch (2).

### 6.3 Why central, not per-table

- A `sale.create` mutation writes invoice + lines + payment + stock + treasury as **one atomic
  unit**; a per-table `idempotency_key` column can't represent "this whole mutation already ran."
- `pos_session.open` and `pos_session.close` are two mutations on the same row; distinct keys in
  one table handle both cleanly.
- Generalizes to all five MVP mutation types with one mechanism.

The existing `invoices.idempotency_key` + `ProcessPosCheckoutAction` replay is **left untouched**
so the current web POS behavior does not change; the sync path additionally records the central
key. (The sale action can still set `invoices.idempotency_key` for continuity.)

### 6.4 Rejected alternative

- **Per-table `idempotency_key` columns everywhere** — cannot express multi-table atomic
  mutations, forces N migrations, and duplicates the replay logic N times. Rejected.

---

## 7. Packaging spike (FR-16)

Time-boxed spike, delivered as a written verdict at
`docs/offline/design/packaging-spike-verdict.md`. Checklist:

- Boot the app under NativePHP (desktop) on SQLite; run `php artisan migrate` clean.
- Bind a single tenant via the existing `app()->instance('currentTenant', …)` pattern (proven in
  `GenerateExportJob::bindTenantContext`).
- Render the POS session page (`Pos/Session`) end-to-end offline.
- Enumerate incompatibilities: Horizon (Redis), Reverb (websockets), Telescope, scheduler/cron,
  Excel exports, any `ilike` usage (pgsql-ism — SQLite needs `like`; POS product search in
  `PosSessionController` uses `ilike` and must be driver-branched), broadcasting in
  `ExportStatusUpdated`, and Redis cache/session drivers.
- Verdict: works / works-with-changes / blocked, with the incompatibility list. This is a spike,
  not a blocker for the schema work.

Found incompatibility to flag now: `PosSessionController` uses `where('name','ilike',…)` — must
become driver-aware (`like` on sqlite/mysql) for the local runtime.

---

## 8. sync-lib package boundary (roadmap #6)

**`namain/sync-protocol`** — composer package in its own repo, consumed by both the cloud app and
the NativePHP client.

- **Namespace:** `NamaIn\SyncProtocol\`
- **PHP floor:** `^8.4` (matches the cloud app and the NativePHP client runtime).
- **Dependencies:** none beyond `symfony/uid` (ULID) — **no Laravel, no DB**. Pure PHP so both a
  Laravel host and a lean client can install it.

**Lives in the package (the wire contract):**

- Path + version constants: `SyncProtocol::BASE = '/api/sync/v1'`, `SyncProtocol::VERSION = 1`.
- Enums: `MutationType`, `Operation` (create/update/delete), `RejectionReason`,
  `MutationOutcome` (applied/already_applied/rejected), `DeviceStatus` (shared with cloud).
- DTOs / envelopes (array ⇄ object codecs, symmetric on both sides): `PushEnvelope`, `Mutation`,
  `MutationResult`, `PullResponse`, `ChangeEntry`, `SnapshotManifest`, `ProvisionRequest`,
  `ProvisionResponse`, `SyncError`.
- Rules/helpers: `IdempotencyKey` (format + generator), `Ulid` (generate/validate),
  the money-on-the-wire convention (integer minor units — DTO money fields are typed `int`).

**Stays in the cloud app (this repo):** endpoints/controllers, the push handler mapping mutations
→ Action classes, the `public_id → int` boundary resolver, the `change_log` producer, the
snapshot generator, Sanctum abilities/guard, rate limiting, `SerialNumberGenerator`, migrations.

**Stays in the offline client (PRD-03 repo):** the stateful sync engine (outbox persistence,
scheduling, retry/backoff, cursor storage), the HTTP transport, the NativePHP shell, and local
UI. The package additionally offers a **transport-agnostic client core** (request builders +
response parsers) that the client injects a transport into — but the cloud consumer never
imports client I/O. This honors decision #6's "client sync engine" while respecting the
dependency rule: the shared package stays pure; each app owns its I/O.

Detailed DTO shapes are specified in [Design 02 §9](02-sync-protocol.md).

---

## 9. Answers to PRD-01 open questions (§7)

1. **Change-log storage / retention.** One `change_log` table; per-tenant monotonic `seq` from a
   locked `tenant_sync_state` counter (§4.3); identity-only entries with live payload resolution;
   scheduled compaction bounded by the minimum active device cursor (§4.4). Not partitioned —
   volume per tenant is low; a `(tenant_id, seq)` index suffices.
2. **Soft-delete vs tombstone per table.** Tombstone-via-changelog for all (§5). No table becomes
   soft-delete for sync; `payments` is already soft-delete (PRD assumption corrected).
3. **Do users/roles/permissions sync?** Yes — needed for local login and `can:` checks. In this
   PRD they gain `public_id` (schema). Their *projection into the snapshot* is PRD-02 (§4.4 of
   Design 02). `permissions` are global reference data; `roles` are tenant-scoped; the tenant's
   `users` and their `tenant_user` pivot rows are projected per device.
4. **Register code format/assignment.** System-assigned `R{n}`, unique per tenant, immutable;
   reserved `R0` = cloud. Users may set a friendly `label` but never the code (§2.1).

---

## 10. Inputs for other design docs

- **Design 02 (protocol):** consumes `public_id`, `change_log(seq)`, `sync_idempotency`,
  `registers`/`devices`/`register_serials`, the `SerialNumberGenerator`, and the DTO list in §8.
  It owns the `public_id → int` resolver, snapshot projection (incl. users/roles/permissions),
  Sanctum abilities, and the per-entity pull scope matrix.
- **PRD-03 (client):** the sync-lib client-core boundary (§8); the client must **mirror FK
  cascades** for `stock_transfers→lines` and `quotes→items` (§5); local login uses cached bcrypt
  hashes from the snapshot (decided in Design 02 §7, flagged here because it depends on `users`
  carrying `public_id` + password hash in the projection).
- **PRD-04 (reconciliation):** oversell + credit-breach record shapes are defined in Design 02;
  device lifecycle uses `devices.status`/`last_*` columns from §2.2.

---

## 11. Implementation notes (suggested PR slicing)

1. **PR-1 `HasPublicId` + backfill (identity).** Trait, per-table nullable column, chunked
   backfill, then non-null + unique. Include `users/roles/permissions/tenants`. Tests: new web
   rows get a ULID; 100% backfill; all existing tests green.
2. **PR-2 registers + devices + drawer column.** Tables, enums, `R0` seed, `treasury_accounts.register_id`,
   backfill drawer→R0. No behavior change.
3. **PR-3 serials.** `register_serials`, `SerialNumberGenerator`, `invoices.register_id`, move
   generation out of `InvoiceObserver` into the create path. Tests: two registers same year,
   year rollover, legacy lookup still matches (FR-7/8/9).
4. **PR-4 change log.** `change_log` + `tenant_sync_state`, `ChangeLog::record`, `RecordsChanges`
   trait, Channel-B patches + Eloquent conversions, wrap the three transaction-less actions, and
   the **architecture test**. Tests: every Action write logs exactly once with the next seq; soft
   + hard delete both produce a tombstone; concurrent-tenant seq monotonicity.
5. **PR-5 idempotency.** `sync_idempotency` table + the wrap helper (exercised fully in PRD-02).
   Tests: replay produces one record and zero extra change-log rows.
6. **PR-6 packaging spike** (parallel, independent) → verdict doc.

PRs 1→4 are ordered (later ones depend on `public_id` + registers); PR-5 and PR-6 are
independent. Every PR keeps the web app behavior identical except the serial format (PR-3).

---

## Implementation deviations (Phase 0, shipped 2026-07-04)

Recorded during implementation on `feat/offline-sync-foundations`
(`cb10e91..192f42c`). None weaken a settled decision; each is noted with why.

1. **Serial generation is centralized in `Invoice`'s `creating` hook**, not
   duplicated inside the three invoice-creating Actions (§3.3 sketch). Strictly
   stronger: every creation site (POS checkout, store, inverse, factories,
   seeders) gets the serial atomically in the same insert, and it can never be
   null. A pre-set serial is respected (legacy imports, factories); an explicit
   `register_id` (device path, Phase 1+) wins over the R0 default.
2. **`RecordsChanges` registers events via `registerModelEvent()`** instead of
   the `static::restored(...)` static-helper form shown in §4.2 — the magic
   static re-enters model booting on Laravel 13 and throws a `LogicException`.
   Behavior is identical.
3. **`HasPublicId` and `ChangeLog` tolerate their columns/tables not existing
   yet** (memoized `Schema::hasColumn`/`hasTable` guards). Historical migrations
   run seeders that create models before the sync schema exists; the guards make
   `migrate:fresh` order-safe. Steady-state cost is zero (positives are cached).
4. **`Tenant::created` provisions `tenant_sync_state` and the R0 register** for
   tenants created after the seed migrations (onboarding flows through
   `ProvisionTenantAction` → `Tenant::create`). The counter is inserted before
   R0 because creating R0 already emits a change-log entry.
5. **The `namain/sync-protocol` package (D10/§8) was not started in Phase 0** —
   no Phase-0 slice consumes it. `DeviceStatus` lives in `App\Enums` for now and
   moves to the package when Phase 1 extracts the wire contract.
6. **PHP 8.5 note:** `config/database.php`'s `PDO::MYSQL_ATTR_SSL_CA` deprecation
   breaks header flushing under `php -S` (see the packaging-spike verdict). The
   guard ships as its own PR, not on the Phase-0 branch.
7. **Behavioural changes ship behind the `offline_sync` entitlement**
   (added 2026-07-22, after rebase onto the feature-gating engine). Register-scoped
   serials and change-log capture activate per tenant via
   `Feature::OfflineSync` (plan value or admin override; default **off**, including
   for unconfigured tenants — it is a rollout flag, not a sellable capability, so
   it opts out of the "unconfigured grants everything" fallback). With the flag
   off, invoices keep the legacy PK-based serial from the restored
   `InvoiceObserver` and no change-log rows are written. Schema groundwork —
   `public_id` minting, registers/devices, R0 provisioning, `sync_idempotency` —
   stays unconditional so enabling a tenant needs no backfill.
8. **The change log records only the FR-1 syncable set**
   (`ChangeLog::SYNCABLE_TABLES`, shared with the architecture test). Tables that
   carry a `public_id` but are outside the set (registers, devices) are never
   recorded; Phase 1's cursor never serves rows a device schema cannot hold.
