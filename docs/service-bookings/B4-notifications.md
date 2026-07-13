# PRD — B4: Notifications (24h reminder + cancellation notice)

**Status:** Draft · **Milestone:** B4 · **Depends on:** B1 (Booking model + `Cancelled` status); realistically ships after B2/B3 · **PR grouping:** one PR (T4.1, T4.2, T4.3, T4.4)

## 1. Problem

A confirmed booking is a commitment on the merchant's calendar, and today nothing reminds them it is coming or tells them when one is cancelled. v1 needs two merchant-facing messages: a **single reminder 24 hours before start** (fixed offset, not configurable) and a **cancellation notice** when a booking is cancelled. Clients are **not** notified in v1 — there is no client portal and no client-side delivery. The work is to ride the existing, mature Laravel Notifications pipeline (which already gives us mail + a database bell + live broadcast) rather than invent a new delivery mechanism. Real WhatsApp/SMS delivery does not exist in this codebase and is explicitly deferred; B4 leaves a clearly-marked TODO channel seam, nothing live.

## 2. Goals / Non-goals

**Goals**
- `BookingReminderNotification` delivered once per booking, ~24h before `starts_at`, to the merchant.
- `BookingCancelledNotification` delivered to the merchant the moment a booking is cancelled.
- A scheduled command that scans upcoming confirmed bookings and sends the reminder exactly once, correctly across all tenants (unbound context).
- Both notifications light the existing frontend notification bell for free via the `broadcast` channel, and persist in the database channel.
- A documented, **inert** SMS/WhatsApp channel seam so a future initiative can wire a real provider without rearchitecting.

**Non-goals**
- Any client-facing notification, portal, or self-service (out of scope for v1).
- Configurable reminder timing or multiple reminders (fixed 24h, exactly one).
- Wiring a real WhatsApp/SMS provider (Twilio or `mazin_host`) — TODO only, no credentials, no live send.
- Reminder for services with `requires_booking = false` (they create no booking).

## 3. Current state (from audit)

- **Notification blueprint to clone:** `app/Notifications/ChequeDueNotification.php` — a due-date reminder that `implements ShouldQueue`, `via()` returns `['mail','database','broadcast']` (`ChequeDueNotification.php:25`), with `toMail()` (MailMessage), `toArray()` (database payload), and `databaseType()`/`broadcastType()` string tags the frontend bell keys on. `app/Notifications/AnnouncementNotification.php` shows the conditional-channel variant (`AnnouncementNotification.php:22-31`).
- **Scheduled-scan blueprint:** `routes/console.php:18-32` defines inline Artisan command `cheques:notify-for-due` — queries records due within a config window (`->where('due','<=',now()->addDays(config('app.cheques_notify_before_days',3)))`), lifts the tenant global scope with `Cheque::withoutGlobalScopes()` (**critical** — scheduled commands run unbound, so `TenantScope` would otherwise fail closed and return nothing), loops recipients, calls `$admin->notify(new ChequeDueNotification(...))`; registered `Schedule::command('cheques:notify-for-due')->daily()` (`routes/console.php:32`). A 90-day notifications prune and other `->daily()` scans (`expenses:generate-recurring`, `stock:reconcile`, `exports:prune`, `backup:scheduled`) live alongside.
- **Queue/broadcast infra is live:** Horizon `^5.46` + Redis queues (`config/queue.php:65-86`), queued-job pattern `app/Jobs/SendAnnouncementJob.php:18-33` (`Notification::send()` over chunked recipients). Reverb/Echo are real: `resources/js/Components/NotificationBell.vue:107-117` listens on `window.Echo.private('App.Models.User.'+id).notification(...)`, so adding `broadcast` to `via()` lights the bell with no frontend work. Channels authorized in `routes/channels.php` (`App.Models.User.{id}`).
- **No working WhatsApp/SMS delivery exists:** `laravel-notification-channels/twilio` is in `composer.json:15` but has **zero** usage — no `toTwilio()`, no `TwilioChannel`, no `routeNotificationForTwilio`, no `services.twilio` config. `config/services.php:34-36` has a dangling, unreferenced `mazin_host` SMS stub (`MAZIN_HOST_SMS_API_KEY`, `MAZIN_HOST_SENDER_ID`), no channel class, no env keys in `.env.example`. The notifiable (`app/Models/User.php`) has no `routeNotificationFor*` method today.
- **Merchant recipients:** users belong to a tenant via the `tenant_user` pivot (role_id, is_active); the reminder/cancel audience is the tenant's owner + admins, resolved the same way `cheques:notify-for-due` resolves its admins.

## 4. Design & behavior

Two notifications cloned from `ChequeDueNotification`, both `ShouldQueue`, both `via()` → `['mail','database','broadcast']`, both carrying a `databaseType()`/`broadcastType()` tag (`booking.reminder`, `booking.cancelled`) and a `toArray()` payload with booking id, service name, customer name, `starts_at`, and (for reminders) address when on-site. All copy localized via `__()`, Arabic-first; times/addresses isolated for bidi per the design rules.

