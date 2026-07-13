# PRD — B1: Data layer — type discriminator, service attributes, add-ons, bookings, snapshots

**Status:** Draft · **Milestone:** B1 · **Depends on:** — · **PR grouping:** one PR (T1.1–T1.6)

## 1. Problem

Today the catalog knows exactly one kind of product: a physical good with cost, price, stock, units and an expiry. Merchants who sell **time** — clinics, mobile/outfield clinics, home-service providers — have nowhere to model a bookable service. Before any booking engine (B2), UI (B3) or notifications (B4) can exist, the data layer needs a `type` discriminator on products, the service-specific attributes (duration, add-ons, the three behavior flags, a travel buffer), and the `bookings` + `booking_addons` tables that record an appointment with **price snapshots** so historical bookings never mutate when a service is later re-priced. This milestone lays that foundation and nothing else: schema, models, relationships, factories, the `physical` backfill, and full model-layer tests. It ships behavior-neutral — every existing product is backfilled to `physical` and the physical read/write paths are untouched.

## 2. Goals / Non-goals

**Goals**
- A `products.type` discriminator (`App\Enums\ProductType`: `physical` default | `service`), indexed, with every existing row backfilled to `physical`.
- Service attribute columns on `products` (`duration_minutes`, `requires_booking`, `on_site`, `allow_overlap`, `travel_buffer_minutes`); base price reuses the existing `price` column.
- `service_addons` (`ServiceAddon`) — merchant-defined per-service extras with a price delta.
- `bookings` (`Booking`) + `App\Enums\BookingStatus` and `booking_addons` (`BookingAddon`) snapshot rows, with the base price and each selected add-on delta/name captured at booking time (immutability).
- Relationships, factories with `physical`/`service` named states, and 100% model-layer test coverage.

**Non-goals**
- Overlap detection, travel-buffer warning, `ends_at` scheduling semantics beyond a pure derivation helper (all B2).
- Any controller, request validation, POS/line-item integration, or UI (B3).
- Notifications / scheduling (B4).
- Changing physical-product behavior, stock, units, costing, or their validation.

## 3. Current state (from audit, with file:line)

- `Product` extends `BaseModel` (`app/Models/BaseModel.php:12-22`), whose `booted()` calls `static::unguard()` — the whole app is **unguarded**, there is no `$fillable`, so a new column is mass-assignable the moment it exists. `Product` adds `HasFactory, SoftDeletes, WithTrashScope` (`app/Models/Product.php:24`).
- `Product::booted()` (`app/Models/Product.php:35-44`) chains `parent::booted()` then a `creating` hook defaulting `currency` (from `preference('currency','SDG')`), `price` ← `cost` ← 0 and `average_cost` ← `cost` ← 0. These are **physical-goods defaults**; a service reuses `price` as its base price but does not need `cost`/`average_cost`/stock — the hook must stay harmless for services (it already only defaults, never overwrites a provided value).
- Money casts (`app/Models/Product.php:215-224`): `price`, `cost`, `average_cost` → `App\Casts\MoneyCast`; `is_global_favorite` → `boolean`. `MoneyCast` round-trips **integer minor units** (DB) ↔ major units (PHP/JS) via `App\ValueObjects\Money` (`fromMinor`/`major`, `fromMajor`/`minor`). New money fields (`service_addons.price_delta`, `bookings.base_price`, `booking_addons.price_delta`) follow the same `unsignedInteger`/`MoneyCast` convention (deltas are non-negative — they are *added*).
- **No `type`/discriminator column exists on `products` today** — the clean gap. Enums live in `app/Enums/` with TitleCase keys (pattern: `MovementType`, `InventoryStrategyType`; string-backed with `label()` helpers).
- Products base migration `database/migrations/2023_01_07_162612_create_products_table.php`; `price`/`cost` are `unsignedInteger`. `tenant_id` was retrofitted onto every domain table by `2026_04_20_104832_add_tenant_id_to_all_tables.php`. New columns follow the additive-migration convention; `type` gets an index (list/filter by type in B3).
- Multi-tenancy: single shared DB + `tenant_id`, stamped on `creating` by `app/Traits/BelongsToTenant.php` and enforced by `app/Scopes/TenantScope.php` (fails closed → `whereRaw('1=0')` when no tenant resolves). New models (`ServiceAddon`, `Booking`, `BookingAddon`) **extend `BaseModel`** so they inherit tenant scoping + unguard automatically.
- `Customer` (`app/Models/Customer.php`, extends `BaseModel`) is the reused client: single free-text `address`, `phone_number`, **no email**. `Booking.customer_id` FKs to `customers`; the booking `address` is a **per-booking snapshot** (pre-fillable from the customer in B3), nullable at the DB layer and required-when-`on_site` only in B3 request validation.
- `ProductFactory` (`database/factories/ProductFactory.php`) sets no `type` and relies on the `BelongsToTenant` `creating` hook for `tenant_id`; because the model is unguarded, new columns are settable without touching `$fillable`.
- Eloquent rules (`.ai/Eloquent rules`): models singular; `Model::unguard()` inherited via `BaseModel`; **100% model test coverage mandatory**.

