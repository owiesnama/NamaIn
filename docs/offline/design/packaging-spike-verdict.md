# Packaging Spike Verdict (Design 01 §7 / PRD-01 FR-16)

> Status: **Works with changes** · Date: 2026-07-04
>
> Method: the full NamaIn app was booted self-contained on a local SQLite file
> (`DB_CONNECTION=sqlite`, file/sync/log fallback drivers, no Postgres/Redis/external
> services) and driven end-to-end over HTTP — tenant onboarding, login, POS session,
> checkout, receipt, idempotent replay — on the `feat/offline-sync-foundations` branch.
> NativePHP itself was not installed (dependency change deferred to the offline-app
> repo's PR-O2); this spike proves the Laravel-on-SQLite substrate NativePHP wraps.

## Verified working

- All 114 migrations run clean on SQLite; onboarding provisions R0 + `tenant_sync_state`.
- POS session page renders; checkout completes; serial allocated from the register
  counter (`INV-SA-26-R0-00001`); receipt page renders it.
- Idempotent replay holds on SQLite (duplicate checkout → 1 invoice; stock 100 → 97
  for a single 3-unit sale).
- Change log gap-free per tenant (23 entries across the flow), both capture channels
  (Eloquent trait + raw-SQL `stocks` sites).
- Cache/session/queue/mail degrade to `file`/`sync`/`log` drivers with **zero code
  changes**; Redis, Horizon, Reverb, and Telescope are not needed for the POS path.

## Incompatibilities found (the "changes" in works-with-changes)

1. **`ilike` in POS product search 500s on SQLite** (`PosSessionController`,
   pgsql-only operator) — confirmed live, exactly as Design 01 §7 predicted. Fix:
   driver branch. Ships in the cloud seams PR (Design 03 seam S6).
2. **PHP 8.5 deprecation notice breaks `php -S` header flushing**
   (`PDO::MYSQL_ATTR_SSL_CA` in `config/database.php` emits output before headers,
   killing cookies/redirects under the CLI server). Fix: guard the constant for
   PHP 8.5. Unrelated to offline work → its own PR. NativePHP's server model may not
   hit this, but the fix is correct regardless.

## Verdict for PR-O2 (NativePHP shell)

Proceed. The substrate is proven; remaining risk is confined to NativePHP packaging
mechanics (Electron shell, appdata paths, child processes) rather than the Laravel
app's ability to run offline.
