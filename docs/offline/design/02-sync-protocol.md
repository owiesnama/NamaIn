# Design 02 — Sync Protocol & Cloud API (PRD-02)

> Status: Design for review · Owner: server-side offline · Implements: [PRD-02](../prds/prd-02-sync-protocol-api.md) · Phase 1
>
> Depends on [Design 01](01-sync-foundations.md): `public_id`, `change_log(seq)`,
> `sync_idempotency`, `registers`/`devices`/`register_serials`, `SerialNumberGenerator`,
> and the `namain/sync-protocol` package.
>
> All endpoints live under **`/api/sync/v1`** in a new `routes/sync.php` (registered in
> `bootstrap/app.php`), on a dedicated `auth:sync` guard. `routes/api.php` stays otherwise empty.

---

## Decisions at a glance

| # | Area | Decision | PRD FR |
|---|---|---|---|
| P1 | Auth model | Sanctum token whose **tokenable is the `Device`** (not a `User`). New `sync` guard resolves tenant from `device.tenant_id`; TenantScope binds it and fails closed. | FR-1..3 |
| P2 | Token abilities | Abilities `sync:snapshot`, `sync:pull`, `sync:push`, `sync:attach`. No web-route access; revoke = delete device tokens (immediate 401). | FR-2 |
| P3 | Provisioning | Web user with `devices.manage` mints a device record + one-time pairing code; device exchanges it at `POST /provision` for its token + identity + drawer. | FR-1 |
| P4 | Snapshot format | **JSONL per entity, gzipped in one archive + a manifest** — not a SQLite file. Driver-neutral, same shape as pull, applied through the client's own migrations. | FR-4..6, §7.1 |
| P5 | Snapshot delivery | Queued job (reuse `GenerateExportJob`/backup patterns) → poll for readiness → ranged file download. Carries the `seq` cursor it was taken at. | FR-6 |
| P6 | Pull | `GET /pull?cursor=&limit=` returns ordered `change_log` entries with **live payloads**, public_id-keyed, scope-filtered, paged, plus `next_cursor`. | FR-7..10 |
| P7 | Pull scope | Reference/catalog/users/roles: **tenant-wide**. Stock levels: **tenant-wide read** (powers replenishment hints). Transactional records (invoices/payments/sessions/expenses/treasury): **scoped to the device's register/storage**. | FR-10, §7.3 |
| P8 | Push | `POST /push`: ordered mutation array, applied **synchronously** in the request, each mutation its own transaction, per-mutation result. Five MVP mutation types. | FR-11..16, §7.2 |
| P9 | `sale.create` | ONE atomic mutation → replayed through a `ReplayPosSaleAction` wrapping the existing checkout/stock/payment Actions; serial allocated per the register. | FR-12 |
| P10 | Oversell | Recorded-never-rejected: force-deduct (stock may go negative) + `oversell_reconciliations` row. `InsufficientStockException` never fails a push. | FR-13 |
| P11 | Credit breach | Recorded as `credit_breach_flags`; never rejected. | FR-14 |
| P12 | FK boundary | A `PublicIdResolver` maps every incoming `public_id` → int id within tenant scope; unknown refs → per-mutation retriable `unknown_reference`. Money stays integer minor units. | FR-8, FR-15 |
| P13 | Local auth | **Cached bcrypt hashes** from the snapshot; `Hash::check` offline; roles/permissions cached for `can:`. (Decided for PRD-03.) | §7.5 |
| P14 | Attachments | Separate `POST /attachments` upload, referenced by `public_id`; not inline in push JSON. | §7.4 |
| P15 | Versioning | Client sends `X-Sync-Protocol` + `X-App-Version`; server can answer `426 upgrade_required` as a first-class status. | FR-20 |

---

## 1. Auth, guard & provisioning (FR-1..3)

### 1.1 The device token and `sync` guard

`Device` (from Design 01 §2.2) uses `Laravel\Sanctum\HasApiTokens`. The token's **tokenable is the
device**, so the authenticated principal on the sync API is a device, not a user. A new guard:

```php
// config/auth.php
'guards' => [
    // ...existing web/admin...
    'sync' => ['driver' => 'sanctum', 'provider' => 'devices'],
],
'providers' => [
    // ...
    'devices' => ['driver' => 'eloquent', 'model' => App\Models\Device::class],
],
```

Middleware on every `/api/sync/v1` route:

