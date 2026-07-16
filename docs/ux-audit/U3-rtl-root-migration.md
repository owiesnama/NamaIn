# U3: Remove the nested `dir` divs now that `<html dir>` ships

**Status:** Ready once PR #77 is confirmed in production · **Size:** S · **Depends on:** PR #77 deployed

## 1. Problem

PR #77 set `dir` on `<html>` (`resources/views/app.blade.php:2`), which is what
`CLAUDE.md` always claimed happened:

> *"RTL is driven by the `dir` attribute on the `<html>` element set by the `HandleLocale`
> middleware."*

That was **deliberately the additive half**. The nested `<div :dir="direction">` still
exists and still works, so PR #77 could not regress layout. This item removes the now-redundant
nesting.

## 2. Why it was split

Before PR #77, `document.documentElement` computed `direction: ltr` on every page despite
`lang="ar"`; RTL came entirely from one nested div. Tailwind's `rtl:` variant matches
`[dir="rtl"] &`, so it kept working *inside* that div — which is why the app mostly looked
right, and why this was major rather than critical.

Setting `<html dir>` is purely additive. **Removing the div is not** — it changes which
element establishes direction for everything beneath it. Hence a separate, verifiable step.

## 3. Current state (file:line)

- `resources/views/app.blade.php:2` — now emits `dir` ✅ (PR #77)
- `resources/js/Layouts/AppLayout.vue:58` — `<div :dir="direction">`, the redundant one
- `resources/js/Layouts/AppLayout.vue:23-25` — the `direction` computed that feeds it
- `resources/js/Layouts/AdminLayout.vue` — has its own; **also needs removing**
- `resources/views/emails/layout.blade.php:8` — the pattern `app.blade.php` copied; leave alone
- `tests/Feature/HtmlDirectionTest.php` — asserts the root `dir` for `ar` and `en` (PR #77)

Auth pages set direction on their own root (`:dir="locale === 'ar' ? 'rtl' : 'ltr'"`,
per `.ai/Design rules`) — **check whether those are also redundant now**, but they render
outside `AppLayout`, so verify before touching.

## 4. Task specs

- **T3.1 — Confirm in production.** `document.documentElement.dir === 'rtl'` on a live
  Arabic page. Do this **first** — the rest is predicated on it.
- **T3.2 — Remove the divs.** `AppLayout.vue:58` and the `AdminLayout` equivalent, plus
  the `direction` computed if nothing else consumes it. **Note `AppLayout.vue:93` and
  `:366` read `direction` for layout logic** — those must keep working, so the computed
  may need to stay even after the div goes.
- **T3.3 — Verify what the div was masking.** The reason this matters: things *outside*
  the div inherited LTR. Check body-level teleports/portals, the native scrollbar side,
  text selection direction, and toast/drawer anchoring.

## 5. Acceptance criteria

- No `:dir` on a layout wrapper div; `<html>` is the only direction source.
- `HtmlDirectionTest` still green.
- Visual parity on `/dashboard`, `/products`, `/pos`, `/preferences` in Arabic — RTL
  before and after.

## 6. Risk

Low but not zero, and **wider than the diff looks**: every `rtl:` variant in the app
currently resolves against the nested div. After removal they resolve against `<html>`.
That should be identical — `[dir="rtl"] &` matches either ancestor — but "should be" is
why T3.1 comes first and why this didn't ride along in PR #77.
