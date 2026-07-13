# PRD — B3: Merchant UI (service form, booking form, calendar)

**Status:** Draft · **Milestone:** B3 · **Depends on:** B1 (schema/models), B2 (booking engine seam) · **PR grouping:** one PR (T3.1–T3.5, T3.7); T3.6 line-item sale path is a **separate** PR (or explicit follow-up)

## 1. Problem

B1 gives us the `service` product type, add-ons, and bookings; B2 gives us the overlap/travel-buffer engine. Neither is reachable by a merchant. This milestone is the entire merchant-facing surface: extend the existing product form so a merchant can define a service (duration, add-ons, the three flags, per-service travel buffer), add a booking create/edit flow that reuses the customer picker and honors the B2 engine (hard-block on overlap, soft-warn on tight travel buffer), and give a tenant-wide calendar to see the schedule. Everything is Arabic-first, RTL-correct, dark-mode-correct, and **must not** change how `physical` products are created or edited.

## 2. Goals / Non-goals

**Goals**
- A `type` toggle (physical | service) in the product form; when `service`, reveal service sections and hide the goods-oriented sections (stock, units, expiry, cost).
- Add-ons editor: repeatable name + price-delta rows persisted to `service_addons`.
- The three flags (`requires_booking`, `on_site`, `allow_overlap`) and a `travel_buffer_minutes` field **revealed only when `on_site` is on**.
- Booking create/edit: customer picker (reused), service picker (service-type only), date + start time with **derived read-only end time**, add-on selector (snapshotting deltas), address **conditional on `on_site`**, notes, status.
- Surface B2: overlap → hard error that blocks submit; travel buffer → **soft warning** the scheduler can confirm-and-proceed past.
- A tenant-wide calendar view of bookings — localized names, correct locale week-start, consistent numerals, bidi-isolated times/addresses.

**Non-goals**
- Client-facing booking/cancellation UI (v1 is merchant-only).
- Staff/resource columns on the calendar (one calendar for the whole tenant).
- Notifications wiring (B4) and the booking engine internals (B2).
- Recurring appointments, waitlists, deposits, no-show UI.

## 3. Current state (from audit)

- **Product create/edit is modal-driven, not page-based.** There are no `Products/Create.vue`/`Edit.vue` and no `create()`/`edit()` controller methods. The reused form component is `resources/js/Components/Products/ProductForm.vue` (Inertia `useForm`; infers edit vs create from the optional `product` prop; edit → `put route('products.update', product)`, create → `post route('products.index')`). `resources/js/Pages/Products/Index.vue` also has its own inline new-product `useForm` + quick-update; `Show.vue` is read-only.
- `ProductsController::store`/`update` (`app/Http/Controllers/Catalog/ProductsController.php:57,93`) always create a base `Unit` and run `syncOnHandQuantities()` inside a transaction — the **service path must bypass** stock/units. Routes: `Route::resource('/products', ProductsController::class)` (`routes/tenant.php:208`).
- `ProductRequest` (`app/Http/Requests/ProductRequest.php`) hard-requires `cost` `numeric|gt:0` and has **no `type` rule** — validation must become conditional: services validate `price` (base price), not `cost > 0`.
- **Reusable pickers:** `resources/js/Components/CustomSelect.vue` (remote async select: `label`, `track-by`, `:remote`, `:loading`, `@search-change`, `@scroll-end`) + `resources/js/Composables/useAsyncOptions.js`. Live examples to copy: `resources/js/Components/Quotes/QuoteForm.vue:18-24,87-97` (customer async options + `onCustomerSelect` + inline add) and POS `resources/js/Pages/Pos/Session.vue:47-58`. Inline customer create modal: `resources/js/Components/QuickAddPartyModal.vue` (`type="customer"`, emits `created`). Customers API: `GET /api/customers` → `route('api.customers.index')` (`routes/tenant.php:160`).
- Shared form primitives: `InputError`, `InputLabel`, `PrimaryButton`, `TextInput`, `CustomSelect`.
- `Customer` has a single free-text `address` and **no email** — the booking `address` is a **per-booking snapshot** field, pre-filled from the customer's address when `on_site`.
- Money serializes to **major units (decimal)** to the frontend via `Money::jsonSerialize()` — the add-on delta and base-price inputs are major-unit decimal fields, consistent with the existing cost/price inputs.
- Design system: flat, emerald primary, `.ai/Design rules` (summarized in CLAUDE.md) — logical RTL props only (`ms/me/ps/pe/text-start`), full dark-mode pairs, inline Heroicons SVG, no new component library.

## 4. Design & behavior

