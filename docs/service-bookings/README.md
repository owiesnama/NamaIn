# Service Product Type & Bookings — PRDs

Product requirements for introducing a second product type — **service** — alongside the existing **physical** product, so merchants who sell time (clinics, mobile/outfield clinics, home-service providers) can sell bookable services through the same POS, with a merchant-side calendar to manage appointments.

> **Scope guardrail (v1):** merchant-side only — **no client-facing portal**. Clients are notified, they do not self-serve. Existing `physical` products keep **zero behavior change** (strict backwards-compatibility). Out of scope for v1: staff/resource assignment, recurring appointments, configurable reminder timing, client self-service, route/distance calculation, waitlists, deposits, no-show tracking. Do not scaffold these "just in case".

See the Phase 0 survey findings that motivate this work (product/customer/notification audit). **Headline:** products are a single tenant-scoped table with **no `type` discriminator today** (the clean gap we fill); the whole app is `unguard()`ed; money is integer-minor-units via `MoneyCast`. The `Customer` entity is reused as the client (single free-text `address`, `phone_number`, **no email**). A mature Laravel Notifications pipeline exists (`ChequeDueNotification` + the scheduled `cheques:notify-for-due` command is a near-exact blueprint for the 24h reminder), but **no working WhatsApp/SMS delivery** exists — so we ship on `mail`+`database`+`broadcast` and leave the SMS/WhatsApp channel as a clearly-marked TODO.

## Milestones

| PRD | Milestone | One PR? | Depends on |
|---|---|---|---|
| [B1](B1-data-layer.md) | Data layer — `type` discriminator, service attributes, add-ons, bookings, snapshots, `physical` backfill | yes | — |
| [B2](B2-booking-engine.md) | Booking engine — overlap hard-block + travel-buffer soft-warning (pure domain, no UI) | yes | B1 |
| [B3](B3-merchant-ui.md) | Merchant UI — service create/edit, booking create/edit, calendar view | yes | B1, B2 |
| [B4](B4-notifications.md) | Notifications — 24h reminder + cancellation notice (following `ChequeDueNotification`) | yes | B1 (realistically after B2/B3) |

**Critical path:** B1 → B2 → B3. B4 depends only on B1 (bookings + cancel action) but ships after B3 in practice.

## Canonical schema & naming (single source of truth — every PRD conforms to this)