## 4. Design & behavior

`products.type` is a string cast to `ProductType`, defaulting to `physical`; the migration backfills existing rows to `physical` in the same change so the column is never null. The five service attributes live as columns on `products` (not a side table): they are cheap, nullable/boolean, and keep a service a single row — consistent with how the app already colocates product attributes. `duration_minutes` and `travel_buffer_minutes` are nullable `unsignedInteger`; `requires_booking`, `on_site`, `allow_overlap` are booleans defaulting `false` so **every existing physical row reads as a non-booking, non-on-site, non-overlap product with no duration** — exactly today's meaning.

Add-ons are a per-service child table. A `ServiceAddon` has a `name` and a `price_delta` that is *added* to the base price when selected on a booking.

A `Booking` belongs to a service `Product` (`service_product_id`) and a `Customer`, has `starts_at`, a **derived** `ends_at`, a `status` (`confirmed` default | `cancelled` | `completed`), an optional per-booking `address` and `notes`, and a **snapshotted** `base_price`. `ends_at` is computed as `starts_at + duration_minutes` by a pure model helper in this milestone (the *engine* meaning — overlap, buffer — is B2); we **persist** `ends_at` (see §10) so B2's overlap queries and the calendar can range-scan without joining the service each time.

**Snapshot / immutability** is the load-bearing rule. When a booking is created from a service, it copies the service's current `price` into `bookings.base_price` and, for each selected add-on, inserts a `booking_addons` row copying the add-on's current `name` and `price_delta` (with a nullable `service_addon_id` back-reference for provenance). The booking's `total` (`base_price` + Σ `booking_addons.price_delta`) is likewise **computed once at creation and stored as a column** — because all operands are already immutable snapshots, the stored total can never drift, and persisting it lets B3 reporting sort/sum totals in SQL. Because every value is copied, later edits to the service `price` or its `ServiceAddon` rows **do not** change any existing booking. A small helper (`Booking::createForService($service, $customer, $startsAt, $addonIds, …)` or a factory helper) centralizes the snapshot so callers can't forget it.

## 5. Data model / schema changes

- **`products`** (additive columns): `type` string default `'physical'` **indexed** (+ backfill existing → `physical`); `duration_minutes` unsignedInteger nullable; `requires_booking` boolean default `false`; `on_site` boolean default `false`; `allow_overlap` boolean default `false`; `travel_buffer_minutes` unsignedInteger nullable. Add `type` → `ProductType`, and the two booleans/durations to `Product::$casts` as appropriate (`type` → enum; booleans → `boolean`).
- **`service_addons`** (new): `id`, `tenant_id` (FK, per `BelongsToTenant`), `product_id` (FK → products, `cascadeOnDelete`), `name` string, `price_delta` unsignedInteger (`MoneyCast`), timestamps, softDeletes. Index `product_id`.
- **`bookings`** (new): `id`, `tenant_id`, `service_product_id` (FK → products), `customer_id` (FK → customers), `starts_at` datetime, `ends_at` datetime, `status` string default `'confirmed'` **indexed**, `address` text nullable, `notes` text nullable, `base_price` unsignedInteger (`MoneyCast`), `total` unsignedInteger (`MoneyCast`) — computed once at booking time (`base_price` + Σ selected add-on deltas) and stored, timestamps, softDeletes. Composite index `[service_product_id, status, starts_at]` to serve B2 overlap and the calendar (justified now, consumed in B2/B3).
- **`booking_addons`** (new): `id`, `tenant_id`, `booking_id` (FK → bookings, `cascadeOnDelete`), `service_addon_id` (FK → service_addons, **nullable**, `nullOnDelete`), `name` string, `price_delta` unsignedInteger (`MoneyCast`), timestamps. Index `booking_id`.
- **Enums:** `App\Enums\ProductType` (`Physical='physical'`, `Service='service'`) and `App\Enums\BookingStatus` (`Confirmed='confirmed'`, `Cancelled='cancelled'`, `Completed='completed'`), each with a localized `label()` per the `MovementType` pattern.
- All additive and reversible; `down()` drops the new tables and the added `products` columns.