1. `auth:sync` — resolves the `Device` from the bearer token or 401s.
2. `EnsureDeviceActive` — rejects non-active devices: **`403 { "error": "device_revoked" }`**
   for revoked devices (a first-class status the client wipes on — Design 04 §4.2), plain `401`
   for unknown/expired tokens (immediate revocation; FR-2).
3. `BindDeviceTenant` — `app()->instance('currentTenant', $device->tenant)`; sets locale from the
   tenant's preferences (mirrors `GenerateExportJob::bindTenantContext`). Every downstream query
   is then TenantScope-bound and **fails closed** to `1=0` if this ever unsets (Design 01 §0).
4. `token->can('<ability>')` gate per endpoint (P2).

Because the guard, provider, and middleware group are disjoint from `web`, a device token cannot
authenticate a web/Inertia route, and cross-tenant reads are impossible (tenant comes from the
device row, not the request). FR-2/FR-3 satisfied. Every push records `source_device_id`
(Design 01 change_log + per-row where applicable).

### 1.2 Separation of concerns: device authenticates the channel, user attributes the work

Actions need a `User` actor (`created_by`, `opened_by`, `approved_by`). The device token does not
supply one. Instead **each mutation payload carries the acting user's `public_id`** (the cashier
who rang the offline sale). The push handler resolves it to a local `User` (must belong to the
tenant via `tenant_user`) and passes it as the actor. Tenant = device; actor = mutation data.

### 1.3 Provisioning flow (FR-1)

```
POST  /api/sync/v1/provision            (unauthenticated — pairing code is the credential)
```

Enrollment is initiated in the **web app** (not this API): a user with the new `devices.manage`
permission creates a `Device` (status `pending`), picks/creates its `Register` and cash drawer,
and the server generates a **one-time pairing code** (random, shown once), storing only
`hash('sha256', code)` in `devices.pairing_code_hash` with `pairing_expires_at` (e.g. +15 min).

The device calls `POST /provision`:

**Request**
```json
{ "pairing_code": "K7QMP-3F9A2", "device_name": "Front counter", "app_version": "1.0.0" }
```

**Response 201**
```json
{
  "token": "42|Xk...plaintext-sanctum-token...",
  "device":   { "public_id": "01j...", "name": "Front counter", "status": "active" },
  "register": { "public_id": "01j...", "code": "R2", "label": "Counter 2" },
  "storage":  { "public_id": "01j...", "name": "Main Store", "type": "sale_point" },
  "tenant":   { "public_id": "01j...", "name": "Acme" },
  "drawer":   { "public_id": "01j...", "type": "cash" },
  "cursor": 0,
  "protocol": 1
}
```

Server: validates the code (constant-time compare, unexpired, unused), sets device `active`,
clears the pairing hash, mints the Sanctum token with abilities P2, returns identity + initial
`cursor: 0`. Errors: `410 pairing_expired`, `409 already_provisioned`, `422 invalid_pairing_code`.

---

## 2. Snapshot — bootstrap (FR-4..6)

### 2.1 Format decision (P4, resolves §7.1): JSONL, not a SQLite file

**JSONL per entity**, each line one row as a public_id-keyed JSON object, gzipped, bundled in a
single archive (`tar.gz`) with a `manifest.json`. Rationale:

- **Driver-neutral.** The cloud runs MySQL/pgsql; producing a SQLite file server-side would need a
  parallel SQLite build with schema parity — fragile. JSONL is trivially produced from any driver.
- **Schema ownership stays with the client.** The device applies snapshot rows through **its own
  migrations and Eloquent casts** (correct indexes, ULIDs, MoneyCast, future local migrations),
  instead of adopting a foreign binary that can drift from the app's schema.
- **One apply path.** A snapshot row and a pull payload have the **same shape** (§6.3), so the
  client has a single upsert routine for bootstrap and incremental sync.
- **Money is JSON-safe** as integer minor units (no float hazard).

Cost: importing thousands of rows is slower than attaching a prebuilt DB — acceptable for a
one-time bootstrap. **Rejected: SQLite file** — schema drift, binary coupling, no SQLite in cloud
prod.

### 2.2 Contents (FR-5) & scope

Manifest declares the cursor and per-entity file list:

