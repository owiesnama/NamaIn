# PRD — B2: Booking engine (overlap hard-block + travel-buffer soft-warning)

**Status:** Draft · **Milestone:** B2 · **Depends on:** B1 · **PR grouping:** one PR (T2.1–T2.4 + tests)

## 1. Problem

Bookings must not silently collide. When a merchant schedules a service appointment, two independent scheduling rules apply and they behave very differently:

- **Overlap** is a hard constraint. A single tenant has one calendar (no staff/resource model in v1), so for a given service the same slot cannot be sold twice — unless the merchant has explicitly opted that service into double-booking (`allow_overlap`). A colliding booking must be **hard-blocked** with a clear, localized error.
- **Travel buffer** is a *soft* constraint. For on-site services, staff travel between clients; if a new on-site booking starts too soon after the previous one ends, we should **warn** the scheduler — but never block. The merchant knows traffic better than we do, so they confirm and proceed.

This milestone builds the **pure domain engine** that answers "can this booking be placed, and is there anything the scheduler should be warned about?" — no UI, no controllers, no persistence changes. B3 consumes it; the engine here is behavior-neutral until then.

## 2. Goals / Non-goals

**Goals**
- A half-open interval overlap primitive (`[starts_at, ends_at)`) so back-to-back bookings that exactly abut do **not** count as overlapping.
- Overlap detection **scoped per-service** (same `service_product_id`) against **`Confirmed`** bookings only, hard-blocking via `App\Exceptions\BookingOverlapException`, and skipped entirely when the service has `allow_overlap = true`.
- Travel-buffer detection for **on-site services only**, returning a **soft warning** value object (never throwing) when the gap from the previous confirmed booking's end is shorter than the service's `travel_buffer_minutes`.
- A single orchestration seam (`BookingScheduler::assertBookable()`) that runs the hard checks and returns any soft warnings — the one method B3 calls.
- An `ignore` hook so editing/rescheduling a booking excludes itself from its own overlap/buffer checks.
- Exhaustive unit coverage of the edge cases below (this is the phase the initiative flags for thorough testing).

**Non-goals**
- Any UI, controller, route, or FormRequest (all B3).
- Persisting bookings, deriving/storing `ends_at`, or the booking total (B1 owns the schema + `ends_at`; the engine only reads them).
- Notifications on placement/cancel (B4).
- Cross-service overlap, staff/resource conflicts, recurring appointments, route/distance math (out of v1 scope).

## 3. Current state (from audit)

- **No booking/scheduling engine exists.** There is no `app/Services/Bookings/`, no overlap or buffer logic anywhere in the codebase (Phase 0 confirmed no booking/appointment/calendar concept). The engine is net-new.
- **Pattern to mirror:** the inventory policy seam. `app/Services/Inventory/` holds a contract (`InventoryStrategy`) with concrete implementations resolved from tenant state, and enforcement throws a domain exception (`InsufficientStockException`) at a single choke point (`Storage::deductStock`). B2 follows the same shape — a small service class in `app/Services/Bookings/` and a domain exception in `app/Exceptions/`.
- **B1 provides the inputs the engine reads:** `Booking` model + `App\Enums\BookingStatus` (`Confirmed` | `Cancelled` | `Completed`, default `Confirmed`), with `service_product_id`, `customer_id`, `starts_at` (datetime), `ends_at` (datetime, derived from the service `duration_minutes`), `status`. The service product carries the per-service flags `allow_overlap` (bool, default false), `on_site` (bool, default false), and `travel_buffer_minutes` (unsignedInteger, nullable). Overlap/buffer are **per-service** (confirmed by product owner): a new booking for service X is compared only against other bookings of service X.
- **Tenant scoping is implicit.** Every `Booking` query runs under `TenantScope` (`app/Scopes/TenantScope.php`), which constrains to the resolved tenant and **fails closed** (`whereRaw('1 = 0')` when no tenant resolves). Overlap queries in a normal request context therefore need no explicit `tenant_id` clause — they inherit the scope. (The unbound, scheduled-command context is B4's concern, not B2's.)
- **Timezone:** `starts_at`/`ends_at` are Eloquent `datetime` casts (Carbon instances). App timezone is a single tenant timezone; Sudan (Africa/Khartoum) observes **no DST**, so wall-clock and instant comparisons don't diverge in production today — but the engine must still compare **instants** (Carbon), never naive strings, so a future timezone/DST change can't silently corrupt overlap math.

