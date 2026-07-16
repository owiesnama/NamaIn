# U1: Configurable numerals (Arabic-Indic ↔ Latin) as a tenant preference

**Status:** Blocked — see §5 · **Size:** L (was scoped as ~2 files; it is ~41) · **Foundation:** parked on `wip/u1-numerals-foundation`

## 1. Problem

> **§1 below was wrong and is kept only as the record.** The audit claimed three
> formatters with the dashboard as the outlier. There are **41**, and the dashboard's
> pattern is the **majority**. See §5.

The same tenant's money renders three different ways, from three different formatters:

| Screen | Renders | Source |
|---|---|---|
| `/dashboard` | `‏٣٧٧٬٢٩٧٬٤٨٠٫٠٠ ج.س.` (Arabic-Indic) | `resources/js/Pages/Dashboard.vue:92` — own `Intl`, hardcodes `'ar-SA'` |
| `/pos` | `25 SDG` (Latin, code appended) | `resources/js/Pages/Pos/Session.vue:41` — own `toLocaleString('en-US')` |
| `/products` | `5,000` + `(SDG)` in the column header | `useCurrency().formatAmount` ✅ |

`resources/js/Composables/useCurrency.js:12-13` documents the intended rule in a comment:

> *"Always format with Latin (Western) digits regardless of app locale; Arabic-Indic
> digits are avoided app-wide for numeric/financial data."*

So `/products` is correct and **the dashboard is the outlier** — it produces exactly the
digits the convention forbids. An owner reading the dashboard and then the POS sees the
same currency in two numeral systems.

**The decision taken:** don't pick a winner — support both, configurable per tenant.

## 2. Goals / Non-goals

**Goals**
- A tenant preference selecting the numeral system for money and numeric data.
- One formatter. `Dashboard.vue:92` and `Pos/Session.vue:41` delete their bespoke ones
  and route through `useCurrency()`.
- Correct bidi in **both** modes (see §4 — this is the hard part).

**Non-goals**
- Per-user numerals. Decided: tenant-level, alongside `currency` (an invoice printed by
  two users must not disagree).
- Changing the stored representation. Money stays integer minor units via `MoneyCast`;
  this is presentation only.
- Deriving numerals from `language` with no override — rejected; the setting is explicit
  so an Arabic UI can still use Latin digits.

## 3. Decisions already taken

- **Where:** a tenant preference on `/preferences`, next to العملة. Same plumbing as
  `currency`/`language` (`Preference::asPairs()` → `HandleInertiaRequests` → the global
  `preferences()` helper in `resources/js/Plugins/preferences.js`).
- **Default:** **Arabic-Indic for `ar` tenants.** This preserves today's dashboard and
  changes POS, products, invoices, reports and receipts — i.e. **most money renders in
  the app move**. That blast radius is the reason this is L, not S.

## 4. The blocker: `<Ltr>` cannot stay a universal wrapper

The original plan assumed this was two files plus a settings field. **Testing invalidated
that.** Rendering `Intl` output for `-377297.5` in an RTL container:

| Case | Renders | Verdict |
|---|---|---|
| `en-US`, bare | `SDG 377,297.50-` | ❌ minus at the wrong end |
| `en-US`, inside `.ltr-isolate` | `-SDG 377,297.50` | ✅ |
| `ar-SA`, bare | `؜-‏٣٧٧٬٢٩٧٫٥٠ ج.س.` | ✅ |
| `ar-SA`, inside `.ltr-isolate` | reordered — `ج.س.` punctuation shifts | ❌ |

The reason is in the codepoints. `Intl`'s `ar-SA` output already ships its own bidi
controls:

```
"؜-‏٣٧٧٬٢٩٧٫٥٠ ج.س."
 U+061C U+002D U+200F U+0663 … U+066C … U+066B … U+00A0 U+062C U+002E U+0633 U+002E
 ^ALM          ^RLM
```

`U+061C` (Arabic Letter Mark) and `U+200F` (RLM) are placed by ICU specifically so the
string renders correctly **in an RTL context**. `<Ltr>` applies `dir="ltr"` +
`unicode-bidi: isolate` (`resources/js/Components/Ltr.vue`, `.ltr-isolate` in
`resources/css/app.css:13`), which fights those marks.

**So the rule inverts per mode:**
- Latin money **requires** `<Ltr>`.
- Arabic-Indic money **must not** be wrapped in it.

`.ltr-isolate`'s own comment (*"force Latin lining/tabular figures so digits never fall
back to Arabic-Indic glyphs"*) is also written against a Latin-only world and needs
revisiting — note `font-variant-numeric: lining-nums` does **not** transliterate
Arabic-Indic codepoints, so that claim is questionable independent of this work.

### The decision needed

Pairing "format" with "isolate correctly" cannot be left to ~9 call sites — that is
exactly the class of bug this audit found. Two candidate shapes:

**(a) A `<Money :value>` component** that owns formatting *and* isolation, so no call
site can get the pairing wrong. Cleanest, but it's a refactor across every money render
in POS, products, invoices, reports and receipts.

**(b) `useCurrency()` returns `{ text, isolate }`** and each call site conditionally
wraps. Smaller diff, but keeps the footgun — every new call site can still get it wrong,
and silently.

Recommendation: **(a)**, given the default moves most screens anyway, so the call sites
are being touched regardless.

## 5. Current state (file:line)