Decided in Phase 0 (with the prompt's delegated calls resolved):

### Product type discriminator
- **`products.type`** — `string`, cast to new enum **`App\Enums\ProductType`** (`Physical` | `Service`, TitleCase keys per PHP rules; string values `physical` | `service`). **Default `physical`**, indexed, **backfill all existing rows to `physical`**. Follows the `MovementType`/`InventoryStrategyType` enum convention (`app/Enums/`).

### Service attributes (columns on `products`, meaningful only when `type = service`)
- **Base price** — reuse the existing **`price`** column (integer minor units via `MoneyCast`). No new base-price column.
- **`duration_minutes`** — `unsignedInteger`, nullable.
- **`requires_booking`** — `boolean`, default `false`. When false the service sells as a normal line item (no calendar).
- **`on_site`** — `boolean`, default `false`. When true, booking requires an address and the travel buffer applies.
- **`allow_overlap`** — `boolean`, default `false`. When false, overlapping **same-service** confirmed bookings are hard-blocked.
- **`travel_buffer_minutes`** — `unsignedInteger`, nullable. **Per-service** (not merchant-level) — justified because `on_site` is per-service and Phase 3 reveals this field inside the service form; buffer reflects the travel/setup character of that service.

### Add-ons (merchant-defined per service)
- Table **`service_addons`**, model **`ServiceAddon`** (extends `BaseModel`). Columns: `tenant_id`, `product_id` (FK → products, cascade), `name` (string), `price_delta` (integer minor units via `MoneyCast`), timestamps, softDeletes. A booking's total = base price + Σ selected add-on deltas.

### Booking
- Table **`bookings`**, model **`Booking`** (extends `BaseModel`). Columns: `tenant_id`, `service_product_id` (FK → products), `customer_id` (FK → customers), `starts_at` (datetime), `ends_at` (datetime, derived from `starts_at` + service `duration_minutes` and **persisted** on save), `status` (string → enum **`App\Enums\BookingStatus`**: `Confirmed` | `Cancelled` | `Completed`, default `Confirmed`), `address` (text, nullable — **required only when the service is `on_site`**, snapshotted per-booking), `notes` (text, nullable), **`base_price`** (integer minor, **snapshot** at booking time), **`total`** (integer minor, computed once at booking time = `base_price` + Σ snapshot deltas and **stored**), timestamps, softDeletes. (`reminder_sent_at` nullable timestamp is added later as an additive migration in **B4**.)
- **Overlap & buffer are per-service** (confirmed via user): a new booking for service X is checked only against other **`Confirmed`** bookings of service X (`Completed`/`Cancelled` never block); `allow_overlap = true` skips the block entirely.

### Booking add-on snapshots (immutability)
- Table **`booking_addons`**, model **`BookingAddon`** (extends `BaseModel`). Columns: `tenant_id`, `booking_id` (FK → bookings, cascade), `service_addon_id` (FK → service_addons, **nullable** source reference), `name` (**snapshot**), `price_delta` (**snapshot**, integer minor via `MoneyCast`), timestamps.
- **Snapshot rule:** both the booking `base_price` and each `booking_addons.price_delta`/`name` are captured at booking time. Later edits to the service price or its add-ons **must not** mutate historical bookings.

### Booking engine (B2)
- `app/Services/Bookings/` — a `BookingScheduler` (or split `OverlapDetector` + `TravelBufferChecker`) mirroring the `app/Services/Inventory/` strategy layout. Overlap → throws `App\Exceptions\BookingOverlapException` (hard block) unless the service `allow_overlap`. Travel buffer → returns a **soft warning** (nullable value object / boolean+message), **never** throws; on-site services only.

### Notifications (B4)
- `App\Notifications\BookingReminderNotification` (24h fixed offset, not configurable) and `App\Notifications\BookingCancelledNotification` (to the merchant on cancel), following `ChequeDueNotification` — `implements ShouldQueue`, `via()` → `['mail','database','broadcast']`. Scheduled command (e.g. `bookings:notify-upcoming`) in `routes/console.php` following `cheques:notify-for-due` (window scan, `withoutGlobalScopes()` for the unbound tenant context). SMS/WhatsApp = **marked TODO** (no working channel exists; Twilio installed-but-unused, `mazin_host` stub).

## Cross-cutting conventions (apply to every task)

- **TDD** — Pest test written first (`php artisan make:test --pest`); run `php artisan test --compact --filter=…`. 100% model coverage is mandatory.
- **Localization** — every user-facing string via `__()`; Arabic-first.
- **RTL + dark mode** — every Vue element per `.ai/Design rules`; **logical** properties only (`ms/me/ps/pe/text-start/text-end`), never physical. One consistent numeral system per view; isolate bidi (times/addresses/Latin inside Arabic).
- **Pint** — `vendor/bin/pint --dirty --format agent` on touched PHP before finalizing.
- **Additive & reversible migrations only**; nothing destructive. New tables carry `tenant_id` and extend `BaseModel` (models are unguarded via `BaseModel::booted()`).
- **Backwards compatibility** — `physical` products keep exactly today's behavior: service-only paths (stock, units, expiry, booking) must be bypassed for physical, and physical validation/flow is untouched.
- **No new dependencies** without explicit approval.

## PRD template

Each milestone PRD follows this structure (standard depth, ~1 page + task specs):

```
# PRD — Bx: <Title>
Status · Milestone · Depends on · PR grouping
## 1. Problem
## 2. Goals / Non-goals
## 3. Current state (from audit, with file:line)
## 4. Design & behavior
## 5. Data model / schema changes
## 6. Task specs   → per task: Behavior · Files · Edge cases · Acceptance criteria · Test plan · Size
## 7. Edge cases (cross-task)
## 8. Test plan (summary)
## 9. Rollout & backwards compatibility
## 10. Open questions
```