## 4. Design & behavior

Two rules, one seam.

**Interval semantics — half-open `[start, end)`.** A booking occupies `starts_at` inclusive to `ends_at` exclusive. Two intervals `A` and `B` overlap iff `A.start < B.end AND B.start < A.end`. This makes back-to-back bookings that touch at the boundary (`A.end == B.start`) **non-overlapping** — the correct real-world behavior (a 09:00–09:30 and a 09:30–10:00 appointment do not conflict). We commit to this convention explicitly and test it at the boundary.

**Overlap (hard block).** For a candidate booking (service `S`, interval `I`, optional `ignoreId`):
- If `S.allow_overlap === true` → skip; no block. (Merchant opted into double-booking.)
- Else query existing bookings where `service_product_id = S.id`, `status = Confirmed`, `id != ignoreId`, and the stored interval overlaps `I` (half-open). If any exist → throw `BookingOverlapException` carrying the conflicting booking(s) and a localized message.
- Only `Confirmed` participates — a `Cancelled` booking is excluded, so cancelling instantly frees the slot for rebooking. `Completed` is likewise excluded (see §10).

**Travel buffer (soft warning).** Only when `S.on_site === true` **and** `S.travel_buffer_minutes` is a positive value:
- Find the nearest **preceding** confirmed same-service booking (the one whose `ends_at` is the greatest `ends_at <= I.start`, excluding `ignoreId`). If the gap `I.start - previous.ends_at` (in minutes) is `< travel_buffer_minutes`, return a `TravelBufferWarning` value object (conflicting booking + actual gap + required buffer). Otherwise return `null`.
- This **never throws**. If `on_site` is false, or `travel_buffer_minutes` is null/zero, always return `null`.
- The forward-looking direction (the *next* booking starting too soon after *this* one ends) is symmetric and also surfaced — the detector returns all buffer violations adjacent to `I` (preceding and following), so inserting a booking between two others warns about both neighbors. B3 decides how to render them.

**Orchestration seam.** `BookingScheduler::assertBookable(Booking|candidate $b, ?int $ignoreId = null): TravelBufferWarning[]`:
1. Runs the overlap check first — throws `BookingOverlapException` on a hard conflict (nothing else runs).
2. If it passes, runs the buffer detector and **returns** the (possibly empty) array of soft warnings.

So the contract is: *throws* ⇒ cannot proceed; *returns warnings* ⇒ may proceed, surface these; *returns `[]`* ⇒ clean. B3's booking controller calls this once and branches on the outcome.

Layout mirrors `app/Services/Inventory/`: a `BookingScheduler` service (constructor-injectable, resolvable from the container) delegating to two focused collaborators — `OverlapDetector` and `TravelBufferChecker` — so each rule is independently testable. Exceptions live in `app/Exceptions/`.

## 5. Data model / schema changes

**None.** B2 is pure logic over the schema B1 defines. It reads `bookings.starts_at/ends_at/status/service_product_id` and the service product's `allow_overlap/on_site/travel_buffer_minutes`. It writes nothing and adds no columns. `ends_at` is assumed present (stored, set from `duration_minutes` at creation — B1); the engine does not derive it.

## 6. Task specs

### T2.1 — Interval / overlap primitive · **S**
- **Behavior:** a small, pure `TimeInterval` helper (or a private method on `OverlapDetector`) implementing half-open overlap: `overlaps(A, B) = A.start < B.end && B.start < A.end`, comparing Carbon instants. Boundary-touching intervals (`A.end == B.start`) return `false`.
- **Files:** *new* `app/Services/Bookings/TimeInterval.php` (value object wrapping two Carbon instants) or an inlined primitive in `OverlapDetector.php` — prefer the value object for testability and reuse by the buffer checker.
- **Edge cases:** exact abutment (no overlap); identical intervals (overlap); one interval fully inside another (overlap); zero-duration interval `start == end` (see §7); instants across a naive-string boundary must still compare correctly (assert with differing offsets if the cast ever changes).
- **Acceptance criteria:** `overlaps` is symmetric; abutting → false; any positive intersection → true; comparison is instant-based (Carbon), not string-based.
- **Test plan:** unit table of interval pairs (abut, overlap-partial, contained, identical, disjoint, zero-duration) asserting expected boolean; a case constructed to fail under naive string comparison but pass under instant comparison.