## 6. Task specs

### T1.1 — `ProductType` enum + `type` column + backfill · **S**
- **Behavior:** add `App\Enums\ProductType` (string-backed, TitleCase keys, `label()`). Migration adds `products.type` string default `'physical'`, indexed, and backfills existing rows to `physical` in the same migration. Cast `type` → `ProductType` on `Product`. Add convenience scopes/accessors (`scopeServices`, `scopePhysical`, `isService()`).
- **Files:** *new* `app/Enums/ProductType.php`; *new* `database/migrations/…_add_type_to_products_table.php`; `app/Models/Product.php` (`$casts`, scopes/accessors).
- **Edge cases:** column must be non-null after migrate (default + backfill); an unknown string in DB should surface as an enum error, not silently coerce; physical scope must include legacy rows (guaranteed by backfill).
- **Acceptance criteria:** all existing rows read `ProductType::Physical`; `Product::factory()->service()` reads `Service`; `scopeServices`/`scopePhysical` partition the table; migrate + rollback clean.
- **Test plan:** unit test enum values/labels; migration/model test asserting backfill, cast, scopes, `isService()`.

### T1.2 — Service attribute columns + casts · **S**
- **Behavior:** migration adds `duration_minutes`, `requires_booking`, `on_site`, `allow_overlap`, `travel_buffer_minutes` to `products` with the defaults in §5; add boolean/enum casts; `price` remains the base price (no new column). Add a `serviceAddons()` `hasMany` relationship to `Product`.
- **Files:** *new* `database/migrations/…_add_service_attributes_to_products_table.php`; `app/Models/Product.php` (`$casts`, `serviceAddons()`).
- **Edge cases:** existing physical rows read `requires_booking/on_site/allow_overlap = false`, `duration_minutes/travel_buffer_minutes = null` (today's meaning); zero-duration service allowed at the data layer (a real duration is enforced in B3); booleans coerce from `0/1/'true'`.
- **Acceptance criteria:** physical rows unchanged; a service row round-trips all five attributes; `serviceAddons()` returns the product's add-ons.
- **Test plan:** model test asserting defaults on a physical product and round-trip on a service; regression that a physical factory product is untouched.

### T1.3 — `ServiceAddon` model + table + factory · **S**
- **Behavior:** `ServiceAddon` (extends `BaseModel`, `SoftDeletes`) with `product()` `belongsTo`, `price_delta` → `MoneyCast`. Migration per §5. Factory with a tenant-aware default and a way to attach to a service product.
- **Files:** *new* `app/Models/ServiceAddon.php`; *new* `database/migrations/…_create_service_addons_table.php`; *new* `database/factories/ServiceAddonFactory.php`.
- **Edge cases:** `price_delta` of 0 is valid (free add-on); deleting a product cascades its add-ons; add-ons are tenant-scoped like every `BaseModel`.
- **Acceptance criteria:** an add-on persists with a money delta, belongs to its product, is tenant-scoped, and cascades on product delete.
- **Test plan:** model test for relationship, `MoneyCast` round-trip, tenant scoping, cascade.

### T1.4 — `Booking` model + `BookingStatus` enum + table · **M**
- **Behavior:** `App\Enums\BookingStatus` (`confirmed`/`cancelled`/`completed`, `label()`). `Booking` (extends `BaseModel`, `SoftDeletes`): relationships `service()` (`belongsTo` Product via `service_product_id`), `customer()`, `addons()` (`hasMany` `BookingAddon`); casts `starts_at`/`ends_at` → `datetime`, `status` → `BookingStatus`, `base_price` → `MoneyCast`, `total` → `MoneyCast`; a pure helper `deriveEndsAt()` = `starts_at + service.duration_minutes`. `total` is a **stored column** written once by the create helper (T1.5) as `base_price` + Σ snapshot add-on deltas — not a live accessor, since all operands are immutable. Persist `ends_at` on save from the derivation (a `saving` hook or explicit set in the create helper). Migration per §5.
- **Files:** *new* `app/Enums/BookingStatus.php`; *new* `app/Models/Booking.php`; *new* `database/migrations/…_create_bookings_table.php`.
- **Edge cases:** null `duration_minutes` → `ends_at == starts_at` (guard, no crash) — a real duration is a B3 concern; `status` defaults `confirmed`; `address`/`notes` nullable; stored `total` with no add-ons == `base_price`; **no overlap/buffer logic here** (B2). Timezone: `starts_at`/`ends_at` stored as `datetime` in the app timezone (Sudan `Africa/Khartoum`, no DST) — DST/tz edge tests are B2's remit, but the columns and casts are fixed here.
- **Acceptance criteria:** a booking persists with derived `ends_at`, correct `total()`, enum status, and all three relationships load; tenant-scoped; soft-deletable.
- **Test plan:** model test: relationships, `datetime`/enum/`MoneyCast` casts, `deriveEndsAt()` (incl. null duration), stored `total` with/without add-ons (written by the create helper), default status.

### T1.5 — `BookingAddon` snapshot model + table + create-from-service helper · **M**
- **Behavior:** `BookingAddon` (extends `BaseModel`) with `booking()` `belongsTo`, optional `serviceAddon()` `belongsTo` (nullable), `price_delta` → `MoneyCast`, `name` snapshot. A create helper — `Booking::createForService(Product $service, Customer $customer, CarbonInterface $startsAt, array $addonIds = [], ?string $address = null, ?string $notes = null): Booking` — snapshots `base_price` from `service->price`, derives/persists `ends_at`, inserts one `BookingAddon` per selected add-on copying its current `name` + `price_delta`, and writes the stored `total` (`base_price` + Σ inserted deltas). Wrapped in a transaction.
- **Files:** *new* `app/Models/BookingAddon.php`; *new* `database/migrations/…_create_booking_addons_table.php`; *new* `database/factories/BookingAddonFactory.php`; helper on `app/Models/Booking.php` (or a small `app/Actions/Bookings/CreateBookingAction.php` if it keeps the model lean — lean toward an action to avoid fattening the model).
- **Edge cases:** **immutability** — after creating a booking, editing the source `ServiceAddon`'s `price_delta`/`name` (or the service `price`) must not change the booking's snapshot rows/`base_price`/stored `total`; selecting zero add-ons → no `booking_addons` rows, stored `total == base_price`; a later-deleted source add-on → `service_addon_id` nulls out but the snapshot `name`/`price_delta` remain (history preserved); passing an add-on id from another service → helper ignores/validates (data-layer guards against cross-service add-ons).
- **Acceptance criteria:** the helper snapshots base price and selected deltas; mutating the source afterward leaves the booking total unchanged; deleting a source add-on preserves the snapshot; cross-service add-on ids are rejected.
- **Test plan:** the **immutability test is mandatory** — create booking, mutate `ServiceAddon` + service `price`, assert booking stored `total`/`base_price`/rows unchanged; test zero-add-on and deleted-source-add-on paths; assert transactional creation.

### T1.6 — Factories, named states & seeder touch · **S**
- **Behavior:** extend `ProductFactory` with `service()` (sets `type=service`, a `duration_minutes`, sensible flags) and `physical()` (explicit, = today's default) states; ensure `ServiceAddonFactory`/`BookingFactory`/`BookingAddonFactory` compose (e.g. `Booking::factory()->for(Product::factory()->service())`). Optionally seed one demo service + booking in the example seeder (only if it mirrors existing demo-seed patterns; otherwise skip).
- **Files:** `database/factories/ProductFactory.php`; *new* `database/factories/BookingFactory.php`; `database/seeders/DashboardExampleSeeder.php` (optional, guarded).
- **Edge cases:** `service()` state must not set physical-only fields in a way that breaks the `creating` hook; factories must not set `tenant_id` explicitly (rely on the trait) to match existing convention; a `physical()` product must be indistinguishable from a legacy row.
- **Acceptance criteria:** `Product::factory()->service()->create()` yields a valid bookable service; booking/add-on factories build a complete booking-with-snapshots graph; seeded demo data (if added) stays tenant-consistent.
- **Test plan:** factory tests building each graph; assert `service()`/`physical()` states set the right `type` and attributes.

## 7. Edge cases (cross-task)

- **Physical regression:** every added column defaults to the physical meaning (`type=physical`, flags `false`, durations null); a broad regression test asserts an existing physical product's behavior/attributes are unchanged after all migrations.
- **Snapshot immutability** (the headline invariant): re-priced services and edited/deleted add-ons must never alter historical bookings — covered explicitly in T1.5.
- **Zero values:** zero `price_delta`, zero/undefined `duration_minutes`, and zero-cost services are all representable; higher-layer minimums are B3.
- **Tenant scoping:** `service_addons`/`bookings`/`booking_addons` are all `BaseModel` → auto-scoped; a booking must never reference a cross-tenant customer/service (enforced by scoping; assert in tests).
- **Cascade/soft-delete:** deleting a product cascades add-ons; soft-deleting a booking retains its snapshot rows; a nulled `service_addon_id` keeps the snapshot readable.
- **`on_site` + null `address`** is permitted at the data layer (the required-when-`on_site` rule is B3 validation); note it so B3 owns it.

## 8. Test plan (summary)

- Enum unit tests: `ProductType`, `BookingStatus` values/labels (T1.1, T1.4).
- Product model tests: `type` cast/scopes/backfill, service attributes defaults & round-trip, `serviceAddons()` (T1.1–T1.2).
- `ServiceAddon`/`Booking`/`BookingAddon` model tests: relationships, `MoneyCast`/`datetime`/enum casts, `deriveEndsAt()`, stored `total`, tenant scoping, cascade/soft-delete (T1.3–T1.5).
- **Immutability test** (mandatory): mutate source service price + add-ons, assert booking snapshot unchanged (T1.5).
- Factory graph tests + `service()`/`physical()` states (T1.6).
- Regression: existing product/stock feature tests stay green; a physical product is unaffected by every new migration/column.
- 100% model-layer coverage across the four new/updated models.

## 9. Rollout & backwards compatibility

Fully additive and reversible. `products.type` backfills to `physical` in the same migration, and every new attribute defaults to the physical meaning, so existing tenants see **zero behavior change** — no controller, request, POS, stock, or UI path reads the new columns yet (those arrive in B2–B4). New tables are empty until B3 creates bookings. `down()` drops the three new tables and the six added `products` columns. Deploy as a single PR ahead of B2.

## 10. Resolved decisions

- **Persist `ends_at`** (decided). Set from `deriveEndsAt()` on save via a `saving` hook / the create helper, so B2 overlap detection and B3's calendar range-scan by time without joining the service. The `saving` hook keeps it consistent when `starts_at` changes.
- **Store `total` as a column** (decided). Computed once at booking time (`base_price` + Σ snapshot deltas) and persisted — safe because every operand is an immutable snapshot, and it lets B3 reporting sort/sum totals in SQL. Written by the create helper (T1.5), never recomputed live.
- **`requires_booking = false` services create no `bookings` rows** (decided). They sell as ordinary line items through POS/invoices (a B3/POS concern). B1 only guarantees the column exists and defaults `false`.
- **Cross-service add-on guard: defense in depth** (decided). The create helper (T1.5) rejects add-on ids that don't belong to the service, and B3's request re-validates — the helper is the single snapshot choke point.