**Reminder scheduling.** A new Artisan command `bookings:notify-upcoming` clones the `cheques:notify-for-due` shape:
- Scan `Booking::withoutGlobalScopes()` where `status = Confirmed`, `reminder_sent_at IS NULL`, and `starts_at <= now()->addDay()` (i.e. start is within the next 24h) **and** `starts_at >= now()` (not already past).
- For each match, resolve that booking's tenant's merchant users and `notify(new BookingReminderNotification($booking))`, then stamp `reminder_sent_at = now()` in the same pass so the booking is reminded **exactly once** (dedupe). Wrap the per-booking stamp so a re-run can't double-send.
- **Cadence: hourly.** Justified — the offset is a specific 24h point; a `->daily()` scan (as cheques use) would fire the reminder anywhere from ~0 to ~24h before start depending on run time, which is too coarse for an appointment. Hourly bounds the reminder to within an hour of the intended 24h mark at negligible cost. Registered `Schedule::command('bookings:notify-upcoming')->hourly()` in `routes/console.php`.

**Cancellation notice.** Fired imperatively from the cancel path (the cancel action/controller introduced in B3), **not** the scheduler: on transition to `Cancelled`, `notify(new BookingCancelledNotification($booking))` to the merchant users. This keeps it immediate and avoids a scan.

**SMS/WhatsApp seam.** Add a documented but inert channel: a `routeNotificationForSms()`/`toSms()` (or `toTwilio()`) stub on the notifiable/notifications marked `// TODO: no live SMS/WhatsApp provider wired — see B4 §10`, and a note of the env keys a real provider would need. `via()` does **not** include the SMS channel in v1, so nothing is sent. No provider credentials, no `services.twilio`/`mazin_host` wiring.

## 5. Data model / schema changes

- **One additive column** on `bookings`: `reminder_sent_at` (nullable timestamp) — the dedupe marker so the hourly scan reminds each booking once. Additive, reversible.
- No other schema change. Notifications persist in the existing `notifications` table (database channel) exactly like `ChequeDueNotification`.

## 6. Task specs

### T4.1 — `BookingReminderNotification` · **M**
- **Behavior:** notification cloned from `ChequeDueNotification` — `implements ShouldQueue`, `via()` → `['mail','database','broadcast']`; `toMail()` a localized MailMessage naming the service, customer, and start time (address line when the service is on-site); `toArray()` database payload (booking id, service name, customer name, `starts_at`, on-site address); `databaseType()`/`broadcastType()` → `booking.reminder`. Constructor takes the `Booking`.
- **Files:** *new* `app/Notifications/BookingReminderNotification.php`.
- **Edge cases:** on-site vs not (include/omit address line); missing customer name; all strings via `__()`; consistent numeral system and bidi isolation for time/address.
- **Acceptance criteria:** queued; three channels present; database + broadcast type tags correct; bell renders it; no hard-coded English.
- **Test plan:** unit/feature with `Notification::fake()` asserting the notification, its channels, and payload shape for on-site and non-on-site bookings.

### T4.2 — `bookings:notify-upcoming` command + dedupe column + schedule · **M**
- **Behavior:** additive migration adding `bookings.reminder_sent_at` (nullable timestamp). New inline/console command `bookings:notify-upcoming` cloning `cheques:notify-for-due`: scan `Booking::withoutGlobalScopes()` where `status = Confirmed AND reminder_sent_at IS NULL AND starts_at >= now() AND starts_at <= now()->addDay()`; per booking resolve the tenant's merchant users, notify, and stamp `reminder_sent_at`. Register `Schedule::command('bookings:notify-upcoming')->hourly()`.
- **Files:** *new* migration `..._add_reminder_sent_at_to_bookings_table.php`; command in `routes/console.php` (or `app/Console/Commands/` following `GenerateRecurringExpenses.php`); schedule registration in `routes/console.php`.
- **Edge cases:** unbound tenant context — must lift `TenantScope` and still notify the *correct* tenant's users; command run twice in the same hour must not double-send (dedupe via `reminder_sent_at`); booking created <24h before start → picked up on the next hourly scan (window is `<= now+24h` and not-yet-reminded), which is the desired behavior; bookings already past (`starts_at < now()`) skipped; timezone — comparison is instant-based against `now()` (app tz Africa/Khartoum, no DST) so no wall-clock drift.
- **Acceptance criteria:** exactly one reminder per eligible booking; none for cancelled/completed/past/already-reminded bookings; re-running the command sends nothing new; cross-tenant correctness.
- **Test plan:** feature tests with `Notification::fake()` + time travel: reminder sent for a booking starting in ~23h, not for one in ~30h, not for cancelled/completed/past bookings, not sent twice on a second run; multi-tenant test asserting each tenant's merchant is the recipient.

