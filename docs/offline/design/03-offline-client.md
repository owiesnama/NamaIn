# Design 03 — Offline Client, NativePHP (PRD-03)

> Status: Design for review · Owner: offline client · Implements: [PRD-03](../prds/prd-03-offline-client.md) · Phase 2
>
> Depends on [Design 01](01-sync-foundations.md) (`public_id`, registers/devices, per-register
> serials, change log, `sync_idempotency`, `treasury_accounts.register_id`, the
> `namain/sync-protocol` package) and [Design 02](02-sync-protocol.md) (provisioning, JSONL
> snapshot + manifest, pull/push contracts, cached-bcrypt local auth, attachments endpoint,
> `426 upgrade_required`). Those decisions are **inputs, not options** — this doc builds on
> them. Where the codebase forced a different reading, it is recorded in
> [§10 Disputes with upstream design](#10-disputes-with-upstream-design).

---

## Decisions at a glance

| # | Area | Decision | PRD FR |
|---|---|---|---|
| C1 | Repo reuse | **Build-time composition**: the offline repo holds a strictly *additive* overlay (NativePHP shell, `app/Local/**`, local migrations, wizard/sync UI) plus a pinned `CLOUD_REF`; a compose step checks out the cloud app at that ref, copies the overlay on top (hard failure on any file collision), and runs mechanical `composer require` for shell deps. Cloud files are never patched; anything that must change in a cloud file lands **upstream as a seam** (C2). | roadmap #6 |
| C2 | Runtime seams | A minimal, enumerated set of hooks in the cloud repo: `config/runtime.php` + `App\Support\Runtime`, a providers hook in `bootstrap/providers.php`, a route branch in `bootstrap/app.php`, a `runtime.online` middleware, a `runtime` Inertia shared prop, and the `ilike` driver branch. Six seams, all greppable, all shipped as cloud PRs. | FR-3 |
| C3 | Tenant binding | `LocalRuntimeServiceProvider::boot()` binds `currentTenant` from the single local `tenants` row on every request; no subdomain routing. Before provisioning completes there is no tenant row, so `TenantScope` keeps failing closed. | FR-2 |
| C4 | Disabled subsystems | Pure env profile (`QUEUE_CONNECTION=sync`, `CACHE_DRIVER=file`, `SESSION_DRIVER=file`, `BROADCAST_CONNECTION=null`, `MAIL_MAILER=log`, `TELESCOPE_ENABLED=false`) + Horizon/Telescope providers excluded via the providers seam. Nothing forked. | FR-1 |
| C5 | Sync worker | **One persistent NativePHP child process** (`ChildProcess::artisan('sync:worker', persistent: true)`) running a supervised loop: push tick 5 s, pull tick 60 s + pull-after-push. Sync runs only while the app runs. Not the scheduler, not Laravel queues. | FR-15 |
| C6 | Durability | SQLite in the NativePHP appdata dir with `journal_mode=WAL`, `synchronous=FULL`, `busy_timeout=5000`. Outbox row inserted in the same transaction as the business write (container **decorators** around the five Actions). `in_flight` rows revert to `pending` on worker start. | FR-14, FR-18 |
| C7 | Auto-update | NativePHP updater on an S3-compatible bucket (`latest` channel). `426 upgrade_required` pauses sync (status surfaced in chrome) but never blocks the POS. Update applies on restart; NativePHP auto-migrates on version change. | FR-4 |
| C8 | Provisioning | Resumable 6-state wizard persisted in a single-row `sync_state` table: `fresh → paired → snapshot_requested → downloading → applying → ready`. Snapshot applied per-entity through one upsert path (same code as pull-apply). Register is chosen cloud-side at enrollment; the wizard confirms it. | FR-5..7 |
| C9 | Local checkout | The existing Actions run locally. One shared cloud-side extraction — `CheckoutContext` (register, stock policy, optional preset ids/serial) on `ProcessPosCheckoutAction` — serves web (strict, R0), push replay (`ReplayPosSaleAction`), and the local runtime (force-deduct, no auto-replenishment transfers, local serial mint). | FR-8..9 |
| C10 | Stale-data POS | Local `stocks` gains a local-only `synced_quantity` column: `quantity` = live local, `synced_quantity` = last pulled server truth. Pull-apply for `stocks` sets `quantity = payload.quantity − pendingLocalDeduction()` so pulls never clobber unsynced sales. Staleness chip (time since last pull) in the POS header. | FR-12, FR-16 |
| C11 | Credit offline | Allowed; limit check runs against `synced balance + local unsynced credit sales`, labeled "as of {last pull}". Breach shows a confirm dialog, never a block. | FR-10 |
| C12 | Expenses offline | `StoreExpenseAction` runs locally; drawer fixed to the register's treasury account; **existing categories only** (no offline category creation); recurring hidden. Attachments stored locally, uploaded via `POST /attachments` after the `expense.create` push, linked by `receipt_public_id`. | FR-13 |
| C13 | Outbox home | Overlay code: `app/Local/Outbox` (decorators + tables), `app/Local/Sync` (worker, push runner, pull applier, resolver, transport), `app/Local/Provisioning`, `app/Local/Auth`. The sync-lib stays pure wire contract + request builders/parsers, per Design 01 §8. | FR-14..16 |
| C14 | Lifecycle | Prune synced history > 90 days (configurable) and applied outbox rows > 30 days; never prune unsynced. Detach requires pending = 0 (parked requires typed confirmation + encrypted export). Persistent 401 ⇒ revoked ⇒ wipe-on-launch after exporting unsynced outbox to an encrypted support file. | FR-19..20 |
| C15 | Receipts & printing | The Inertia `Invoices/Receipt` page renders locally with the final serial; printing stays the browser print dialog (Electron) for MVP; ESC/POS native printing deferred to Phase 4. | FR-11, §7.4 |

---

## 0. Ground truth found in the code (what PRD-03 got slightly wrong)

Verified against the codebase; these shape the design and correct PRD assumptions:

1. **There is no register concept in the POS flow today.** The drawer is
   `TreasuryAccount::where('sale_point_id', $storage_id)->ofType(Cash)` in all three of
   `OpenPosSessionAction`, `ClosePosSessionAction`, and `ProcessPosCheckoutAction`
   (`app/Actions/Pos/*`). Design 01 §2.3 adds `treasury_accounts.register_id` and defers the
   lookup switch to PRD-02 — the local runtime is the *second* consumer of that switch (§5.2).
2. **The serial is assigned by `InvoiceObserver` on `created` using the PK**, with a second
   `save()` inside the checkout transaction. PRD-01 PR-3 replaces this with the
   `SerialNumberGenerator` in the create path; local minting (FR-9) depends on that PR, not on
   anything in this repo's current code.
3. **Checkout executes auto-replenishment `StockTransfer`s inside itself** when
   `acknowledge_transfers` is true — it does not merely trust the preflight. Stock transfers
   are *not* one of the five MVP mutation types, so the local runtime must not create them
   (§5.2). The preflight (`PosPreflightAction`) is purely advisory JSON.
4. **The receipt is an Inertia Vue page, not Blade** (`InvoiceReceiptController::show` →
   `Invoices/Receipt`). PRD-03's "server-rendered receipt page" still holds in the sense that
   matters: the route + data live server-side and render locally unchanged (FR-11).
5. **Expense approval is manual-only.** No thresholds, no tenant preference. `expenses.status`
   defaults to `'pending'` at the DB level; `StoreExpenseAction` never sets it, and the treasury
   outflow is recorded **at creation regardless of approval**. So FR-13's "sync as pending
   approval" is free: a pushed `expense.create` lands pending by default (§5.3).
6. **Expenses have no register/session link today**; `treasury_account_id` is user-selected and
   nullable (no movement recorded when null). The local UI pins it to the register's drawer.
7. **Expense attachments are two-phase FilePond uploads** (`receipt` is a temp *filename*
   string; `HandlesAsyncUploads::resolveTemporaryUpload` moves `tmp/… → receipts/…` on the
   `local` disk). The offline flow reuses the same private-disk pattern locally (§5.3).
8. **Providers are a static array** (`bootstrap/providers.php`) registering Horizon and
   Telescope unconditionally; broadcasting is wired via `->withBroadcasting()` in
   `bootstrap/app.php`; Reverb is only a broadcast connection. The env profile alone silences
   Reverb/broadcasts/queues; Horizon/Telescope need the providers seam (§2.2).
9. **Routing is hard-bound to subdomains** in `bootstrap/app.php` (`routes/tenant.php` on
   `{tenant}.domain`). `ResolveTenant` passes through when the `tenant` route param is absent —
   so mounting `tenant.php` without a domain constraint locally is safe (§2.3).
10. **`ilike` confirmed** at `PosSessionController::show()` (product grid search,
    `->where('name', 'ilike', "%{$search}%")`) — pgsql-only, must be driver-branched (cloud PR;
    already flagged by Design 01 §7).
11. **The checkout idempotency key really is `Date.now().toString()`** in
    `resources/js/Pages/Pos/Session.vue` (`checkout()`); two registers can collide on it in
    principle even today. Fixed upstream with `crypto.randomUUID()` (§6.2).
12. **Localization ships as `lang/ar.json` only** — English strings are the keys. Bundling the
    app bundles the catalogs; `HandleInertiaRequests`'s `cache()->rememberForever` works on the
    `file` cache driver. Pull-apply of `preferences` must `Cache::forget('preferences')` (and
    locale changes must forget `translations.{locale}`) or the local UI shows stale settings.
13. **NativePHP is not installed** — no `nativephp/*` in `composer.json`, no config, no entry
    point. The shell is greenfield, which is exactly why decision #6 puts it in its own repo.

---

## 1. Offline-app repo structure (roadmap decision #6)

### 1.1 The problem, stated honestly

The cloud repo is a Laravel *application* (`"type": "project"`, `App\` autoloaded from `app/`,
routes/migrations/Inertia pages/Vue components in skeleton paths), not a package. The cloud
team ships weekly. The offline client must be byte-for-byte the same business logic (PRD-03
goal: "a runtime profile, not a fork") while living in its own repo. Any mechanism that
maintains a *diff against cloud files* will rot within a month of weekly cloud PRs.

### 1.2 Decision: build-time composition — pinned upstream + additive overlay

The offline repo (`namain/offline-app`) contains **no copy of the cloud source**. It contains:

```
namain-offline-app/
├── CLOUD_REF                      # pinned cloud repo ref (tag/sha), bumped deliberately
├── compose/
│   ├── compose.sh                 # clone cloud@CLOUD_REF → .build/app, overlay, mechanical steps
│   └── requirements.json          # composer packages to `composer require` mechanically
├── overlay/                       # copied ONTO the cloud tree — additive files only
│   ├── app/Local/                 # all offline PHP code (namespace App\Local\…)
│   │   ├── Outbox/                # decorators + payload builders          (§6)
│   │   ├── Sync/                  # worker, push runner, pull applier, transport
│   │   ├── Provisioning/          # wizard state machine, snapshot applier
│   │   ├── Auth/                  # lockout counter, must-change-password gate
│   │   └── LocalRuntimeServiceProvider.php
│   ├── app/Providers/NativeAppServiceProvider.php
│   ├── bootstrap/local-providers.php      # returned providers, loaded via the seam (§2.2)
│   ├── config/nativephp.php
│   ├── database/migrations/local/ # outbox, sync_state, parked columns, synced_quantity (§6.1)
│   ├── routes/local.php           # wizard, sync screen, detach, local status endpoints
│   ├── resources/js/Pages/Local/  # wizard + sync screens (picked up by the existing Vite glob)
│   └── resources/js/Components/Local/   # sync chrome, staleness chips
├── tests/                         # Pest tests that run against the composed tree
├── .github/workflows/             # compose + test + native:build pipelines
└── docs/
```

`compose.sh` (also run by CI and by `compose --dev` for day-to-day work):

1. `git clone --depth 1 --branch $(cat CLOUD_REF) <cloud-repo> .build/app`
2. Copy `overlay/` onto `.build/app/` — **abort with a hard error if any overlay path already
   exists in the cloud tree** (additive-only is enforced by the build, not by convention).
3. Mechanical, conflict-free transforms only: `composer require nativephp/electron
   namain/sync-protocol` (semantic JSON merge, never a text patch) and write the local `.env`
   profile (§2.1). No sed, no patches, no source edits.
4. `--dev` mode symlinks overlay files instead of copying, so edits inside `.build/app` flow
   back into the overlay repo.

Anything that would require *modifying* a cloud file is, by definition, a **seam** and ships as
a PR to the cloud repo (§2.2). That inverts the drift problem: the seams live in the cloud
repo's own test suite, so weekly cloud PRs keep them green; the overlay can only collide with
cloud changes by literal file-path collision, which the compose step turns into an immediate,
attributable build failure instead of a silent merge conflict. Bumping `CLOUD_REF` is a normal
reviewed PR in the offline repo whose CI runs the full composed test suite — that is the drift
firewall.

Versioning: the desktop app version (`NATIVEPHP_APP_VERSION`) is minted by the offline repo and
maps 1:1 to a `(CLOUD_REF, overlay ref)` pair recorded in the release notes; `X-App-Version`
(Design 02 §8.4) carries it.

### 1.3 Rejected alternatives (record for review — this will be challenged)

- **Composer path/VCS dependency on the cloud repo.** A Laravel app is not a composer package:
  requiring it puts a second `App\` namespace in `vendor/` (fatal collision with the shell's
  own `App\`), and none of its migrations/routes/views/Vue pages load without writing package
  service providers into the cloud repo — i.e. it degenerates into the restructure option
  below, with extra steps. The frontend cannot flow through composer at all. Rejected.
- **Restructure NamaIn into `namain/core` consumed by two thin shells.** The architecturally
  "correct" one-codebase shape, and the likely long-term destination — but it moves every file
  in a weekly-shipping application, breaks every open PR, and violates Phase 0's "invisible to
  current web users" rule. Months of churn before the first offline build. Rejected for this
  phase; revisit after the pilot (PRD-04) when the seam inventory tells us what a core package
  actually needs to export.
- **Git submodule.** Pins well, but a submodule at `core/` still cannot produce a single
  runnable Laravel tree without a compose step — so it is composition with worse ergonomics
  (detached heads, forgotten `--recurse-submodules`). Rejected.
- **Git subtree / merge-tracking fork.** Same overlay idea executed through git merges. Works,
  but the additive-only discipline is enforced only socially, and violations surface as merge
  conflicts inside merge commits — the hardest place to review. Composition turns the same
  violation into a deterministic build failure. Rejected in favor of composition.
- **Monorepo: add NativePHP to the cloud repo behind env flags.** Simplest mechanically, but it
  violates settled decision #6, ships Electron/packaging toolchains into cloud CI, and couples
  desktop release cadence to cloud deploys. Rejected (locked).

---

## 2. Runtime profile (FR-1..4)

### 2.1 One codebase, two boots — the env profile

The config layer already boots offline with **zero config-file edits** (§0.8). The composed
`.env`:

```dotenv
RUNTIME_PROFILE=local
APP_URL=http://localhost            # NativePHP serves on a local port
DB_CONNECTION=sqlite                # NativePHP points this at appdata/database.sqlite
QUEUE_CONNECTION=sync               # jobs degrade inline; no Horizon, no Redis
CACHE_DRIVER=file
SESSION_DRIVER=file
BROADCAST_CONNECTION=null           # Reverb/Echo silent; ExportStatusUpdated broadcasts no-op
MAIL_MAILER=log
TELESCOPE_ENABLED=false
NATIVEPHP_UPDATER_PROVIDER=s3       # §3.4
```

The SQLite connection config additionally sets `journal_mode=wal`, `synchronous=full`,
`busy_timeout=5000` (§3.2), via `config/nativephp`-side connection overrides in
`LocalRuntimeServiceProvider::register()` (no cloud config edit needed —
`config(['database.connections.sqlite.…'])` at register time).

### 2.2 The `runtime` abstraction and the six seams (FR-3)

All cloud-side hooks, shipped as one small cloud PR (Implementation notes, §12). This is the
complete list — adding a seventh seam requires a design-doc amendment, which is what keeps FR-3
"kept to a minimum" honest:

| Seam | Cloud change | Local behavior |
|---|---|---|
| S1 `config/runtime.php` + `App\Support\Runtime` | `'profile' => env('RUNTIME_PROFILE', 'cloud')`; static `Runtime::isLocal()/isCloud()`. The **only** branching API — greppable as `Runtime::` | returns `local` |
| S2 providers hook | `bootstrap/providers.php` becomes: base providers + Horizon/Telescope only when `env('RUNTIME_PROFILE','cloud')==='cloud'` + `array_merge(require bootstrap/local-providers.php)` when that file exists | overlay file registers `LocalRuntimeServiceProvider`, `NativeAppServiceProvider` |
| S3 route branch | `bootstrap/app.php`: when local profile, mount `routes/tenant.php` with **no domain constraint** and `routes/local.php` if present; skip `routes/web.php`'s admin surface | no subdomains; `ResolveTenant` passes through (no `tenant` param, §0.9) |
| S4 `runtime.online` middleware | tiny middleware: `abort_unless(Runtime::isCloud(), 404)`; applied to online-only route groups (purchases, transfers, quotes, treasury, reports, exports, team, recurring-expenses, settings-write) | those routes 404 → hidden, not broken |
| S5 `runtime` Inertia prop | `HandleInertiaRequests::share()` adds `'runtime' => config('runtime.profile')`; a `useRuntime()` composable wraps it | nav + UI gating (§8) |
| S6 driver fixes | `ilike` → driver-branched in `PosSessionController`; `Session.vue` idempotency key → `crypto.randomUUID()` | POS search + honest replay keys on SQLite |

Cloud behavior is bit-identical when `RUNTIME_PROFILE` is unset (every seam defaults to
`cloud`). The seams are covered by cloud tests (a `RUNTIME_PROFILE=local` boot smoke test runs
in cloud CI so a cloud refactor cannot silently break the local profile).

### 2.3 Single-tenant binding (FR-2)

`LocalRuntimeServiceProvider::boot()` (runs per request — the desktop app boots the framework
per request like FPM):

```php
if (! app()->bound('currentTenant') && ($tenant = Tenant::query()->first())) {
    app()->instance('currentTenant', $tenant);
    // locale from preferences, mirroring GenerateExportJob::bindTenantContext()
}
```

- Exactly one `tenants` row exists locally, created by the provisioning wizard from the
  `/provision` response (§4.2). Before provisioning there is no row → nothing binds →
  `TenantScope` fails closed to `1 = 0`, exactly as on the cloud. The fail-closed invariant is
  asserted by an overlay Pest test (fresh DB, any tenant-scoped query returns empty, never
  throws).
- `BelongsToTenant::creating` auto-fills `tenant_id` from the binding — local writes are
  tenant-stamped with the provisioned tenant with no code changes.
- `HandleInertiaRequests::resolveTenantFromHost()` finds no subdomain and returns null; the
  provider binding wins. `EnsureTenantIsActive` / `EnsureUserIsActiveInTenant` run unchanged
  against the local rows (FR-7 falls out of pull-applied `tenant_user` changes).
- The sync worker (a console process) binds the same way in the `sync:worker` command
  bootstrap — the `GenerateExportJob::bindTenantContext()` pattern, as PRD-03 FR-2 prescribes.

### 2.4 Local auth (FR-6, FR-7) — consuming the upstream decision

Restating the upstream decision (Design 02 §10.5, **not** reopened): local login uses **cached
bcrypt hashes** from the snapshot; `Hash::check` offline; roles/permissions cached for `can:`
gates; online re-auth is implicit in every pull (user/role/tenant_user changes apply).

What this doc adds (client-side mechanics):

- **Fortify works as-is.** The snapshot projects `users` (with `password` hash,
  `must_change_password`), `roles`, `permissions`, `permission_role`, `tenant_user` into the
  local DB; the standard eloquent user provider + session guard authenticate offline with no
  custom guard. `current_tenant_id` is set to the local tenant id at snapshot-apply so
  `roleInCurrentTenant()` and the shared `user.permissions` prop work unchanged.
- **Offline lockout policy** (PRD-03 §7.2's open half): 5 consecutive failures per user locks
  local login for 5 minutes (counter in a local `login_attempts` table; `App\Local\Auth`).
  Fortify's rate limiter backs this on the session guard. No device PIN in MVP (deferred to
  PRD-04 per upstream).
- **Deactivation (FR-7):** pull applies `tenant_user.is_active = false` →
  `EnsureUserIsActiveInTenant` blocks on the next request; active sessions are also invalidated
  by the pull applier (logout-if-affected) so the lockout is immediate, not next-navigation.
- **`must_change_password` users cannot complete the flow offline** (a local password change
  would fork the hash and be clobbered by the next pull). Local login blocks them with
  "Sign in on the web first to set your password" (localized). Password/profile/2FA editing is
  runtime-gated off entirely (S4/S5); Fortify's reset-by-email is unreachable
  (`MAIL_MAILER=log`, routes hidden).

### 2.5 Disabled subsystems and online-only modules (FR-1, FR-3)

| Subsystem | Mechanism | Residual behavior |
|---|---|---|
| Horizon | providers seam (S2) + not required at compose time for local | absent from binary |
| Telescope | S2 + `TELESCOPE_ENABLED=false` | absent |
| Reverb / Echo | `BROADCAST_CONNECTION=null` | `ShouldBroadcast` events no-op; Echo never connects (frontend guards on `runtime`) |
| Mail / SMS | `MAIL_MAILER=log`; Twilio channel never triggered by offline-scope actions | log file only |
| Queues | `sync` | inline; no jobs in offline scope anyway |
| Exports/Imports/Backups | S4 route gating | hidden |
| Scheduler / cron | not run at all | pruning runs inside the sync worker's daily tick (§7.1) |

Modules **available offline**: POS (session, checkout, history), Expenses (create/list),
Customers (browse + quick-create), Products (read-only browse with stock), Dashboard (reduced),
the Sync screen, local login. Everything else — purchases, suppliers-write, sales (non-POS),
quotes, invoices-write, payments, cheques, treasury, stock transfers, reports, team/roles,
exports, settings-write, recurring expenses — is hidden by S4 (routes) + S5 (nav). Hidden, not
broken: the routes exist and 404, the nav items never render.

---

## 3. NativePHP shell (FR-1, FR-4, FR-18)

### 3.1 Window & process model

- **One main window** opened by `NativeAppServiceProvider::boot()` (`Window::open()`, min
  1024×768, the POS is tablet-first already). The Electron runtime serves the Laravel app on a
  local port; each HTTP request boots the framework, exactly like FPM — no long-lived container
  state to worry about for tenant binding.
- **Receipts:** `window.open(route('invoices.receipt', id))` creates a child Electron window
  natively; `window.print()` opens the OS print dialog. No code change (C15).
- **Processes:** main window (UI requests) + **one** persistent sync worker child process
  (§3.3). Both open the same SQLite file — hence WAL (§3.2).

### 3.2 SQLite location, WAL, crash safety (FR-18)

- The database lives in the NativePHP **appdata** directory (`Application::storagePath()`),
  which persists across auto-updates and OS reinstalls of the binary. Expense receipt files and
  the encrypted detach-export live under the same appdata root (`storage/app` is remapped
  there), listed in `cleanup_exclude_files` so builds never ship a developer DB.
- Connection pragmas: `journal_mode=WAL` (two processes, readers never block the writer),
  `synchronous=FULL` (a committed sale survives power loss — POS durability beats the small
  write-latency cost at register volume), `busy_timeout=5000`, `foreign_key_constraints=true`.
- **Crash-safety argument for the exit test:** a sale is one SQLite transaction containing
  invoice + lines + stock + movements + payment + treasury movement + **outbox row** (§6.2).
  WAL+FULL makes that atomic and durable. Kill mid-checkout → either everything (incl. the
  outbox row) or nothing. Kill mid-push → the outbox row is `in_flight`; on restart it reverts
  to `pending` and re-pushes with the **same** idempotency key (minted at capture, stored in
  the row) → server answers `already_applied` (Design 01 §6.2). No loss, no double-send.
- Migrations: NativePHP auto-migrates the appdata DB when the app version changes; the migrate
  set = cloud migrations (SQLite-proven in CI) + overlay migrations registered via
  `loadMigrationsFrom(database/migrations/local)` in `LocalRuntimeServiceProvider`.

### 3.3 Sync worker mechanics (resolves PRD-03 §7.1)

**Decision: one persistent child process, not scheduler ticks, not Laravel queue workers.**

```php
// NativeAppServiceProvider::boot()
ChildProcess::artisan('sync:worker', alias: 'sync-worker', persistent: true);
```

- `sync:worker` (overlay, `App\Local\Sync\SyncWorkerCommand`) is a supervised loop: connectivity
  probe → push pending batches → pull → reconcile → write `sync_state` → sleep. `persistent:
  true` restarts it if it crashes (supervisor semantics). Cadence: push tick every 5 s while
  pending > 0, pull every 60 s, immediate pull after a successful push (§9.3).
- **Why not the Laravel scheduler:** there is no cron on a desktop; emulating one adds a
  process anyway, with worse control over backoff and no in-loop state.
- **Why not Laravel queues + `QueueWorker`:** the outbox is a *domain* table with strict
  ordering, custom retry semantics (parked vs retriable), and same-transaction capture.
  Wrapping it in queue jobs would duplicate state (jobs table + outbox), fight ordering
  (queue concurrency), and gain nothing — the worker is a loop over a table, not a job bus.
- **App closed ⇒ no sync.** Accepted and decided: a register's laptop is open during trading
  hours; sync-on-launch covers the morning. No OS daemon/service in MVP (rejected: a second
  installer surface, platform-specific service management, and a background process holding
  the DB while the app updates). PRD-04's pilot SLO ("sale in cloud < 1 min after connectivity
  returns") is measured while the app runs.
- **UI ↔ worker:** through the DB only. The worker writes `sync_state` + outbox statuses; the
  chrome polls `GET /local/sync/status` (routes/local.php, ~10 s interval + after each local
  mutation). No local websockets (Reverb is off; polling a localhost endpoint is free).

### 3.4 Auto-update & upgrade-required (FR-4)

- **Channel:** NativePHP updater with the **S3-compatible bucket** provider (public-read
  release artifacts, `latest` channel). Rejected: GitHub-releases provider on a private repo —
  it requires a `GITHUB_TOKEN` in every client while `cleanup_env_keys` strips `GITHUB_*` at
  build; a public bucket with signed artifacts is the path of least surprise.
- Updates download in the background and apply on restart; NativePHP then auto-migrates. The
  sync worker is killed with the app on restart — safe by §3.2 durability.
- **`426 upgrade_required`** (Design 02 §8.4): the worker sets `sync_state.status =
  'upgrade_required'` and stops calling the API. The chrome shows a persistent, non-blocking
  banner: "Update required — sales continue offline; sync resumes after updating" (localized).
  **POS and expenses keep working** — nothing in the local write path touches the network.
  After the update+restart, the worker resumes with the same cursor and outbox. FR-4 satisfied.

---

## 4. Provisioning wizard (FR-5..7)

### 4.1 State machine (resumable by construction)

State lives in the single-row `sync_state` table (§6.1); every step is idempotent and the
wizard resumes at `sync_state.stage` after any interruption:

```
fresh ──(pairing code valid)──▶ paired ──(202)──▶ snapshot_requested
      ──(poll ready)──▶ downloading ──(archive verified)──▶ applying ──▶ ready
```

| Stage | What happens | Resume behavior |
|---|---|---|
| `fresh` | Welcome → language (en/ar, sets RTL) → server URL + **pairing code** entry. `POST /provision` (Design 02 §1.3). | re-enter code (410/422 handled with localized messages) |
| `paired` | Persist token (encrypted, §4.3) + device/register/storage/tenant/drawer identities. **Create the local `tenants` row** from the response — this is the moment `TenantScope` starts binding. Show register confirmation card ("You are register **R2 — Counter 2** at **Main Store**"). The register was chosen cloud-side at enrollment (Design 02 P3); the wizard confirms, it does not pick — restating FR-5 against the upstream flow. | skip to snapshot |
| `snapshot_requested` | `POST /snapshot` → store `snapshot_id`; poll `GET /snapshot/{id}` with backoff. | re-poll same id; request a new one if the server GC'd it |
| `downloading` | Ranged download to appdata tmp (HTTP `Range` resume, Design 02 §2.3); verify manifest counts/sizes. | resume byte offset |
| `applying` | Per-entity JSONL apply in manifest order through **the same upsert path as pull-apply** (§6.4) — one code path for bootstrap and increments, as Design 02 P4 intends. Per-file progress persisted (`applied_entities` json) so a crash resumes at the next entity; per-entity apply is transactional. Sets `users.current_tenant_id`, seeds the register's `register_serials` rows lazily (first sale inserts, per Design 01 §3.3). Finally `cursor = manifest.cursor`. | resume at first unapplied entity |
| `ready` | Delete the archive, start the sync worker loop, route to local login. | normal boot |

FR-6/FR-7 (local login honoring roles, deactivation on pull) are covered in §2.4.

### 4.2 What the wizard writes

- `tenants` (1 row), then snapshot entities per the Design 02 §2.2 manifest, all keyed by
  `public_id`, FK `public_id`s resolved to local int ids by the local resolver (§6.4).
- `sync_state`: token, device/register/storage/drawer public_ids, cursor, stage.
- Nothing else — no outbox rows, no serial counters (lazy).

### 4.3 Token & secrets at rest (honest scope)

The Sanctum device token and cached hashes live in the local SQLite/appdata. NativePHP does not
provide OS-keychain access or DB encryption; **we do not claim encryption at rest** (this
corrects Design 02's "encrypted local SQLite" — §10, dispute D2). Mitigations that are real:
the token is stored encrypted with a per-install `APP_KEY` generated on first run (so a copied
`sync_state` row alone is useless without the install's key file), hashes are bcrypt (slow by
design), revocation kills the token server-side instantly, and deployment guidance mandates OS
full-disk encryption on register machines. SQLCipher is a Phase-4 option if the pilot's threat
model demands it.

---

## 5. Offline POS + expenses (FR-8..13)

### 5.1 What runs unchanged

`OpenPosSessionAction`, `ClosePosSessionAction`, `PosPreflightAction`,
`FindReplenishmentSourceAction`, `StoreExpenseAction`, `RecordPaymentAction`,
`RecordTreasuryMovementAction`, `DeliverTransactionAction`, the POS controllers, the Vue POS
page, and the receipt page — all pure DB + Inertia, all SQLite-proven. `lockForUpdate` is a
no-op on SQLite, but SQLite serializes writers globally, so every locking argument in the
Actions still holds (single-writer per register by construction anyway, Design 01 §3.4).

### 5.2 Checkout locally (FR-8, FR-9) — the one shared extraction

Three call sites need slightly different checkout behavior; today's action hardcodes the web
one. **Decision: a `CheckoutContext` value object parameter on `ProcessPosCheckoutAction`**
(cloud PR, coordinated with Design 02's PR-5 — `ReplayPosSaleAction` becomes a thin wrapper):

```php
final readonly class CheckoutContext
{
    public function __construct(
        public Register $register,               // serial series + drawer owner
        public StockPolicy $stockPolicy,         // Strict | AllowNegative
        public bool $executeReplenishment,       // web-only auto-transfer behavior
        public ?PresetIdentity $preset = null,   // replay: pre-minted serial + public_ids
    ) {}
}
```

| Caller | register | stockPolicy | replenishment | preset |
|---|---|---|---|---|
| Cloud web POS | `R0` | Strict (throws `InsufficientStockException`) | yes (unchanged behavior) | — |
| `ReplayPosSaleAction` (push) | device's | AllowNegative (oversell path, Design 02 §6.1) | no | serial + ids from mutation |
| **Local runtime** | device's (from `sync_state`) | AllowNegative | **no** | — (mints locally) |

Local specifics:

- **Serial (FR-9):** minted inside the local checkout transaction by the PRD-01
  `SerialNumberGenerator` against the **local** `register_serials` row for the device's
  register — the device is the single writer of its series (Design 01 §3.4), so the printed
  number is final and collision-free. Pushed verbatim in `sale.create` (Design 02 §5.4).
- **No local `StockTransfer`s (from §0.3):** transfers are not a pushable mutation type, and
  Design 02 replay ignores them — so the local path must not create them, or local and cloud
  ledgers diverge. `AllowNegative` + no-replenishment replaces the web's transfer path: the
  cashier sells, local stock may go negative against stale data, the push records oversell
  server-side (Design 02 §6.1). The replenishment *hint* stays as advisory UI (§5.4).
- **Drawer (FR-8):** the local runtime resolves the drawer by `treasury_accounts.register_id`
  (Design 01 §2.3). A small `DrawerResolver` lands with the PRD-02 cloud work (register-based
  in sync/local contexts, `sale_point`-based for cloud web) and `Open/ClosePosSessionAction`
  accept it — the local session open/close then works against the register's drawer unchanged,
  including opening float and the close-time reconciliation adjustment.
- **Walk-in sales:** local `firstOrCreate('Walk-in Customer')` matches the pulled system row
  (customers are tenant-wide in the snapshot), and the outbox mutation sends `customer: null`
  so the server applies its own walk-in resolution (Design 02 §5.4). No duplicates either side.
- **Preflight (FR-9):** runs unchanged against local `stocks` (tenant-wide, pulled). Its
  cross-storage replenishment answer is a *hint* labeled with staleness (§5.4); the local
  "Transfer & Complete" modal is replaced by a "Stock may be unavailable — data as of
  {last pull}" confirm (§8.2).

### 5.3 Expenses locally (FR-13)

- `StoreExpenseAction` runs unchanged inside the outbox decorator (§6.2). Local form deltas
  (runtime-gated, §8): `treasury_account_id` is **fixed to the register's drawer** (the only
  treasury account pulled anyway, Design 02 §4) and required (an offline expense with no
  drawer movement would be invisible to reconciliation); `is_recurring` hidden.
- **Categories: existing only.** `categories` are pull-only/server-wins; a free-text
  `category_objects` create offline would mint a local row the server re-creates under a
  different `public_id` on push, leaving a permanent local orphan next to the pulled twin.
  The offline form offers pulled categories only; the `expense.create` payload carries
  category `public_id`s. (Category management stays a cloud task; acceptable for MVP.)
- **`expense.create` payload** (completing Design 02, which left it unspecified):

  ```json
  {
    "type": "expense.create",
    "public_id": "01jEXPENSE...",
    "idempotency_key": "01j...",
    "actor": "01jUSER...",
    "occurred_at": "2026-07-03T14:02:11Z",
    "payload": {
      "title": "Ice for the fridge",
      "amount": 35000,
      "expensed_at": "2026-07-03",
      "notes": null,
      "categories": ["01jCAT..."],
      "treasury_account": "01jDRAWER...",
      "receipt_public_id": "01jRCPT..."
    }
  }
  ```

  `amount` is integer minor units on the wire (the local action produced the same minor-unit
  rows via `MoneyCast`/`Money::fromMajor`). The push handler runs `StoreExpenseAction` with
  resolved ids; status lands `'pending'` by DB default → the expense **enters the existing
  approval queue with zero approval-specific sync code** (§0.5). Approval outcomes flow back
  as ordinary `expenses` pull updates (register-scoped, Design 02 §4).
- **Attachments (per upstream P14):** the local controller stores the receipt on the local
  `local` disk (`receipts/…`, same `HandlesAsyncUploads` flow, appdata-backed). The decorator
  records `receipt_public_id` + local path on the outbox row. The worker pushes
  `expense.create` first (small, fast), then uploads the file via `POST /attachments`
  (multipart, `sync:attach`, ≤ 5 MB `jpg|png|pdf`) with independent retry; the server links by
  `receipt_public_id`. The local file is kept until upload is confirmed, then subject to
  retention (§7.1).

### 5.4 Stale data in the POS (FR-10, FR-12)

- **Two stock numbers per product** (C10): `stocks.quantity` (live local — decremented by local
  sales, possibly negative) and the local-only `stocks.synced_quantity` + `sync_state
  .last_pull_at`. The product card shows the live number; when unsynced sales exist for the
  product, a secondary line shows "{synced} synced − {n} unsynced" (§8.2). A staleness chip in
  the POS header shows time since the last successful pull and turns amber past 15 minutes
  online / any duration offline — because sibling registers deplete the same stock invisibly
  (FR-12's stated reason).
- **Replenishment hints degrade, not break (FR-9):** the hint ("12 at Main Warehouse") renders
  from pulled warehouse stock with the as-of label. It never triggers a transfer locally.
- **Credit sales (FR-10):** limit check = cached `credit_limit` vs (snapshot/pull balance +
  local unsynced credit sales for that customer, summed from the outbox). The checkout modal's
  credit option shows "Credit check based on data as of {last pull}" (localized). A breach
  shows a confirm dialog — allowed, per settled decision #4; the server flags it on push
  (`credit_breach_flags`, Design 02 §6.2) for PRD-04 follow-up.
- **Receipt (FR-11):** the local invoice row has the final serial before the receipt opens —
  same flow as cloud, `window.open` → print (C15).

---

## 6. Outbox + sync engine (FR-14..18)

### 6.1 Local tables (overlay migrations, `database/migrations/local/`)

```php
Schema::create('outbox', function (Blueprint $table) {
    $table->id();                                    // strict push order
    $table->char('idempotency_key', 26)->unique();   // ULID, minted at capture (sync-lib IdempotencyKey)
    $table->string('mutation_type', 40);             // sync-lib MutationType values
    $table->char('entity_public_id', 26);
    $table->char('actor_public_id', 26);
    $table->timestamp('occurred_at');
    $table->json('payload');                         // full Mutation payload (public_id-keyed, minor units)
    $table->string('status', 12)->default('pending'); // pending|in_flight|applied|parked
    $table->unsignedInteger('attempts')->default(0);
    $table->timestamp('last_attempt_at')->nullable();
    $table->string('rejection_reason', 40)->nullable(); // sync-lib RejectionReason value
    $table->text('last_error')->nullable();          // human message from MutationResult
    $table->json('result')->nullable();              // stored MutationResult (serial echo, flags)
    $table->string('attachment_path')->nullable();   // expense receipt local path
    $table->string('attachment_status', 12)->nullable(); // pending|uploaded
    $table->timestamps();
    $table->index(['status', 'id']);
});

Schema::create('sync_state', function (Blueprint $table) {   // exactly one row
    $table->id();
    $table->string('stage', 24)->default('fresh');           // wizard §4.1
    $table->text('token_encrypted')->nullable();
    $table->char('device_public_id', 26)->nullable();
    $table->char('register_public_id', 26)->nullable();
    $table->char('storage_public_id', 26)->nullable();
    $table->char('drawer_public_id', 26)->nullable();
    $table->unsignedBigInteger('cursor')->default(0);
    $table->string('status', 20)->default('offline');        // ok|offline|upgrade_required|revoked
    $table->timestamp('last_push_at')->nullable();
    $table->timestamp('last_pull_at')->nullable();
    $table->text('last_error')->nullable();
    $table->json('snapshot_progress')->nullable();           // §4.1 resume data
    $table->timestamps();
});

// plus: login_attempts (§2.4) and `stocks.synced_quantity` (local-only column, C10)
```

No `parked_mutations` table: parked **is** an outbox status — one queue, one truth, the errors
screen is a filtered view. (Rejected: a separate table — it duplicates rows and invites drift
between "the queue" and "the errors".)

### 6.2 Same-transaction capture (FR-14) — container decorators

**Decision:** the five mutations are captured by **container decorators** bound only in
`LocalRuntimeServiceProvider` — cloud controllers, routes, and Actions stay untouched:

```php
$this->app->extend(ProcessPosCheckoutAction::class,
    fn ($action) => new CheckoutOutboxDecorator($action, $this->outboxRecorder()));
// likewise: OpenPosSessionAction, ClosePosSessionAction, StoreExpenseAction,
// and the QuickAddPartyModal's customer-create path.
```

Each decorator opens `DB::transaction`, invokes the inner Action (its internal transaction
becomes a savepoint), builds the sync-lib `Mutation` payload **from the models the Action just
created** (guaranteeing the payload mirrors exactly what the local DB committed — serial,
public_ids, minor-unit amounts), mints the idempotency key
(`IdempotencyKey::forMutation()`), and inserts the outbox row. One commit ⇒ business rows and
outbox row are atomic; WAL+FULL makes them durable (§3.2). Five decorator classes in
`App\Local\Outbox\` — small, greppable, testable in isolation.

Rejected alternatives: model observers (can't see a whole mutation — a sale is seven tables;
Design 01 rejected observers for the change log for the same shape of reason); capturing in
controllers (needs cloud-file edits, violates C1); HTTP middleware (no access to the domain
result, can't be same-transaction).

The frontend `idempotency_key` in `Session.vue` (double-submit guard for the *local HTTP*
replay via `invoices.idempotency_key`) becomes `crypto.randomUUID()` (seam S6) — it is distinct
from the outbox key, which is minted at capture and owns cloud exactly-once.

### 6.3 The worker loop (FR-15) — push

Per tick: select `status = 'pending' ORDER BY id LIMIT 200`, mark `in_flight`, build a
`PushEnvelope` (sync-lib), `POST /push`, then reconcile per `MutationResult`:

| outcome | action |
|---|---|
| `applied` | status `applied`, store result; **assert the echoed serial equals the local serial** for sales (a mismatch is a protocol bug — park + alarm, never silently accept); surface `flags.oversell` / `credit_breach` on the sync screen as informational |
| `already_applied` | same as applied (restart/duplicate push — the designed-for case) |
| `rejected` + retriable (`unknown_reference`) | back to `pending`, exponential backoff on `attempts` (30 s · 2ⁿ, cap 1 h) |
| `rejected` + terminal (`validation_failed`, `tenant_mismatch`, `session_closed`) | **`parked`** with localized reason (FR-15c) |

- **Parked never blocks the queue** — later mutations keep pushing. Honest consequence in an
  ordered domain: if `pos_session.open` parks, its sales reject `unknown_reference` and retry
  on backoff until the parked parent is resolved (PRD-04's reconciliation). The errors screen
  groups dependents under the parked root so the cashier sees one problem, not thirty (§8.3).
- Transport failures (timeout, DNS, 5xx) are not rejections: batch reverts to `pending`,
  `sync_state.status = 'offline'`, normal backoff. `429` honors `Retry-After`. `426` → §3.4.
  `401` persistent → revocation path (§7.3).
- On worker start, `in_flight` rows revert to `pending` (crash recovery; idempotent by key).
- Each push carries `pending_count` / `oldest_pending_at`; a `POST /heartbeat` goes out on a
  5-minute tick when idle (Design 02 §8.2).

### 6.4 Pull + apply (FR-15b, FR-16)

`GET /pull?cursor=&limit=` loop while `has_more`; per page, one local transaction:

1. Resolve payload FK `public_id`s → local int ids (`LocalPublicIdResolver`, the client mirror
   of Design 02 §7's boundary rule; unknown parent within a page ⇒ apply page in table
   dependency order, then re-pull — pages are seq-ordered so parents precede children from the
   same cut).
2. Upsert by `public_id` (the **same** routine the snapshot applier uses — one apply path, per
   Design 02 P4). Money fields are already integer minor units; write raw column values, no
   cast round-trip.
3. Tombstones: delete by `public_id`, mirroring the two FK cascades locally
   (`stock_transfers→lines`, `quotes→items` — not pulled in MVP, but the helper ships so
   Phase 4 doesn't rediscover Design 01 §5's caveat).
4. Cache invalidation: `preferences` ⇒ `Cache::forget('preferences')`; user/role/tenant_user
   changes ⇒ permission prop refresh + logout-if-deactivated (§2.4).
5. Commit, then advance `sync_state.cursor` in the same transaction (cursor and data can never
   disagree).

**Why pulls can't clobber unsynced work (FR-16), append-only MVP reasoning:** every locally
created row (invoice, lines, payment, session, expense, customer, movements) has a
device-minted `public_id` the cloud has never seen until *we* push it — a pull can only target
it *after* our own push created it server-side, at which point the server row was produced from
our own mutation via the idempotent replay and is identical. There is exactly **one**
both-sides-written table: `stocks`. For it, upsert-by-payload would clobber local decrements,
so the apply rule is:

```
local stocks.quantity        = payload.quantity − Σ unsynced local deductions(storage, product)
local stocks.synced_quantity = payload.quantity
```

where the pending deduction sum is read from `pending|in_flight` sale outbox payloads. Once a
sale is pushed (`applied`), the server's own change log emits the post-deduction stock state,
and the correction term for it is zero — the formula converges to server truth with no special
cases. This is the whole FR-16 mechanism; no field-level merge machinery is needed until
offline *edits* exist (post-MVP, PRD-02 FR-17 forbids them today).

### 6.5 What lives where (respecting the Design 01 §8 boundary)

| Piece | Home |
|---|---|
| DTOs, enums (`MutationType`, `RejectionReason`, …), envelopes, `IdempotencyKey`/`Ulid`, request builders + response parsers (transport-agnostic client core), protocol constants | `namain/sync-protocol` (sync-lib repo) |
| Outbox tables + decorators | offline repo, `overlay/app/Local/Outbox`, `overlay/database/migrations/local` |
| Worker command, push runner, pull/snapshot applier, `LocalPublicIdResolver`, connectivity probe, HTTP transport (Laravel `Http` client injected into the sync-lib client core) | offline repo, `overlay/app/Local/Sync` |
| Wizard, local auth glue, lifecycle (prune/detach/wipe) | offline repo, `overlay/app/Local/{Provisioning,Auth,Lifecycle}` |
| Sync chrome + screens | offline repo, `overlay/resources/js/{Pages,Components}/Local` |
| `RejectionReason` → localized message keys | keys defined in sync-lib enum docblocks; translations in the app catalogs (en keys + `lang/ar.json`), because the package is framework-free and must not own `__()` |

One addition the sync-lib needs from us (input to its repo): `MutationResult` must expose
`isRetriable()` (derived from `RejectionReason`) so the client and any future consumers share
one retriability truth.

---

## 7. Data lifecycle (FR-19..20)

### 7.1 Local retention & pruning (FR-19)

A daily tick inside the sync worker (no cron, §2.5):

- **Prune** (defaults, overridable in local settings): `applied` outbox rows > 30 days;
  synced transactional history — invoices/transactions/receipts, payments, closed
  pos_sessions, treasury_movements, stock_movements, approved/rejected expenses — older than
  **90 days**; uploaded receipt files > 30 days after upload confirmation.
- **Never pruned:** catalog/reference/users (working set), `stocks`, open sessions, anything
  `pending|in_flight|parked`, any transactional row newer than the retention window, and any
  row referenced by an unpruned row (FK-safe deletion order; prune is one transaction per
  batch). "Synced" is provable locally: the row's creating outbox row is `applied`, or the row
  arrived via pull.
- Pruning is a **local delete only** — it must not, and cannot, emit anything: the cloud is the
  archive; the device is a cache (PRD-19's exact framing). Monthly `VACUUM` after a prune pass
  keeps the file small.

### 7.2 Detach device (FR-20)

Settings → "Detach this device" (permission-gated to admins, localized):

1. Hard requirement: `pending + in_flight = 0`. If `parked > 0`: show the parked list, require
   typed confirmation ("DETACH"), and write the **encrypted unsynced export** (outbox rows +
   payloads, encrypted with a key printed once for support) before proceeding.
2. Best-effort `POST /detach` (revokes own token; device row → PRD-04 lifecycle).
3. Delete the SQLite file, WAL/SHM siblings, receipt files, token file; reset to `fresh`;
   relaunch into the wizard.

### 7.3 Remote revocation (FR-20b, with PRD-04 FR-7)

*(Amended in review to Design 04 §4.2's contract.)* Revocation = token deletion **plus** a
first-class **`403 device_revoked`** response on every sync call (Design 02 §1.1 as amended) —
the client acts on that status immediately, no heuristic needed. The persistent-401 window (3
consecutive attempts spanning ≥ 10 minutes) is retained only as a conservative fallback for
ambiguous auth failure, and it surfaces a warning rather than wiping. On `device_revoked` the
worker sets `sync_state.status = 'revoked'`, and the UI locks into a full-screen "This
device has been revoked" state — **selling stops** (a revoked device must not keep minting
serials against a register the tenant may re-assign). On next launch (or from that screen), the
wipe flow runs: unsynced outbox exported to the encrypted support file (unsynced data is also
flagged cloud-side per PRD-04 FR-7), then the §7.2 wipe. Restating upstream: the wipe is
client-enforced on launch; the cloud's guarantee is only the dead token.

---

## 8. Frontend deltas (FR-12, FR-17)

All strings via `__()` (en keys + `lang/ar.json`), logical properties for RTL, and the
`CLAUDE.md` design system (flat, `emerald` primary, status colors as specified). Everything
below is overlay code or gated by the S5 `runtime` prop via a `useRuntime()` composable —
cloud users never see any of it.

### 8.1 Sync-status chrome (FR-17)

A persistent pill in the `AppLayout` sidebar footer (local runtime only), polling
`/local/sync/status` (~10 s):

| State | Treatment (design-system tokens) |
|---|---|
| Synced | `bg-emerald-50 text-emerald-700 border-emerald-200` + dark variants, check icon, "Synced · {relative last push}" |
| Pending n | amber status colors, count badge, "{n} pending" |
| Offline | neutral gray, cloud-slash icon, "Offline · {n} pending" |
| Attention | red status colors, "{n} need attention" (parked > 0) |
| Update required | amber, persistent banner variant (§3.4) |

Clicking opens **`Pages/Local/Sync/Index.vue`**: last successful push/pull timestamps, cursor,
pending list (type, entity, age), and the **errors screen** — parked mutations with localized
human-readable reasons (`RejectionReason` → catalog keys), dependents grouped under their
parked root (§6.3), per-item "Retry" (re-queues as `pending`) and detail view. No destructive
"discard" in MVP — discarding a sale a customer walked away with is a PRD-04 reconciliation
decision, not a cashier button.

### 8.2 POS staleness & credit (FR-10, FR-12)

- POS header chip: "Stock as of {relative time}" — gray when fresh, amber status colors when
  stale (> 15 min online, or offline) with tooltip "Other registers may have sold from this
  stock since the last sync".
- Product cards / cart rows: live quantity as today; when the product has unsynced local
  deductions, a caption line "{synced_quantity} {__('synced')} − {n} {__('unsynced')}"
  (`text-xs text-gray-400 dark:text-gray-500`).
- The web "Transfer & Complete Sale" modal is replaced (local runtime) by a confirm using the
  amber warning pattern: "May be out of stock — data as of {time}. Complete anyway?".
- Checkout modal, credit method: caption "{__('Credit check based on data as of')} {time}";
  breach ⇒ amber confirm dialog (allowed, §5.4).

### 8.3 Hidden modules & local-only nav

- Nav groups/items additionally gated `v-if="can(...) && (isCloud || OFFLINE_MODULES.has(item))"`
  via `useRuntime()` — one constant, one composable, greppable. Tenant switcher, profile/2FA,
  impersonation banner, OperationsCenter (exports feed) hidden on local.
- New local nav items: **Sync** (with the pill) and **Device settings** (detach, retention,
  about/version, update check).
- Wizard pages follow the auth-page idiom (slate palette, `AuthenticationCard`-style centered
  card, `dir` from the chosen locale) since they run pre-login.

---

## 9. Answers to PRD-03 §7 open questions

1. **Sync worker mechanics:** one persistent NativePHP child process
   (`ChildProcess::artisan('sync:worker', persistent: true)`), supervised restart, push tick
   5 s / pull 60 s. **Sync runs only while the app runs** — no OS daemon in MVP (§3.3).
2. **Local auth (decided upstream, restated):** cached bcrypt hashes + cached
   roles/permissions from the snapshot; `Hash::check` offline (Design 02 §10.5/P13). This doc
   adds the offline lockout policy: 5 failures → 5-minute lock, local counter (§2.4), and the
   `must_change_password` online-first rule.
3. **Pull interval / signal:** poll every 60 s while online + an immediate pull after every
   successful push (our own writes echo back fastest, and sibling-register activity usually
   clusters with ours). No "changes available" push channel in MVP — Reverb is disabled locally
   and a long-poll endpoint is new server surface for marginal freshness; revisit in Phase 3
   with the heartbeat infrastructure (§6.3).
4. **Thermal printing:** keep the browser print dialog (Electron) in MVP — the receipt page
   already prints correctly and NativePHP gives us `window.print()` for free. Native/silent
   ESC-POS printing deferred to Phase 4 behind a device-settings toggle (§3.1, C15).
5. **Cross-storage stock visibility:** per Design 02 P7 (restated, not reopened): `stocks` is
   pulled tenant-wide read-only, so the offline POS shows the full replenishment-hint UX from
   cached data, labeled as-of last pull; the device writes only its own storage's stock, and no
   transfers can be initiated offline (§5.2, §5.4).

---

## 10. Disputes with upstream design

Recorded per the working agreement — none blocks the upstream docs; all are amendments or
corrections to be acknowledged by their owners:

- **D1 — `ReplayPosSaleAction` shape (Design 02 P9/§5.4).** Amendment, not disagreement: rather
  than a replay action that "wraps" checkout while separately re-implementing force-deduct and
  preset ids, this doc asks for the `CheckoutContext` parameter on `ProcessPosCheckoutAction`
  (§5.2) so web, replay, and the local runtime are three contexts of **one** action.
  `ReplayPosSaleAction` remains as the mutation-handler wrapper. Without this, the local client
  would need a fourth checkout variant and the "same Action runs locally" promise (FR-9) breaks.
- **D2 — "encrypted local SQLite" (Design 02 §10.5).** Correction: NativePHP provides no DB
  encryption or keychain; SQLCipher is not available in the MVP toolchain. The real mitigations
  are §4.3 (per-install `APP_KEY`-encrypted token, bcrypt hashes, instant server-side
  revocation, full-disk-encryption deployment guidance). Design 02's security note should be
  amended so the review record doesn't over-claim.
- **D3 — expense mutation payload.** Design 02 P8/§5.3 names `expense.create` but never
  specifies its payload; §5.3 of this doc defines it (categories by `public_id` only,
  mandatory drawer, `receipt_public_id`). The push-handler PR (Design 02 PR-4) should adopt
  this shape. Related codebase fact upstream should note: approval is manual-only and the
  treasury outflow is recorded at creation regardless of approval status (§0.5) — "expenses
  sync as pending approval" is satisfied by the DB default, not by workflow code.
- **D4 — drawer lookup switch.** Design 01 §2.3 defers the `sale_point_id → register_id` drawer
  lookup to "the sync/offline path" in PRD-02. This doc makes the dependency concrete: the
  **local runtime** needs it too (session open/close + checkout + expense drawer, §5.2/§5.3),
  so the `DrawerResolver` must land in the shared Actions (cloud PR), not inside the push
  handler where the local client couldn't reach it.
- **D5 — sync-lib addition.** `MutationResult::isRetriable()` derived from `RejectionReason`
  (§6.5), so retriability is defined once in the wire contract instead of per consumer.

---

## 11. Inputs for other docs

**Design 04 (reconciliation, device management & pilot):**

- The client's parked-mutation model (§6.3, §8.1): parked = terminal rejection held locally
  with dependents grouped; PRD-04's cloud conflict UI is the *resolution* side — define
  cloud-side actions that unpark (e.g. reopening a session, fixing a reference) and how the
  device learns to retry (it already retries dependents on backoff; a parked *root* needs an
  explicit local "Retry" after cloud-side resolution).
- Device-health inputs the client reports: `pending_count` + `oldest_pending_at` on every push
  and a 5-minute idle heartbeat (§6.3) — the dashboard's "pending age" source.
- Revocation contract implemented client-side (§7.3): persistent-401 detection window (3
  attempts / ≥ 10 min), selling stops at `revoked`, wipe-on-launch, **encrypted unsynced
  export file** — PRD-04 owns the support workflow that consumes that file and the cloud-side
  flagging of data unsynced at revocation (PRD-04 FR-7).
- Detach leaves the cloud `devices` row for PRD-04's lifecycle states; `POST /detach` endpoint
  needs a home in the sync API (natural fit: Design 02 PR-6 operational endpoints).
- Backpressure (PRD-04 FR-12): the client's recovery from a pruned change log should be
  **re-snapshot** through the §4.1 wizard stages (`snapshot_requested → … → ready`) with the
  outbox preserved — the machinery already exists; PRD-04 defines the server-side trigger
  (e.g. pull returns a `cursor_too_old` error).
- Exit-test choreography (PRD-03 §6 acceptance): the two-device full-day scenario plus the
  kill-mid-checkout / kill-mid-push chaos tests are implemented in the offline repo's composed
  CI (§12); the pilot SLO measurement hooks live in `sync_logs` (Design 02 §8.1).

**Sync-lib repo:** `MutationResult::isRetriable()` (D5); `RejectionReason` message-key naming
convention (`sync.rejection.{reason}`) so app catalogs can translate without the package
owning localization (§6.5).

---

## 12. Implementation notes (slicing across the three repos)

**Cloud PRs (this repo)** — all behavior-neutral for web users:

1. **PR-C1 runtime seams.** S1–S5 (§2.2): `config/runtime.php` + `Runtime`, providers hook +
   `bootstrap/local-providers.php` loading, route branch, `runtime.online` middleware applied
   to online-only groups, `runtime` shared prop + `useRuntime()`. Includes the
   `RUNTIME_PROFILE=local` boot smoke test in cloud CI.
2. **PR-C2 driver + key fixes (S6).** `ilike` driver branch in `PosSessionController`;
   `Session.vue` idempotency key → `crypto.randomUUID()`. Standalone, benefits cloud today.
3. **PR-C3 `CheckoutContext` + `DrawerResolver`** (§5.2, D1/D4) — coordinated with Design 02
   PR-5 (`ReplayPosSaleAction` builds on it). Web callers pass the strict/R0 context; tests
   prove web behavior unchanged.

**Sync-lib repo:** client core request builders/parsers (already scoped, Design 01 §8) +
`isRetriable()` + message-key convention. No other changes needed by this doc.

**Offline-app repo:**

1. **PR-O1 compose harness.** Repo skeleton, `compose.sh` (collision-fail, `--dev` symlinks,
   mechanical composer step), `CLOUD_REF`, CI that composes and runs the cloud test suite on
   SQLite — proves the harness before any feature code.
2. **PR-O2 shell + runtime profile.** NativePHP packages, `NativeAppServiceProvider` (window +
   worker registration), `config/nativephp.php`, env profile, WAL pragmas,
   `LocalRuntimeServiceProvider` (tenant binding, migration path, decorator bindings stubbed).
   Smoke: app boots, POS page renders on SQLite, TenantScope fails closed pre-provisioning.
3. **PR-O3 provisioning wizard + snapshot apply** (§4): state machine, ranged download, the
   shared upsert path, wizard UI (en/ar/RTL). Test: interrupt at every stage and resume.
4. **PR-O4 local auth** (§2.4): lockout, deactivation-logout on pull-apply,
   must-change-password gate.
5. **PR-O5 outbox** (§6.1–6.2): tables + five decorators + payload builders. Pest: every
   mutation writes exactly one outbox row atomically; kill-mid-checkout chaos test.
6. **PR-O6 sync worker** (§6.3–6.4): push runner (reconcile/park/backoff), pull applier
   (stocks formula, cache invalidation, cursor atomicity), heartbeat, 426/401 handling,
   in-flight recovery. Chaos: kill-mid-push, restart, assert exactly-once against a test
   server stub.
7. **PR-O7 POS/expense deltas + sync chrome** (§5, §8): local checkout context wiring,
   staleness UI, credit as-of, expense form deltas + local attachments, sync screen/errors
   screen, nav gating. RTL screenshot checks for the new surfaces.
8. **PR-O8 lifecycle + updater** (§3.4, §7): prune tick, detach, revocation wipe + export,
   updater bucket wiring, packaging smoke doc (PRD-03 §6). Then the two-device exit test.

O1→O2→O3 are strictly ordered; O4/O5 depend on O3; O6 depends on O5 and cloud PR-C3 + PRD-02's
push/pull endpoints in a staging cloud; O7/O8 close the PRD-03 acceptance list. Money is
integer minor units end-to-end (`MoneyCast` locally, `int` on the wire); every migration and
query introduced here runs on SQLite — enforced by the composed CI being SQLite-only.