### T2.2 — Overlap hard-block honoring `allow_overlap` · **M**
- **Behavior:** `OverlapDetector::assertNoConflict(candidate, ?ignoreId)` — returns early (no-op) when the service `allow_overlap` is true; otherwise queries `Booking::where('service_product_id', S.id)->where('status', BookingStatus::Confirmed)->when(ignoreId, fn ($q) => $q->whereKeyNot(ignoreId))` and filters to those overlapping the candidate interval (half-open). If any match, throw `App\Exceptions\BookingOverlapException` with the conflicting booking(s) and a localized (`__()`) message.
- **Files:** *new* `app/Services/Bookings/OverlapDetector.php`, `app/Exceptions/BookingOverlapException.php`. Uses T2.1.
- **Edge cases:** `allow_overlap=true` → never throws even with a real collision; cancelled/completed bookings excluded (only `Confirmed` queried) so the slot is free; `ignoreId` excludes the row being edited from its own check; empty calendar → passes; DB-level overlap can be pushed into the query (`where starts_at < :end and ends_at > :start`) for efficiency, but the boundary semantics must match T2.1 exactly (`<`/`>`, not `<=`/`>=`).
- **Acceptance criteria:** a colliding confirmed same-service booking throws; the same collision with `allow_overlap=true` does not; a cancelled colliding booking does not throw; editing a booking to the same time it already occupies (self) does not throw when `ignoreId` is passed; a different service at the same time does not throw.
- **Test plan:** unit/feature tests for each: collision-blocks, allow_overlap-permits, cancelled-frees-slot, self-ignored-on-edit, different-service-ignored, abutting-allowed. Assert the exception carries the conflicting booking.

### T2.3 — Travel-buffer soft-warning detector · **M**
- **Behavior:** `TravelBufferChecker::warningsFor(candidate, ?ignoreId): TravelBufferWarning[]` — returns `[]` immediately unless the service `on_site === true` and `travel_buffer_minutes > 0`. Otherwise finds the adjacent confirmed same-service bookings (nearest preceding by `ends_at`, nearest following by `starts_at`, excluding `ignoreId`) and, for each, computes the gap in minutes; if `gap < travel_buffer_minutes`, includes a `TravelBufferWarning` (neighbor booking, actual gap, required buffer, direction). Never throws.
- **Files:** *new* `app/Services/Bookings/TravelBufferChecker.php`, `app/Services/Bookings/TravelBufferWarning.php` (immutable value object; implements `JsonSerializable` so B3 can hand it to Inertia).
- **Edge cases:** `on_site=false` → always `[]`; `travel_buffer_minutes` null or `0` → `[]`; gap exactly equal to buffer → **no** warning (strictly `<`); gap larger than buffer → no warning; neighbor is cancelled → ignored; both neighbors too close → two warnings; `ignoreId` excludes self; a booking placed between two others warns for preceding and following independently.
- **Acceptance criteria:** warnings only for on-site + positive buffer + gap strictly under buffer; boundary gap `== buffer` yields none; cancelled neighbors never trigger; the warning value object exposes gap, required buffer, and the neighbor; serializes cleanly to JSON.
- **Test plan:** unit tests over gap vs buffer (under/equal/over), on_site true/false, null/zero buffer, cancelled neighbor, both-neighbors-close, self-ignored. Assert `warningsFor` never throws.

### T2.4 — `BookingScheduler` orchestration seam · **S**
- **Behavior:** `BookingScheduler::assertBookable(candidate, ?ignoreId = null): TravelBufferWarning[]` — calls `OverlapDetector::assertNoConflict()` (may throw `BookingOverlapException`), then returns `TravelBufferChecker::warningsFor()`. This is the single method B3 invokes. Register the service (and its two collaborators) in the container so it's constructor-injectable, matching the inventory resolver's provider registration.
- **Files:** *new* `app/Services/Bookings/BookingScheduler.php`; binding in a service provider (e.g. `app/Providers/AppServiceProvider.php`).
- **Edge cases:** overlap throws ⇒ buffer check never runs (fail fast); clean placement ⇒ returns `[]`; placement that passes overlap but violates buffer ⇒ returns non-empty warnings (does not throw); `ignoreId` threaded to both collaborators.
- **Acceptance criteria:** throws on hard conflict; returns warnings (never throws) on soft conflict; returns `[]` when clean; resolvable from the container.
- **Test plan:** integration-style unit test wiring the real collaborators: assert throw-on-overlap short-circuits buffer, assert warning-return on buffer-only, assert clean returns `[]`.