**Product form.** A segmented `type` control at the top of `ProductForm.vue`. `physical` (default) renders today's form unchanged. `service` swaps the body: base price (reuse the `price` input), `duration_minutes`, the add-ons editor, and a flags block (`requires_booking`, `on_site`, `allow_overlap`) as toggles; `travel_buffer_minutes` renders **only when `on_site` is true** (mirrors the M3 nested-toggle pattern). The stock/units/expiry/cost sections are hidden for services. On submit the form sends `type` plus service fields; `ProductRequest` branches its rules on `type`.

**Booking form.** A dedicated create/edit form component. Customer picker (reuse `CustomSelect` + `useAsyncOptions(route('api.customers.index'))` + optional `QuickAddPartyModal`). Service picker limited to service-type products (a filtered async endpoint or a passed prop). Selecting a service drives: available add-ons, the duration used to compute a **read-only** `ends_at` from the chosen `starts_at`, and whether the address field is shown/required (`on_site`). Add-on multi-select snapshots each selected delta+name into `booking_addons` on save; the total (`base_price` + Σ deltas) is shown live. Notes free-text; status defaults `Confirmed`.

**Engine surfacing (B2).** On submit the server runs the B2 checks. An **overlap** (same-service, confirmed) throws `BookingOverlapException` → a 422 with a clear localized message; the form shows it and does **not** persist. A **travel-buffer** breach (on-site only) is a **soft warning**: the first submit returns the warning without saving; the form surfaces it inline with a "confirm and proceed" affordance that re-submits with an acknowledgement flag, and the booking saves. The soft path never hard-blocks.

**Calendar.** A month/week grid of the tenant's bookings (one calendar, no resource lanes). Localized month/day names and correct locale week-start (Arabic week starts on the locale's first day), one consistent numeral system per view, and bidi isolation for times/addresses/Latin text embedded in Arabic. Clicking a booking opens edit; clicking a slot opens create pre-filled. Dark-mode contrast verified on every cell/state.

## 5. Data model / schema changes

**None** — B3 is UI + controller/validation only against B1's schema (`products.type` + service columns, `service_addons`, `bookings`, `booking_addons`). No migrations.

## 6. Task specs

### T3.1 — Product `type` toggle + conditional service sections · **M**
- **Behavior:** add a `type` segmented control to `ProductForm.vue`; bind `type`, `duration_minutes`, `requires_booking`, `on_site`, `allow_overlap`, `travel_buffer_minutes` into the `useForm`. Render service sections when `type === 'service'` and hide stock/units/expiry/cost; keep physical rendering byte-for-byte behavior. `travel_buffer_minutes` shown only when `on_site`.
- **Files:** `resources/js/Components/Products/ProductForm.vue`; possibly `resources/js/Pages/Products/Index.vue` (inline new-product form parity); shared toggle/segmented component under `resources/js/Components` if none exists.
- **Edge cases:** editing an existing physical product must show physical layout with no service fields leaking; switching type mid-edit clears the now-irrelevant fields from the payload; `on_site` toggled off must not submit a `travel_buffer_minutes`.
- **Acceptance criteria:** physical create/edit unchanged; service toggle reveals/hides the correct sections; buffer field visibility bound to `on_site`; all strings localized; RTL + dark mode correct.
- **Test plan:** Inertia/feature test asserting the product form page/props; component-level assertion (or Dusk) that service sections toggle and buffer reveals only under `on_site`.

