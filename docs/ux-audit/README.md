# UX/UI + RTL Audit — Backlog

Work deferred from the audit of the live tenant (2026-07-16). The defects that were
fixed immediately shipped in **PR #77**; everything below is what we deliberately
held back, and why.

> **Read the "why held" column before picking anything up.** Nothing here is held
> because it's low value. U1 is held because testing invalidated its original
> design, U2 because it needs a native Arabic speaker, U3 because it wants
> production confirmation of a change that already shipped, U5 because it was never
> visually confirmed.

## Items

| Item | Title | Status | Size |
|---|---|---|---|
| [U1](U1-configurable-numerals.md) | Configurable numerals (Arabic-Indic ↔ Latin) as a tenant preference | **Blocked** — premise was wrong, see §4/§5. Foundation parked on `wip/u1-numerals-foundation` | L |
| [U2](U2-arabic-translation-coverage.md) | 175 untranslated `__()` keys + CI coverage gate | **Blocked** — needs a native Arabic pass | M — mostly not code |
| [U3](U3-rtl-root-migration.md) | Remove the nested `dir` divs now `<html dir>` ships | **Done** | S |
| [U4](U4-mobile-density.md) | Mobile products table → cards; tap targets; export pill collision | **Done** | M |
| [U5](U5-physical-direction-classes.md) | Physical `text-right pr-8` in quote/invoice totals | **Done** — verified real, then fixed | S |

**No critical-severity work remains open.** The audit's one critical finding
(`/products/create` → 500) turned out to be 19 dead routes and is fixed in PR #77,
with `RouteIntegrityTest` preventing recurrence.

## Audit coverage gaps

The audit itself could not cover these. They are **not** findings — they're unexamined
surface, and a future audit should close them:

- **Light mode was never audited.** Dark mode is OS-driven by design (`tailwind.config.js`
  has no `darkMode` key, so it defaults to `media`), there is no in-app toggle, and
  `prefers-color-scheme` could not be emulated through the tooling. Every finding in the
  audit is dark-mode only. **Light mode is entirely unexamined.**
- **390px was never reached.** Chrome enforces a ~500px minimum window width. Mobile
  findings were measured at 485px — below Tailwind's `sm` (640px), so the mobile branch
  rendered and the findings hold, but behaviour below 485px is unverified.
- **No screenshots were persisted.** The tooling's `save_to_disk` wrote nothing
  reachable, so findings were pinned with live DOM measurements (rects, computed styles,
  HTTP status, page props) quoted inline instead. Those are reproducible; images are not
  available.

## Conventions

Same as the other initiatives in `docs/` — TDD (Pest first), `__()` on every
user-facing string, logical RTL properties only (`ms/me/ps/pe/text-start`), full
dark-mode pairs, `vendor/bin/pint --dirty --format agent` on touched PHP, additive
migrations, no new dependencies without approval.

One extra, learned the hard way in PR #77:

> **Verify against the actual failure, not the mechanism you believe in.** Two fixes in
> PR #77 were wrong on first attempt and only measurement caught it — `inset` resolves
> against the *padding box* (so a 1px border made `-inset-[7px]` land on 42px, not 44),
> and `<Ltr>` turned out to *break* the Arabic-Indic output it was assumed to help (U1).