```json
{
  "snapshot_id": "01j...",
  "tenant": "01j...",
  "register": "R2",
  "storage": "01j...",
  "cursor": 148213,             // change_log seq at snapshot time — first pull continues from here
  "protocol": 1,
  "entities": [
    { "table": "products",   "file": "products.jsonl.gz",   "count": 812 },
    { "table": "units",      "file": "units.jsonl.gz",      "count": 40 },
    { "table": "stocks",     "file": "stocks.jsonl.gz",     "count": 2100 },
    { "table": "users",      "file": "users.jsonl.gz",      "count": 9 }
    // ...
  ]
}
```

Entities (minimum): products, units, categories (+ categorizables projected), stocks
(tenant-wide, P7), customers + customer_advances (+ computed balances), suppliers (read-only ref),
storages, registers (the store's), **the register's `register_serials` rows** (so a replacement
device resumes the serial sequence without collision — Design 04 §4.3; absent for a brand-new
register, which starts at 1), the register's cash drawer treasury_account, preferences,
**users + roles + permissions + permission_role + the tenant's tenant_user rows** (for local login
+ `can:`; Design 01 §9.3), and — if not bundled in the app binary — the en/ar translation
catalogs. Every row is public_id-keyed; all FKs are emitted as the referenced row's `public_id`.

**Consistency (FR-4).** The snapshot is taken at a single `change_log.seq` watermark: the job
reads `tenant_sync_state.next_seq - 1` as the cursor and exports committed rows as of that point.
Because change-log appends serialize per tenant (Design 01 §4.3), "rows with seq ≤ cursor" is a
consistent cut; the first pull with `cursor = manifest.cursor` continues seamlessly with no gap
and no double-apply.

### 2.3 Delivery (FR-6)

```
POST /api/sync/v1/snapshot            ability: sync:snapshot   → 202 { "snapshot_id", "status":"queued" }
GET  /api/sync/v1/snapshot/{id}       ability: sync:snapshot   → 200 { "status":"ready", "download_url", "manifest_url", "size", "cursor" } | { "status":"processing" }
GET  /api/sync/v1/snapshot/{id}/download   ability: sync:snapshot   → 200 (file, supports HTTP Range for resumable download)
```

Generation is a queued job (`GenerateSnapshotJob`) reusing the tenant-binding + storage patterns
of `GenerateExportJob` / `BackupTenantAction`. Files are written to `local` disk under
`sync-snapshots/{tenant}/{snapshot_id}/`, streamed with `Accept-Ranges: bytes` for resume, and
GC'd after a TTL. Poll on `GET …/{id}`.

---

## 3. Pull — cloud → device (FR-7..10)

```
GET /api/sync/v1/pull?cursor={seq}&limit={n}     ability: sync:pull
```

**Response 200**
```json
{
  "changes": [
    { "seq": 148214, "table": "products",  "op": "update", "public_id": "01j...", "payload": { ...row... } },
    { "seq": 148215, "table": "stocks",    "op": "update", "public_id": "01j...", "payload": { "storage": "01j...", "product": "01j...", "quantity": 17 } },
    { "seq": 148217, "table": "customers", "op": "delete", "public_id": "01j...", "payload": null }
  ],
  "next_cursor": 148217,
  "has_more": true,
  "protocol": 1
}
```

- Server reads `change_log WHERE tenant_id = ? AND seq > cursor ORDER BY seq LIMIT n`, applies the
  **scope filter** (§4), **collapses** to the latest entry per `(table, public_id)` in the page
  (older superseded rows dropped), then resolves each surviving non-delete entry's **live payload**
  from its table by `public_id` (Design 01 §4.1). Deletes carry `payload: null` (tombstone).
- `next_cursor` is the max `seq` returned; `has_more` true if more remain. The **client owns the
  cursor**; the server records `devices.last_acked_seq` on each pull for observability/compaction
  (Design 01 §4.4) but does not gate on it (FR-7).
- All identity and all FKs in `payload` are `public_id`s (FR-8), translated at the boundary
  (§5.2). Money fields are integer minor units.
- Deletions arrive as tombstones the client applies, mirroring local FK cascades (Design 01 §5).

Idempotent by construction: re-pulling from an older cursor re-sends the same latest state; upsert
+ tombstone application is naturally idempotent on the client.

---

## 4. Pull scope matrix (P7, resolves §7.3)

A device receives only its tenant, and within it:

| Entity | Scope | Rationale |
|---|---|---|
| products, units, categories, suppliers, preferences | **Tenant-wide** | Reference/catalog; server-wins, pull-only. |
| customers, customer_advances | **Tenant-wide** | Any customer may buy at any register; credit checked against cached balances. |
| users, roles, permissions, permission_role, tenant_user | **Tenant-wide** (permissions global) | Local login + `can:` for every cashier. |
| storages, registers | **Tenant-wide** | POS UI shows sale points; replenishment sources are other storages. |
| **stocks** | **Tenant-wide (read-only)** | The POS UI's replenishment hints show other storages'/warehouses' levels. Device **writes** only its own storage's stock. |
| stock_movements | Storage-scoped (device's storage) | Ledger for the device's own storage. |
| invoices, transactions, transaction_receipts, payments | **Register/storage-scoped** | A device needs its own sales back (echo/reconcile), not other registers'. |
| pos_sessions | Register-scoped | The device's own sessions. |
| expenses | Register/storage-scoped | Expenses against the device's drawer. |
| treasury_accounts, treasury_movements | The register's drawer only | Each register writes only its own drawer (FR-17) → no cross-register data needed. |
| stock_transfers, stock_transfer_lines, adjustments, quotes, quote_items, cheques, banks, treasury_transfers, recurring_expenses | **Not pulled in MVP** | Out of offline scope; cloud-only. (Auto-replenishment transfers created server-side during a pushed sale surface only as the resulting stock delta.) |

**Resolved §7.3:** yes — devices see other storages' stock levels (tenant-wide `stocks`, read
only), because the existing replenishment-hint UX depends on it; they cannot write another
storage's stock.

Scope is enforced by joining each entity to the device's `storage_id`/`register_id` where the
matrix says "scoped," and left unfiltered (tenant-only) where "tenant-wide." The filter is applied
**after** reading change-log entries so scoped-out changes are simply skipped (they still advance
the cursor).

---

## 5. Push — device → cloud (FR-11..16)

```
POST /api/sync/v1/push        ability: sync:push
```

### 5.1 Envelope

**Request** (ordered array; batch cap 200 mutations, §7.2):
```json
{
  "protocol": 1,
  "app_version": "1.0.0",
  "mutations": [
    {
      "idempotency_key": "01j-device-ulid-...",
      "type": "customer.create",
      "public_id": "01jCUSTNEW...",
      "actor": "01jUSER...",
      "occurred_at": "2026-07-03T10:15:04Z",
      "payload": { "name": "Sara", "phone_number": "099...", "credit_limit": 500000 }
    },
    {
      "idempotency_key": "01j-device-ulid-...",
      "type": "sale.create",
      "public_id": "01jINVOICE...",
      "actor": "01jUSER...",
      "occurred_at": "2026-07-03T10:15:40Z",
      "payload": { ...see 5.4... }
    }
  ]
}
```

**Response 200** — one result per mutation, positionally aligned:
```json
{
  "results": [
    { "idempotency_key": "01j...", "outcome": "applied",         "public_id": "01jCUSTNEW...", "serial": null },
    { "idempotency_key": "01j...", "outcome": "applied",         "public_id": "01jINVOICE...", "serial": "INV-SA-26-R2-00146",
      "flags": { "oversell": [ { "product": "01j...", "oversold_qty": 3 } ], "credit_breach": false } }
  ],
  "protocol": 1
}
```

`outcome ∈ { applied, already_applied, rejected }`; `rejected` carries `reason` (a
`RejectionReason` code) + human message.

### 5.2 Processing (P8, synchronous — resolves §7.2)

Mutations are applied **in array order, each in its own `DB::transaction`**, so one rejection never
rolls back earlier successes (FR-11). Per mutation:

1. **Idempotency gate** (Design 01 §6.2): `SELECT … FOR UPDATE` on
   `(tenant_id, idempotency_key)`. If present → return stored result as `already_applied`, **zero
   writes** (FR-16/FR-18).
2. **Resolve references** via `PublicIdResolver` (tenant-scoped): actor user, customer, products,
   units, register, drawer. A public_id created earlier **in this same batch** is already committed
   (each mutation is its own transaction, applied in order) → resolvable. Unknown public_id →
   `rejected { reason: unknown_reference }` (retriable; the device re-pushes after the missing
   mutation lands) (FR-15).
3. **Dispatch** to the mutation handler (§5.4), reusing the existing Action classes so serials,
   stock movements, payments, and treasury movements match cloud-created sales (FR-12).
