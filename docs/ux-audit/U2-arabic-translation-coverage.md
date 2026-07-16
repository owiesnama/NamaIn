# U2: 175 untranslated `__()` keys + a CI coverage gate

**Status:** Blocked on a native Arabic pass · **Size:** M (mostly not code) · **Depends on:** —

## 1. Problem

**175 `__()` keys across `resources/js` have no entry in `lang/ar.json`**, so they fall
back to their English keys and render English in an Arabic-first UI.

This is **not** a wrapping problem. The strings are correctly wrapped in `__()` — the
discipline is good. The translations are simply missing data, which is why the gap is
invisible to code review and keeps regrowing.

Worst affected is `/preferences` — the owner-facing configuration screen renders English
section headings ("Identity", "Pricing & currency", "Inventory policy") above a
half-translated nav where only "الإشعارات" is Arabic. It looks half-built.

### It also causes a bidi artifact

Each untranslated English sentence's trailing period migrates to the visual **left**:

```
.Your logo and the header printed on invoices
```

The period is bidi-neutral, so in the RTL paragraph it resolves to the paragraph's visual
end. **Translating the string fixes this for free** — Arabic text is strongly RTL, so the
period resolves correctly. No `<bdi>` needed; the root cause is the missing translation.

## 2. Scope

| Area | Keys |
|---|---|
| `Pages` | 135 |
| `Components` | 28 |
| `Shared` | 9 |
| `Layouts` | 3 |
| **Total** | **175** |

Confirmed examples:
- The 10 on `/preferences` (`Show.vue`, `UpdateApplicationInformationForm.vue`) — the
  visible ones, incl. `"Identity"`, `"Pricing & currency"`, `"Inventory policy"`.
- `"Hot Items"` — `resources/js/Components/Pos/PosProductGrid.vue:110`, renders
  "HOT ITEMS" in Latin caps on the POS screen.
- `"Open navigation"` / `"Close navigation"` — `AppLayout.vue:496` and `:106`. These are
  `sr-only`, so **an Arabic screen-reader user hears English**.

Also, one string is not wrapped at all: `PreferenceController@update` returns
`back()->with('success', 'Settings updated successfully')` — a hardcoded English flash
needing `__()`.

## 3. The worklist

`docs/ux-audit/ar-missing.json` — all 175 keys, empty values, ready to fill:

```json
{
    "No results found": "",
    "Edit Sales Invoice": "",
    ...
}
```

Regenerate it at any time by diffing `__()` keys in `resources/js` against `lang/ar.json`.

## 4. Task specs

- **T2.1 — Translate (owner).** Fill in `ar-missing.json`. **Blocked on a native Arabic
  speaker; this is not work an agent should do.** A machine-translated business UI is
  worse than an English one, because it reads fluent and is wrong.
- **T2.2 — Merge.** Fold the filled worklist into `lang/ar.json`, delete
  `ar-missing.json`. Mechanical.
- **T2.3 — Wrap the flash string.** `__()` around `PreferenceController@update`'s
  `'Settings updated successfully'`, key added to `ar.json`.
- **T2.4 — Land the gate.** A test asserting every `__()` key in `resources/js` has an
  `ar.json` entry. **Lands green only after T2.2** — that was the decision: no baseline
  allowlist, no frozen debt.

## 5. Acceptance criteria

- `lang/ar.json` covers every `__()` key in `resources/js`.
- The gate fails when a new `__()` key ships without a translation.
- `/preferences` renders fully Arabic, and the floating-period artifact is gone.

## 6. Notes

The gate is what makes this stick. Without it the count regrows silently — 175 is what
accumulated *with* good `__()` discipline, precisely because nothing checked the data.

The decision to skip a baseline allowlist means **T2.4 cannot land until T2.1 is done**.
If the translation pass stalls, revisit: a baseline that freezes the 175 and blocks *new*
untranslated keys is strictly better than no gate at all.