### T3.2 — Add-ons editor + conditional validation · **M**
- **Behavior:** repeatable add-on rows (name + major-unit price-delta, add/remove) in the service section; persist to `service_addons` in `ProductsController::store/update` (sync: create/update/soft-delete removed rows), bypassing the base-Unit/`syncOnHandQuantities()` path for services. Make `ProductRequest` conditional on `type`: services require `price` (base price ≥ 0), do **not** require `cost > 0`, and validate `duration_minutes` (int, min 1), the three flag booleans, `travel_buffer_minutes` (int min 0, required-with `on_site`), and `addons.*` (name required, price_delta numeric ≥ 0).
- **Files:** `resources/js/Components/Products/ProductForm.vue` (editor UI); `app/Http/Controllers/Catalog/ProductsController.php:57,93` (branch service persistence); `app/Http/Requests/ProductRequest.php` (conditional rules); `app/Http/Requests/QuickUpdateProductRequest.php` if quick-edit touches type.
- **Edge cases:** removing an add-on already referenced by historical bookings must **not** mutate those bookings (snapshots live in `booking_addons`); zero-cost service passes validation (today's `cost > 0` blocks it); physical validation path untouched.
- **Acceptance criteria:** add-ons round-trip on create/edit; physical products still validate as before; a zero-cost service saves; removed add-ons don't corrupt past bookings.
- **Test plan:** feature tests for service create/edit persisting add-ons; regression test that physical create/edit + validation is unchanged; validation test for the conditional branch.

### T3.3 — Bookings controller, routes, index + form · **L**
- **Behavior:** a `BookingsController` (index/create-less modal or page, store, edit, update, destroy/cancel) + `Route::resource` in `routes/tenant.php`; a booking create/edit form component with customer picker, service picker (service-type only), `starts_at` date+time, derived read-only `ends_at`, add-on selector, conditional address (required when service `on_site`, pre-filled from customer), notes, status. A `BookingRequest` form request with rules (customer exists, service is a `service` product, `starts_at` required date, address required-when on_site, addons belong to the service, status in enum).
- **Files:** *new* `app/Http/Controllers/BookingsController.php`, `app/Http/Requests/BookingRequest.php`; `routes/tenant.php`; *new* `resources/js/Pages/Bookings/Index.vue` + `resources/js/Components/Bookings/BookingForm.vue`; reuse `CustomSelect`/`useAsyncOptions`/`QuickAddPartyModal`.
- **Edge cases:** service with `requires_booking = false` should not be bookable here (route to line-item sale, T3.6); changing the selected service recomputes duration/address-requirement/add-on list and clears stale add-ons; end time recomputes on start-time change; cancelled bookings are read-only.
- **Acceptance criteria:** booking create/edit round-trips; address required iff on_site; end time derived correctly; add-on deltas snapshotted; totals correct; localized + RTL + dark mode.
- **Test plan:** feature tests for store/update/cancel incl. snapshot immutability and on_site address requirement; Inertia prop tests for the index/form.

### T3.4 — Wire B2 engine into the booking form · **M**
- **Behavior:** on store/update, run B2's overlap + travel-buffer checks. Overlap (same-service, confirmed, honoring `allow_overlap`) → `BookingOverlapException` mapped to a 422 with a clear localized message; form blocks and shows it, nothing persists. Travel buffer (on-site only) → soft warning: first submit returns the warning without saving; the form shows it with a confirm-and-proceed affordance that re-submits with an acknowledgement flag; second submit saves.
- **Files:** `app/Http/Controllers/BookingsController.php` (call the B2 seam, translate outcomes), `resources/js/Components/Bookings/BookingForm.vue` (error + warning surfaces + acknowledge flow), exception→response mapping (`app/Exceptions` or handler).
- **Edge cases:** `allow_overlap = true` service silently permits overlap (no error); soft warning must never become a hard block; acknowledging the warning must not also suppress a genuine overlap error; back-to-back bookings that exactly abut are allowed (per B2).
- **Acceptance criteria:** overlap blocks and does not persist; buffer warning surfaces and proceeds on confirm; `allow_overlap` bypasses the block; messages localized.
- **Test plan:** feature tests: overlapping submit returns 422 and no row; buffer-breach submit returns warning then saves on acknowledge; `allow_overlap` service saves overlapping silently.

### T3.5 — Calendar view · **L**
- **Behavior:** a tenant-wide month/week calendar of bookings (no resource lanes). Localized month/day names, locale-correct week-start, one consistent numeral system, bidi-isolated times/addresses, dark-mode contrast on every cell/state. Clicking a booking → edit; clicking an empty slot → create pre-filled. Hand-built grid using logical Tailwind props (no new dependency).
- **Files:** *new* `resources/js/Pages/Bookings/Calendar.vue` (or a `Components/Bookings/Calendar.vue` embedded in Index), a small date-util composable if needed; `BookingsController` calendar data method.
- **Edge cases:** RTL day-order and week-start; DST/month boundaries (delegate time math to server-provided `starts_at`/`ends_at`); overlapping bookings rendered legibly; long month with many bookings scrolls without horizontal page overflow.
- **Acceptance criteria:** correct locale week-start and localized names; consistent numerals; RTL layout correct; dark mode legible; navigation to prev/next period works.
- **Test plan:** Inertia prop test for the calendar payload; optional Dusk smoke test for RTL rendering and period navigation.

### T3.6 — (SEPARATE PR / follow-up) `requires_booking = false` line-item sale path · **M**
- **Behavior:** a service with `requires_booking = false` sells like a normal line item in POS/invoices with **no calendar involvement** — base price + selected add-ons as an ad-hoc line. Either integrate into the POS/quote/invoice line picker or explicitly defer with justification.
- **Files:** POS `resources/js/Pages/Pos/Session.vue`, quote/invoice line components, and the relevant checkout/invoice actions.
- **Edge cases:** such services must be sellable but **not** appear in the bookings calendar/booking form; add-on deltas still snapshot onto the sale line.
- **Acceptance criteria:** non-booking service sells as a line item with add-ons; does not create a booking; booking flow excludes it.

### T3.7 — Catalog surface + `type` API filter + Bookings nav item · **M**
- **Behavior:** (a) surface service products in the existing `Products/Index.vue` list with a `type` badge and a `type` filter chip, reusing the existing filter-drawer pattern (`[[filter-drawer-pattern]]`) — no separate Services page; (b) add a `type` filter param to the web products index query and to the `api.products` endpoint so the booking form's service picker can request `?type=service` (service-type products only); (c) add a top-level **Bookings** sidebar nav item (calendar + list) following the existing nav-link active/inactive pattern.
- **Files:** `resources/js/Pages/Products/Index.vue` (badge + filter chip); `app/Http/Controllers/Catalog/ProductsController.php` (index `type` filter) and `app/Http/Controllers/Api/ProductsController.php` (`type` filter for the picker); the sidebar nav component under `resources/js/` (add the Bookings link); route names already defined in T3.3.
- **Edge cases:** default (no filter) shows all products as today — physical list unchanged; the `type` filter must compose with existing filters/search in the drawer; the API `type=service` must also respect `requires_booking` if the picker should only offer bookable services (offer all services, let the form guard `requires_booking`); nav active-state highlights on all Bookings sub-routes.
- **Acceptance criteria:** service products visible + filterable in the existing list with a type badge; `api.products?type=service` returns only service products; Bookings nav item present with correct active state; physical-only list behavior unchanged when no type filter is applied; localized + RTL + dark mode.
- **Test plan:** feature test for the `type` filter on both the web index and `api.products`; Inertia prop test that the list carries the type badge/filter; nav-render test/assertion for the Bookings link.
- **Test plan:** feature test selling a `requires_booking=false` service through POS/invoice with an add-on; assert no booking row created.
- **Note:** lean toward a **separate PR** — this touches the sales/POS surface, which is orthogonal to the booking UI and higher-risk; keep B3's core PR reviewable.

## 7. Edge cases (cross-task)

- Editing a product's `type` after data exists (physical→service or reverse) must not orphan or leak the other type's fields; hide + drop irrelevant payload keys.
- `on_site` toggled off must not submit a misleading `address` (booking) or `travel_buffer_minutes` (service).
- Overlapping submit shows the hard error and persists nothing; the soft buffer warning surfaces but allows proceed.
- RTL: calendar direction, day order, and week-start all follow the locale; chevrons rotate; times/addresses bidi-isolated.
- One numeral system per view — no mixing Arabic-Indic and Latin digits in the same calendar/booking view.
- Dark-mode contrast verified on the calendar grid, badges, and warning/error surfaces.
- Physical product create/edit and validation remain byte-for-byte unchanged (backwards compatibility).

## 8. Test plan (summary)

- Product form: physical unchanged; service toggle reveals/hides sections; buffer visibility bound to `on_site` (T3.1); add-ons round-trip + conditional validation + zero-cost service saves (T3.2).
- Bookings: store/update/cancel, on_site address requirement, derived end time, add-on snapshot immutability (T3.3).
- Engine surfacing: overlap blocks (no row), buffer warns then proceeds on acknowledge, `allow_overlap` bypasses (T3.4).
- Calendar: prop/payload test + optional Dusk RTL/navigation smoke (T3.5).
- Line-item sale (T3.6, separate): non-booking service sells with add-ons, no booking created.
- Regression: existing product/POS feature tests stay green.

## 9. Rollout & backwards compatibility

Additive UI. Physical products keep exactly today's create/edit/validation behavior — service branches are gated on `type`. No schema change. B3 requires B1 (schema) and B2 (engine seam) merged first. Ship T3.1–T3.5 as one PR; T3.6 (POS/line-item) as a separate PR to keep the diff reviewable. Calendar is hand-built to avoid a new dependency.

## 10. Resolved decisions

- **Hand-built calendar** (decided). A hand-built month/week grid using logical RTL props and the existing design system — **no new dependency**. No calendar package is pulled in.
- **New top-level "Bookings" nav item** (decided). Bookings (calendar + list) get a dedicated top-level sidebar entry, following the existing nav-link active/inactive pattern in `.ai/Design rules`; not nested under Products/POS.
- **Service products live in the existing Products list** (decided). They appear in `resources/js/Pages/Products/Index.vue` with a `type` badge and a `type` filter chip, reusing the existing filter-drawer pattern (`[[filter-drawer-pattern]]`) — no separate Services page.
- **Service picker uses a `?type=service` filter on the products API** (decided). Add a `type` filter to the existing `api.products` endpoint (`App\Http\Controllers\Api\ProductsController`); the picker reuses `CustomSelect` + `useAsyncOptions`. No dedicated `api.services` endpoint.