### T4.3 — `BookingCancelledNotification` fired on cancel · **S**
- **Behavior:** notification cloned from `ChequeDueNotification` (`ShouldQueue`, `via()` → mail+database+broadcast, type tag `booking.cancelled`, localized). Fired from the cancel action/controller path (B3) to the tenant's merchant users when a booking transitions to `Cancelled`.
- **Files:** *new* `app/Notifications/BookingCancelledNotification.php`; hook the `notify()` call into the cancel action introduced in B3 (reference only — the action lives in B3).
- **Edge cases:** cancelling an already-cancelled booking must not re-notify (guard on the status transition, owned by B3's action); notification independent of whether a reminder was already sent.
- **Acceptance criteria:** merchant receives exactly one cancellation notice per cancel transition; three channels present; bell renders it.
- **Test plan:** feature test with `Notification::fake()` cancelling a confirmed booking and asserting the notice to merchant users; asserting no notice on a no-op re-cancel.

### T4.4 — SMS/WhatsApp TODO channel scaffold (inert) · **S**
- **Behavior:** add a documented but non-functional SMS/WhatsApp seam — a `routeNotificationForSms()` (or `toTwilio()`) stub marked TODO, plus a comment listing the env keys a real provider would need (the installed-but-unused Twilio channel, or the `mazin_host` stub). `via()` **excludes** the SMS channel in v1 so nothing is sent. No credentials, no `services.twilio`/`mazin_host` activation.
- **Files:** `app/Models/User.php` (routing stub, TODO); a short note in this PRD / a code comment referencing §10; optionally a placeholder `app/Channels/` doc-comment — no live class.
- **Edge cases:** the stub must never be reached by `via()` (guard against accidental enable); no runtime dependency on Twilio/mazin_host config.
- **Acceptance criteria:** no SMS/WhatsApp is ever sent in v1; the seam is discoverable and documented; test suite unaffected.
- **Test plan:** assert `via()` returns only `['mail','database','broadcast']` (no sms/twilio channel) for both notifications.

## 7. Edge cases (cross-task)

- **Unbound tenant scan:** the hourly command runs outside any tenant; it must `withoutGlobalScopes()` to see all bookings yet still resolve and notify each booking's own tenant's merchant users (never cross-notify).
- **Idempotency:** a second run within the hour, an overlapping run, or a retried queue job must not produce a duplicate reminder — enforced by `reminder_sent_at`.
- **Late creation:** a booking created 10h before start is reminded on the next hourly scan (correct — within the 24h window and not yet reminded), not skipped.
- **State transitions after reminder:** a booking cancelled after its reminder was sent gets a cancellation notice but no further reminder.
- **Timezone:** all window math is instant-based against `now()`; app timezone is Africa/Khartoum (no DST), so there is no wall-clock ambiguity, but tests should still pin time explicitly.
- **`requires_booking = false`:** such services create no booking, so they never enter the reminder scan.

## 8. Test plan (summary)

- `Notification::fake()` feature tests for reminder eligibility (in-window vs out-of-window vs past), status exclusion (cancelled/completed), once-only dedupe on re-run, and multi-tenant recipient correctness (T4.1/T4.2).
- Cancellation notice fired on cancel, not on no-op re-cancel (T4.3).
- `via()` returns only mail+database+broadcast — no SMS ever sent (T4.4).
- Regression: existing notification and scheduled-command tests stay green; the new column is additive.

## 9. Rollout & backwards compatibility

Fully additive: one nullable `bookings.reminder_sent_at` column, two new notification classes, one new scheduled command, and an inert SMS seam. No existing notification, schedule entry, or table changes. Existing tenants are unaffected until they have service bookings. The reminder command is safe to enable immediately (it no-ops when there are no eligible bookings). SMS/WhatsApp stays off until a future initiative wires a real provider.

## 10. Resolved decisions

- **Hourly scan** (decided). `bookings:notify-upcoming` runs hourly (`Schedule::command(...)->hourly()`) so the fixed 24h reminder lands within ~1h of the true offset — a light query on the indexed `starts_at`/`reminder_sent_at` window.
- **`reminder_sent_at` column for dedupe** (decided). Add a nullable `reminder_sent_at` timestamp to `bookings` (additive migration in T4.2); the scan filters `reminder_sent_at IS NULL` within the window and stamps it after dispatch, keeping the scan cheap, indexable, and self-contained. No dependency on the `notifications` table for dedupe.
- **Recipients = owner + admins** (decided). Both the reminder and the cancellation notice go to the tenant **owner and all admin users**, resolved in the unbound scan exactly as `cheques:notify-for-due` resolves its admin recipients. Clients are never notified in v1 (no client portal).
- **Real SMS/WhatsApp provider deferred** (decided). B4 leaves the `via()` seam + a marked TODO channel only; choosing between the installed-but-unused Twilio channel and the `mazin_host` stub — and adding the env keys / `routeNotificationFor*` — is a future initiative, not this milestone.
