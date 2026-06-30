# PRD 08 — Open POS for any POS warehouse

**Batch:** B — POS · **Branch:** `feat/pos` · **Item:** 8

## Problem

Opening `/pos` always uses the **first** sale-point storage:
`app/Http/Controllers/Sales/PosSessionController.php:19`
`$storage = currentTenant()->storages()->where('type', StorageType::SALE_POINT)->first();`
There is no picker, so tenants with multiple sale points can only ever reach the first one — even
though the backend already supports concurrent per-storage sessions (`OpenPosSessionAction`,
`Storage.active_session_id`, `PosSessionController@store` already accepts `storage_id`).

A "POS warehouse" = a `Storage` whose `type === StorageType::SALE_POINT` (scope `Storage::salePoints()`).

## Goal

Users can open POS for any of the tenant's sale-point storages via a picker; the chosen sale point
drives the session, products, and checkout.

## Requirements

1. **Selectable sale point in `show()`.** Change `PosSessionController@show` to accept a selected
   sale point — via a route param (`/pos/{storage?}`) or `request('storage_id')`. Validate it is a
   `StorageType::SALE_POINT` belonging to the current tenant; fall back to the first/only sale point
   when none is specified. Key the open-session lookup to the chosen storage (as today).
2. **Picker UI.** Add a sale-point selector listing the tenant's sale points via the existing
   `Storage::salePoints()` scope. Place it where the user opens POS — e.g. a dropdown on
   `resources/js/Pages/Pos/Open.vue` (which currently hardcodes `storage_id: props.storage.id` at
   line 14) and/or the POS nav entry in `resources/js/Layouts/AppLayout.vue:138-149`. Selecting a
   sale point navigates to / opens POS for that storage. Follow `.ai/Design rules` (dark mode, RTL).
   Hide/disable the picker when only one sale point exists.
3. **Do not regress** the existing single-sale-point flow or checkout
   (`ProcessPosCheckoutAction` is already driven by `$session->storage`).

## Testing (mandatory)

**Pest** — extend `tests/Feature/PosSessionTest.php`:
- `show()` with a specified `storage_id` renders POS for that sale point (products scoped to it).
- A non-sale-point storage id (a `WAREHOUSE`) or another tenant's storage is rejected / falls back.
- Sessions can be opened on two **different** sale points independently (only same-storage
  uniqueness is currently tested).

**Cypress** — new/extended POS spec: with two seeded sale points, switch the picker → assert POS
opens against the selected sale point (e.g. its name shows and its product stock is used).

## Acceptance criteria

- [ ] Any sale point can be selected and opened; validation rejects non-sale-points/cross-tenant.
- [ ] Picker UI present; single-sale-point flow unchanged.
- [ ] Pest + Cypress green; `vendor/bin/pint --dirty` clean.
