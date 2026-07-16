# U5: Physical `text-right pr-8` in the quote/invoice totals

**Status:** Needs verification before any change · **Size:** S · **Depends on:** —

## 1. Problem — and its caveat

> **⚠️ This was flagged from source only. It was never seen rendered.** The quote and
> invoice routes were not in the audit's scope, so there is **no evidence these actually
> look wrong**. Verify first (§3); if the totals row renders correctly today, close this
> item rather than "fixing" it.

Three components use physical direction properties that don't mirror in RTL:

| File | Line | Class |
|---|---|---|
| `resources/js/Components/Quotes/QuoteForm.vue` | 200 | `col-span-2 text-right pr-8` |
| `resources/js/Components/Invoicing/InvoiceForm.vue` | 226 | `col-span-2 text-right pr-8` |
| `resources/js/Components/ConfirmationModal.vue` | 41 | `mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left` |

In an RTL layout `pr-8` pads the wrong side and `text-right`/`text-left` don't follow
reading direction. These are the money rows on customer-facing documents.

## 2. Why the confidence is low

The codebase is **very** clean on this axis — a live DOM sweep of a fully-rendered
`/products` page found **zero** `pl-*`/`pr-*`/`ml-*`/`mr-*` physical classes, and only two
`text-left`/`text-right` nodes in the entire document. The team uses logical properties
(`ms/me/ps/pe/text-start`) consistently.

That makes these three look like genuine strays — but it equally means they might be
deliberate, or inside a container that's already `dir="ltr"` (a printed document, say,
where LTR is intended). `Invoices/Print.vue`, `Receipt.vue` and the `PrintStatement`
pages exist and may legitimately force LTR.

Note some nearby physical classes **are** correct and must not be "fixed":
`Components/Dropdown.vue:40,44` uses `left-0`/`right-0` driven by an `align` prop, and
`OperationsCenter.vue` uses `isRtl ? 'left-4' : 'right-4'` ternaries. Those are
intentional mirroring, not bugs.

## 3. Task specs

- **T5.1 — Verify (do this first).** Open a quote and an invoice in the Arabic UI and look
  at the totals row. Check the computed `direction` of the containing element — if an
  ancestor is `dir="ltr"`, the physical classes are correct and this item closes.
- **T5.2 — Fix only what T5.1 confirms.**
  - `text-right pr-8` → `text-end pe-8`
  - `sm:ml-4 sm:text-left` → `sm:ms-4 sm:text-start`
- **T5.3 — Sweep, don't spot-fix.** If confirmed, grep the rest:
  `grep -rnE '(^|[^:a-z-])(pl|pr|ml|mr)-[0-9]|text-(left|right)' resources/js --include='*.vue'`
  filtering `rtl:`/`ltr:`-prefixed hits and print-only views.

## 4. Acceptance criteria

- Either: the totals row is confirmed correct and this item is closed with a note, **or**
  the padding/alignment follows reading direction in Arabic, verified visually — not just
  by reading the class.
- No regression to print/receipt views that intentionally force LTR.
