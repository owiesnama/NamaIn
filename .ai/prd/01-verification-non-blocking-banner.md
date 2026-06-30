# PRD 01 — Verification non-blocking + dashboard banner

**Batch:** C — Auth + Cost · **Branch:** `feat/auth-cost` · **Item:** 1

## Problem

Email verification currently blocks registration and login. New/unverified users are bounced to a
full-page `verification.notice` interstitial and cannot reach the app. Merchants want registration
and login to succeed immediately, with a non-intrusive hint on the dashboard prompting the user to
verify their email.

## Goal

Unverified users can register, log in, and use the app. The dashboard shows a dismissible banner
prompting verification, with a working "resend email" action. The verify/resend machinery stays
intact so a user can still complete verification.

## Requirements

1. **Stop blocking on the `verified` middleware.** Remove `verified` from the protected route
   groups:
   - `routes/tenant.php:101` and `routes/tenant.php:121` (both tenant middleware groups).
   - `routes/web.php:46` (`tenants.select` / `tenants.switch`).
2. **Relax the login gate.** In `app/Http/Controllers/Auth/TenantLoginController.php:35-39`, remove
   the `hasVerifiedEmail()` redirect to `verification.notice`; unverified users should proceed to
   the tenant dashboard like verified users.
3. **Expose verification status to the frontend.** In
   `app/Http/Middleware/HandleInertiaRequests.php` `share()` (the `user` array, ~lines 51-59), add
   the verification flag, e.g. `'email_verified_at' => $request->user()?->email_verified_at` and/or
   a derived `'verified' => $request->user()?->hasVerifiedEmail()`.
4. **Dashboard banner.** In `resources/js/Pages/Dashboard.vue`, render a banner only when the
   shared user is unverified. Follow `.ai/Design rules` (flat design; amber/warning palette is
   appropriate for a "please verify" hint, emerald for the resend CTA). Include a "Resend email"
   action that posts to Fortify's `verification.send` route. Banner should be dismissible for the
   session (local state is fine; it must reappear on reload while still unverified).
5. **Keep verification feature intact.** Do **not** remove `Features::emailVerification()` from
   `config/fortify.php` or `MustVerifyEmail` from `app/Models/User.php`. The `verification.send`,
   `verification.verify` routes and `app/Http/Responses/VerifyEmailResponse.php` post-verify routing
   must keep working.

## Implementation notes / files

- `routes/tenant.php`, `routes/web.php` — drop `verified` from middleware arrays.
- `app/Http/Controllers/Auth/TenantLoginController.php` — remove the verification redirect block.
- `app/Http/Middleware/HandleInertiaRequests.php` — add verified flag to shared `user`.
- `resources/js/Pages/Dashboard.vue` — banner component + resend action.
- Existing verify page `resources/js/Pages/Auth/VerifyEmail.vue` stays as the post-link landing.

## Testing (mandatory)

**Pest** — rewrite `tests/Feature/TenantEmailVerificationTest.php` (it currently asserts the old
blocking behavior and will fail):
- An unverified user can log in and reach `/dashboard` (no 409 / no `verification.notice` redirect).
- A freshly registered user lands on the dashboard.
- The Inertia `user` shared prop carries the unverified flag.
- `verification.send` still dispatches the notification; `verification.verify` still marks verified.

**Cypress** — register or log in as an unverified user → assert dashboard renders and the verify
banner is visible; click resend → assert success feedback. Verified users see no banner.

## Acceptance criteria

- [ ] Unverified registration/login reaches the dashboard.
- [ ] Banner shows only for unverified users; resend works.
- [ ] Verify/resend routes and post-verify routing still function.
- [ ] Pest + Cypress green; `vendor/bin/pint --dirty` clean.