## 7. Edge cases (cross-task)

- **Exact abutment.** `A.end == B.start` is **not** an overlap (half-open convention, T2.1) — a deliberate, tested choice; back-to-back bookings are always allowed.
- **Back-to-back within buffer.** Two abutting on-site bookings do not overlap but *may* trip the travel-buffer warning (gap = 0 < buffer) — the two rules are independent and both are asserted in one scenario.
- **Cancellation frees the slot.** Only `Confirmed` bookings are queried by both detectors, so cancelling a booking makes its interval immediately rebookable with no cleanup step. Explicitly tested: place → cancel → rebook same slot succeeds.
- **`allow_overlap=true`** bypasses the overlap query entirely (never throws), independent of the buffer rule (which still applies if on-site).
- **Buffer only when on-site + positive buffer**; gap `== buffer` yields no warning (strict `<`); null/zero buffer yields none.
- **Zero-duration service** (`starts_at == ends_at`, e.g. a duration-less service): with half-open intervals a zero-length interval overlaps nothing (its `start == end` makes `A.start < A.end` false), so two zero-duration bookings at the same instant do **not** register as overlapping. Flagged as an open question (§10) — if merchants create bookable zero-duration services this may need a special-case; for v1 we document the behavior and test it rather than special-casing.
- **Self-exclusion on edit.** Rescheduling passes the booking's own id as `ignoreId` so it never conflicts with itself; tested for both overlap and buffer.
- **Timezone/DST.** All comparisons use Carbon instants. Sudan has no DST so no production divergence, but tests assert instant-based comparison (not naive string) and cover intervals that would mis-sort under string compare.
- **Tenant isolation.** In-request queries inherit `TenantScope`; the engine adds no cross-tenant clause and must never be handed a query with global scopes removed (that unbound context belongs to B4's scheduled command, not B2).

## 8. Test plan (summary)

- Interval primitive: abut/overlap/contained/identical/disjoint/zero-duration + instant-vs-string (T2.1).
- Overlap: collision-blocks, allow_overlap-permits, cancelled-frees-slot, self-ignored-on-edit, different-service-ignored, abutting-allowed (T2.2).
- Buffer: gap under/equal/over, on_site true/false, null/zero buffer, cancelled neighbor, two-neighbors, self-ignored, never-throws (T2.3).
- Scheduler seam: throw-short-circuits-buffer, warning-only-returns, clean-returns-empty, container-resolvable (T2.4).
- Regression: no existing tests touch this net-new namespace; the engine is inert until B3 calls it.

## 9. Rollout & backwards compatibility

Fully additive and behavior-neutral. B2 introduces a new `app/Services/Bookings/` namespace and one exception; nothing calls the engine yet (B3 wires it into the booking controller). No schema, no migration, no change to any existing path — physical products and every current flow are completely untouched. Ship as one PR after B1.

## 10. Resolved decisions

- **Only `Confirmed` bookings block overlap** (decided). A `Completed` booking is in the past and its slot is spent; `Cancelled` already frees its slot. New placements are constrained purely by future `Confirmed` bookings of the same service. B3/B4 must not assume otherwise.
- **Return both-neighbor buffer warnings** (decided). The travel-buffer check surfaces both the preceding-neighbor gap (new booking starts too soon after the previous ends) and the following-neighbor gap (next booking starts too soon after the new one ends); B3 decides which to render. Cheap and more informative.
- **Bookable services require a positive duration** (decided). B3 validation enforces `duration_minutes > 0` for `requires_booking = true`; B2 still documents and defensively tests the half-open zero-duration behavior (same-instant zero-duration bookings never overlap) but it is not a supported real case.
- **Push the overlap predicate to SQL** (decided, impl detail). Query `status = Confirmed AND service_product_id = :id AND starts_at < :end AND ends_at > :start` in the DB for scale, with a shared constant defining the boundary operators and a unit test asserting byte-for-byte parity with the in-memory T2.1 primitive.