4. **Record** the `sync_idempotency` row with the serialized result.

**Why synchronous** (rejected async): MVP batches are small (a store's queued offline day); the
client contract is simplest (send → per-mutation results, incl. the final serial); `sale.create`
must run `ReplayPosSaleAction` inline in a transaction and return its serial immediately. Async
(accept-then-process) would add a job + status polling + a result-fetch endpoint and complicate
exactly-once. Revisit only if batch latency becomes a problem.

### 5.3 The five MVP mutation types

| `type` | Handler | Writes |
|---|---|---|
| `pos_session.open` | `OpenPosSessionAction` | pos_sessions, storages.active_session_id, treasury_movements (opening float) |
| `pos_session.close` | `ClosePosSessionAction` | pos_sessions, storages, treasury_movements (reconciliation) |
| `sale.create` | `ReplayPosSaleAction` (wraps `ProcessPosCheckoutAction`) | invoices (+serial), transactions, stocks, stock_movements, payments, treasury_movements |
| `customer.create` | `Customer::create` (tenant-bound) | customers |
| `expense.create` | `StoreExpenseAction` | expenses, treasury_movements |

Each handler already wraps its work in `DB::transaction`; the push wrapper nests inside the
per-mutation transaction (savepoint), and every write flows through Channel A/B → `change_log`
(Design 01 §4.2), so pushed rows are themselves pullable by other devices.

### 5.4 `sale.create` — one atomic mutation (FR-12)

```json
{
  "type": "sale.create",
  "public_id": "01jINVOICE...",
  "idempotency_key": "01j...",
  "actor": "01jUSER...",
  "occurred_at": "2026-07-03T10:15:40Z",
  "payload": {
    "session": "01jSESSION...",
    "register": "01jREGISTER...",
    "customer": "01jCUSTOMER...",          // null → walk-in (server firstOrCreate, same as web)
    "customer_type": "customer",           // customer|supplier
    "payment_method": "cash",              // cash|credit|cheque|bank_transfer
    "serial_number": "INV-SA-26-R2-00146", // FINAL, minted on the device (Design 01 §3); server stores as-is
    "total": 145000,                       // integer minor units
    "discount": 0,
    "items": [
      { "public_id": "01jTXN...", "product": "01jPROD...", "unit": "01jUNIT...", "quantity": 2, "price": 72500, "base_quantity": 2 }
    ],
    "payment": { "public_id": "01jPAY...", "amount": 145000, "method": "cash" }  // omitted for pure credit
  }
}
```

- The device mints the serial locally (final, printed on the receipt) and sends it. The server
  **stores it verbatim** (does not renumber — FR-8); the `register_serials` counter is the
  device's locally, so cloud does not re-allocate for device sales.
- *(Amended per Design 03 D1/D4, ratified in review.)* `ReplayPosSaleAction` is a **thin
  wrapper**: `ProcessPosCheckoutAction` gains a `CheckoutContext` parameter (register, stock
  policy, replenishment switch, optional preset serial/public_ids — Design 03 §5.2) so web,
  replay, and the local runtime are three contexts of one action. Replay passes: (a) the
  pre-minted serial and public_ids for invoice/lines/payment, so re-push reproduces identical
  rows; (b) `AllowNegative` stock policy with no auto-replenishment (§6); (c) the register's
  drawer via a shared `DrawerResolver` (`register_id`-based; also used by session open/close
  replay and the local runtime — Design 01 §2.3, Design 04 §2.3).
- Money crosses as integer minor units and is stored as-is (the app already stores minor units).

---

## 6. Oversell & credit breach (FR-13, FR-14)

### 6.1 Oversell — recorded, never rejected (P10)

Concurrent registers can sell the same stock past zero. On replay, if the device's storage is
short, the sale is **still recorded** (the customer already left with the goods): stock is
**force-deducted and allowed to go negative**, the `stock_movements` ledger records the (negative)
movement truthfully, and an `oversell_reconciliations` row is created.

Implementation: `ReplayPosSaleAction` uses a `forceDeduct` path on the stock engine (an
`allowNegative` flag added to a sync-only deduction helper; the existing `Storage::deductStock`
that throws `InsufficientStockException` is **unchanged** for the web path). When the pre-deduction
on-hand is less than the quantity, record the shortfall.

```php
Schema::create('oversell_reconciliations', function (Blueprint $table) {
    $table->id();
    $table->char('public_id', 26)->unique();
    $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
    $table->foreignId('device_id')->nullable()->constrained('devices');
    $table->foreignId('storage_id')->constrained('storages');
    $table->foreignId('product_id')->constrained('products');
    $table->foreignId('invoice_id')->constrained('invoices');
    $table->integer('oversold_qty');          // units sold beyond on-hand (positive)
    $table->integer('on_hand_before');
    $table->timestamp('occurred_at');
    $table->timestamps();
    // resolution lifecycle lives on PRD-04's reconciliation_items inbox (single source of
    // truth) — resolved_at/resolved_by removed here per Design 04 §8.3, ratified in review
});
```

Surfaced back to the pushing device in `results[].flags.oversell` and consumed by PRD-04's
conflict UI. `InsufficientStockException` **never** fails a push (FR-13).

### 6.2 Credit breach — flagged, never rejected (P11)

For a credit `sale.create`, after applying, compute the customer's balance vs cached limit; if the
new balance exceeds `credit_limit`, create a flag. Never reject (FR-14).

```php
Schema::create('credit_breach_flags', function (Blueprint $table) {
    $table->id();
    $table->char('public_id', 26)->unique();
    $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
    $table->foreignId('device_id')->nullable()->constrained('devices');
    $table->foreignId('customer_id')->constrained('customers');
    $table->foreignId('invoice_id')->constrained('invoices');
    $table->bigInteger('credit_limit');       // minor units, at time of breach
    $table->bigInteger('balance_after');      // minor units
    $table->timestamp('occurred_at');
    $table->timestamps();
    // resolution lifecycle on reconciliation_items (Design 04 §8.3, ratified in review)
});
```

Surfaced in `results[].flags.credit_breach` and to PRD-04.

Both tables are cloud-only records (a device does not push them; the server derives them). They
carry `public_id` so PRD-04 can reference them and are **not** pulled to devices in MVP.

---

## 7. `public_id → int` FK resolution at the boundary (P12, FR-8/FR-15)

A `PublicIdResolver` service, always operating under the bound tenant (so it fails closed):

```php
$resolver->id(Product::class, $publicId);        // int|null (tenant-scoped lookup by public_id)
$resolver->morph($type, $publicId);              // for invocable/payable morphs
```

- **Inbound (push):** every `public_id` in a mutation payload is resolved to an int id before the
  Action runs. Morph columns (`invocable_type/id`, `payable_type/id`, `movable_type/id`) resolve
  the type token to the model class and the id via the resolver.
- **Outbound (pull/snapshot):** every int FK is projected back to the referenced row's `public_id`
  via an eager-loaded map (batch lookups, no N+1). A per-entity **projection transformer** (one
  class per table) defines exactly which columns are emitted and which FKs are rewritten — this is
  also what guarantees no internal-only column (e.g. `id`, cost internals) leaks unless intended.
- Unknown inbound public_id → the mutation is `rejected { unknown_reference }` (retriable), never a
  hard 500 (FR-15).

Within-batch forward references resolve because each mutation commits before the next runs (§5.2).

---

## 8. Operational: audit, health, rate limiting, versioning (FR-19, FR-20)

### 8.1 Sync audit trail

```php
Schema::create('sync_logs', function (Blueprint $table) {
    $table->id();
    $table->foreignId('tenant_id')->constrained();
    $table->foreignId('device_id')->constrained();
    $table->string('endpoint', 24);           // provision|snapshot|pull|push|attach
    $table->unsignedBigInteger('cursor_from')->nullable();
    $table->unsignedBigInteger('cursor_to')->nullable();
    $table->unsignedInteger('mutation_count')->default(0);
    $table->unsignedInteger('applied_count')->default(0);
    $table->unsignedInteger('rejected_count')->default(0);
    $table->unsignedInteger('duration_ms')->nullable();
    $table->timestamp('created_at');
});
```

### 8.2 Device health (for PRD-04's dashboard)

Updated on each request: `devices.last_seen_at`, `last_pull_at`, `last_push_at`, `last_acked_seq`.
The cloud cannot know the device's outbox depth, so **push and a lightweight `POST /heartbeat`
carry the device-reported `pending_count` and `oldest_pending_at`**, stored on `devices`
(`pending_count`, `oldest_pending_at` columns). "Pending age" on the dashboard = now −
`oldest_pending_at`.

### 8.3 Rate limiting

A named limiter keyed on device id, applied to the group (e.g. `throttle:sync` → 120 req/min/device;
`push` a lower ceiling). Returns `429` with `Retry-After`.

### 8.4 Protocol versioning (FR-20)

- Path carries `/v1`. Every request sends `X-Sync-Protocol: 1` and `X-App-Version: x.y.z`; every
  response echoes `"protocol"`.
- If `X-Sync-Protocol < SERVER_MIN_PROTOCOL` → **`426 Upgrade Required`** with
  `{ "error": "upgrade_required", "min_protocol": 2, "min_app_version": "1.4.0" }`. The client
  treats `upgrade_required` as a first-class terminal status (stops syncing, prompts update).
- Additive changes stay within `v1`; breaking changes bump to `/v2` (new route file), server
  supporting both during migration.

### 8.5 Amendments ratified from Designs 03/04 (additive, within v1)

- **Headers:** every request also sends `X-Client-Time` (device wall clock); the server records
  `devices.clock_skew_seconds` on push/heartbeat (Design 04 §5.1).
- **Telemetry:** the push envelope carries `client_pushed_at` (when the worker began the push);
  heartbeat carries `crash_count` and `session_count` (pilot SLOs, Design 04 §6.2).
- **Statuses:** `GET /pull` may return `409 { "error": "cursor_expired", "min_cursor": … }`
  (re-snapshot path, Design 04 §5.3); `POST /provision` returns `403 offline_disabled` when
  `tenants.offline_enabled` is off (Design 04 §6.5); any call from a revoked device returns
  `403 device_revoked` (§1.1).
- **sync-lib:** `MutationResult::isRetriable()` derived from `RejectionReason` (Design 03 D5);
  rejection-reason message keys follow `sync.rejection.{reason}` so app catalogs translate them
  without the package owning localization.

---

## 9. `namain/sync-protocol` DTOs (the wire contract shared both ways)

Namespace `NamaIn\SyncProtocol\`, PHP `^8.4`, no framework (Design 01 §8). Every DTO is an
immutable object with `fromArray()`/`toArray()` so cloud and client encode identically.

- **Constants:** `SyncProtocol::BASE = '/api/sync/v1'`, `SyncProtocol::VERSION = 1`,
  `SyncProtocol::HEADER_PROTOCOL = 'X-Sync-Protocol'`, `SyncProtocol::HEADER_APP = 'X-App-Version'`.
- **Enums:** `MutationType { PosSessionOpen, PosSessionClose, SaleCreate, CustomerCreate, ExpenseCreate }`;
  `Operation { Create, Update, Delete }`;
  `MutationOutcome { Applied, AlreadyApplied, Rejected }`;
  `RejectionReason { UnknownReference, ValidationFailed, SessionClosed, UpgradeRequired, TenantMismatch }`;
  `DeviceStatus { Pending, Active, Revoked }` (shared with the cloud enum).
- **Envelopes / DTOs:** `ProvisionRequest`, `ProvisionResponse`, `SnapshotManifest`, `EntityFile`,
  `PullResponse`, `ChangeEntry`, `PushEnvelope`, `Mutation`, `MutationResult`, `SaleFlags`,
  `SyncError`.
- **Helpers:** `Ulid::generate()/isValid()`; `IdempotencyKey::forMutation()` (rule: a ULID minted
  once per outbox item on the device, stable across retries — this is what makes re-push a no-op).
- **Money convention:** all monetary DTO fields are typed `int` (minor units). The package does
  not depend on the app's `Money` VO; the cloud maps `int ↔ MoneyCast` at the edge.

**Not in the package:** controllers, Actions, resolver, snapshot generator, Sanctum wiring
(cloud); outbox/engine/transport/UI (client). The package may expose a transport-agnostic client
**core** (request builders + response parsers) the client injects a transport into; the cloud never
imports it.

---

## 10. Answers to PRD-02 open questions (§7)

1. **Snapshot transport format.** JSONL per entity, gzipped + manifest (P4, §2.1). Not a SQLite
   file. Same shape as pull → one client apply path; driver-neutral for the MySQL/pgsql cloud.
2. **Push processing sync vs async.** **Synchronous** within the request, each mutation its own
   transaction, batch cap ~200 (P8, §5.2). Simplest exactly-once contract; returns serials inline.
3. **Pull scope matrix.** Full matrix in §4. Reference/catalog/users tenant-wide; **stock levels
   tenant-wide read** (needed for replenishment hints — resolves the specific question); device
   writes only its own storage's stock; transactional records register/storage-scoped.
4. **Expense attachments.** **Separate `POST /api/sync/v1/attachments`** (ability `sync:attach`),
   multipart, referenced from `expense.create` by a `receipt_public_id`; not inline in push JSON.
   Limits: ≤ 5 MB, `jpg|png|pdf`. Upload can precede or follow the expense mutation (linked by
   public_id); it is optional for MVP. Rationale: keeps the push envelope small/JSON, allows
   independent retry of large binaries.
5. **How users authenticate on the device.** **Cached bcrypt hashes** (P13). The snapshot projects
   each tenant user's `public_id`, name, email, **bcrypt `password` hash**, `must_change_password`,
   and their `tenant_user` role + `is_active`, plus roles/permissions. The device authenticates
   locally with `Hash::check(entered, cachedHash)` — fully offline — and runs `can:` against cached
   roles/permissions. Online re-auth on each successful sync refreshes hashes and drops
   deactivated users. A per-user device PIN is deferred to PRD-04 as an optional fast-unlock.
   **Rejected: device-local PIN as the primary factor** — needs a separate enrollment/credential
   store before MVP; the bcrypt cache reuses existing credentials with zero new surface. Security:
   hashes are slow bcrypt; NativePHP provides no DB encryption or keychain (corrected per
   Design 03 D2), so at-rest protection relies on Design 03 §4.3's mitigations (per-install
   `APP_KEY`-encrypted token, full-disk-encryption deployment guidance); device revocation kills
   the token and PRD-04's remote wipe clears the local DB. **This is the decision PRD-03 consumes.**

---

## 11. Inputs for other design docs

- **PRD-03 (client):** endpoint contracts (§1–§5, §8) are complete enough to build the client
  without reading server code (PRD-02 acceptance). Client responsibilities: own the cursor; mint
  stable `idempotency_key` per outbox item (§9); apply snapshot + pull via one upsert path; mirror
  FK cascades (Design 01 §5); local login via cached bcrypt (§10.5); report `pending_count` on
  push/heartbeat (§8.2); handle `upgrade_required` (§8.4). The device mints final serials locally
  (Design 01 §3) and sends them in `sale.create`.
- **PRD-04 (reconciliation & devices):** consume `oversell_reconciliations` and
  `credit_breach_flags` (§6), `sync_logs` + `devices` health columns (§8) for the device dashboard,
  and `devices.status` for revocation/wipe. Session/cash reconciliation reads pushed `pos_sessions`
  + drawer `treasury_movements`.

---

## 12. Implementation notes (suggested PR slicing)

1. **PR-1 sync guard + provisioning.** `routes/sync.php`, `sync` guard/provider, `Device` tokens,
   abilities, `devices.manage` permission, `POST /provision` + web enrollment. Tests: provision →
   token; revoked/pending device → 401; cross-tenant token cannot read another tenant.
2. **PR-2 snapshot.** `GenerateSnapshotJob`, projection transformers (shared with pull), JSONL
   archive + manifest, poll + ranged download. Test: seed a tenant, snapshot onto a second DB,
   assert row parity.
3. **PR-3 pull.** `GET /pull`, scope matrix filter, change-log collapse + live payload projection,
   tombstones. Tests: incremental pull continues from snapshot cursor; scope filtering; deletes.
4. **PR-4 push core.** Envelope, per-mutation transactions + idempotency gate, `PublicIdResolver`,
   `customer.create` + `expense.create` + `pos_session.open/close`. Tests: batch ordering,
   within-batch reference, replay-after-failure zero duplicates, unknown_reference retriable.
5. **PR-5 `sale.create` + oversell + credit breach.** `ReplayPosSaleAction`, force-deduct path,
   `oversell_reconciliations`, `credit_breach_flags`. Tests: two devices oversell same product
   (both recorded + oversell rows), credit breach flagged-not-rejected, serial stored verbatim.
6. **PR-6 operational.** `sync_logs`, health columns, rate limiter, versioning + `426`,
   `POST /heartbeat`, `POST /attachments`.

PR-1 → PR-3 are ordered; PR-4/PR-5 depend on PR-1 + Design 01's idempotency; PR-6 is largely
independent. All acceptance criteria in PRD-02 §6 map to tests in PR-3/4/5.