- `resources/js/Composables/useCurrency.js:6-31` — `formatCurrency`, `formatAmount`;
  both hardcode `'en-US'` (`:16`, `:29`); the Latin-only convention comment lives at `:12-13`.
- `resources/js/Pages/Dashboard.vue:92` — bespoke `Intl`, `window.lang === 'ar' ? 'ar-SA' : 'en-US'`.
- `resources/js/Pages/Pos/Session.vue:41` — bespoke `toLocaleString('en-US')` + appended code.
- `resources/js/Components/Ltr.vue` — `dir="ltr"` + `.ltr-isolate`; used in only **9** files.
- `resources/css/app.css:13-18` — `.ltr-isolate`.
- `app/Models/Preference.php:14` — `asPairs()`.
- `app/Http/Middleware/HandleInertiaRequests.php:123-131` — `resolvePreferences()`.
- `app/Http/Requests/PreferenceRequest.php` — validation.
- `app/Actions/UpdatePreferences.php` — persistence.
- `resources/js/Pages/Preferences/Partials/UpdateApplicationInformationForm.vue:101` —
  the "Pricing & currency" section the field belongs in.

## 6. Task specs

- **T1.1 — Preference plumbing.** `numerals` key (`arabic` | `latin`), validated in
  `PreferenceRequest`, persisted via `UpdatePreferences`, defaulting to `arabic` when
  `language === 'ar'` and `latin` otherwise. *Test:* the default resolves from language;
  an explicit value overrides it.
- **T1.2 — Settings UI.** A field in "Pricing & currency" beside العملة. Arabic-first,
  RTL, dark mode. **Its labels must exist in `lang/ar.json`** — see U2; the settings page
  is exactly where untranslated keys already surface.
- **T1.3 — Resolve §4, then land the formatter.** `useCurrency()` reads the preference
  and picks the locale. *Test:* both modes, including a **negative** value and the
  isolation rule from §4 — the table in §4 is the test matrix.
- **T1.4 — Converge the outliers.** Delete the bespoke formatters at `Dashboard.vue:92`
  and `Pos/Session.vue:41`.
- **T1.5 — Guard it.** An ESLint rule (or a test) banning `Intl.NumberFormat` /
  `toLocaleString` outside `useCurrency.js`. Without this the three formatters grow back.
- **T1.6 — Revisit `.ltr-isolate`.** Make the glyph-forcing conditional or drop the claim
  from the comment; keep the isolation.

## 7. Acceptance criteria

- One formatter. `grep -rn "Intl.NumberFormat\|toLocaleString" resources/js` returns only
  `useCurrency.js`, and T1.5 keeps it that way.
- Every screen in §1 renders the same numeral system for the same tenant.
- A negative amount renders correctly in **both** modes (`-20`, never `20-`).
- Switching the preference flips every money render with no page-specific exceptions.

## 8. Open questions

- §4(a) vs §4(b).
- Does `numerals` govern **all** numbers (stock qty, counts, dates) or only money?
  The audit only measured money. Dates/times were never examined.
- Does the currency **symbol** follow the numeral system? Today `ar-SA` implies `ج.س.`
  and `en-US` implies `SDG`; the setting conflates them. Splitting numerals from symbol
  style is possible but doubles the matrix.


## 5. Correction — the premise was wrong (found while implementing)

A sweep of `resources/js` while building this:

| Pattern | Files |
|---|---|
| `window.lang === 'ar' ? 'ar-SA' : 'en-US'` (the "dashboard pattern") | **35** |
| hardcoded `en-US` | **6** — all POS — plus `useCurrency.js` |

So the dashboard is **not** the outlier: its pattern is what the app overwhelmingly
does (Reports, Treasury, Suppliers, Customers, Expenses, Payments, Cheques, Storages,
Purchases, Invoices…). `useCurrency.js`'s "Latin app-wide" comment describes itself and
POS, nothing more. §1's framing is inverted.

Consequences, all verified:

1. **The chosen default is nearly a no-op.** Arabic-Indic for `ar` tenants is already
   what those 35 files produce. The screens that actually change are POS and `/products`
   — the opposite of what §1 implied.

2. **The `latin` option cannot ship yet.** Those 35 files render money with **no**
   `<Ltr>`. They are correct today *only* because `ar-SA` output self-isolates
   (`U+061C`/`U+200F`). Pick `latin` and all 35 emit `en-US` into RTL unisolated — the
   `-20` → `20-` bug, app-wide. Every one of their render sites must move to `<Money>`
   first.

3. **Even the default breaks `/products` today.** It wraps `useCurrency.formatAmount`
   in `<Ltr>` (`Index.vue:587-589`), so Arabic-Indic would be forced LTR — precisely
   what `<Money>` exists to prevent.

4. **Converging POS is not numerals-only.** Its `fmt()` renders `25 SDG`;
   `formatCurrency` renders `SDG 25.00`. That is a visual/product change and needs a
   decision, not a refactor.

### What exists already

`wip/u1-numerals-foundation` carries the working mechanism: the `NumeralSystem` enum,
the `numerals` preference + validation, the server-derived default, `useCurrency()`
reading it, and `<Money>` (which owns the format+isolate pairing — `needsLtrIsolation()`
inverts per system). It is deliberately not merged.

### Order of work

1. Decide the POS format question (§5.4).
2. Sweep the 35 files' render sites onto `<Money>`.
3. Then expose the setting.
