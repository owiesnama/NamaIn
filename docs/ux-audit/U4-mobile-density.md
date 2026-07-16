# U4: Mobile — products table, remaining tap targets, export pill collision

**Status:** Done · **Size:** M · **Depends on:** —

Three mobile defects the audit found that PR #77 didn't close. Grouped because they're the
same surface, but T4.1 is design work while T4.2/T4.3 are mechanical — **split the PR if
the design stalls**.

## T4.1 — Products table doesn't survive mobile

**Measured** at 485px on `/products`: the table overflows its container by **201px**
(`scrollWidth 637` vs `clientWidth 436`), putting السعر — the most-read column in an
inventory list — off-screen behind a horizontal swipe. Headers wrap to 2–3 lines
("المتاح / المتوفر"), inflating row height.

**To its credit the overflow is properly contained.** The document itself does *not*
scroll horizontally (`scrollWidth === clientWidth === 485`), which matches the design rule
— wide content scrolls inside its own `overflow-x-auto`. So this is a density/IA problem,
not a broken-layout one, which is why it's minor and why it's real design work.

**Fix:** below `sm`, collapse the 6-column table to a card-per-product (name + price +
stock badge) instead of scrolling a desktop table sideways. Needs a design pass — which
fields earn a place on the card is a product decision.

- **Current state:** `resources/js/Pages/Products/Index.vue`; the scroller is the
  `div.overflow-x-auto.rounded-xl` wrapping the table.
- **Acceptance:** no horizontal scroll below `sm`; price visible without swiping; document
  still never scrolls horizontally.

## T4.2 — 27 remaining sub-44px tap targets

PR #77 fixed the two worst (favourite star 30→44, nav toggle 40→44). **29 of 94**
interactive elements on POS at 485px were under 44×44; **27 remain**.

- **Approach:** re-measure first, then apply the pattern PR #77 established — expand the
  hit area via `::before`, keep the visual size, so flat density is preserved. Bump the box
  only where the visual can afford it.
- **The gotcha, learned in PR #77:** `inset` on an absolutely positioned child resolves
  against the **padding box**. With a 1px border, `-inset-[7px]` lands on 42px, not 44.
  `-inset-2` is the value that works for a 30px bordered control. Verify by hit-testing
  with `elementFromPoint`, not by reading the class.
- **Acceptance:** every interactive element on POS ≥44×44 hit area at mobile widths.

## T4.3 — The export pill collides too

`resources/js/Components/ExportPanel.vue:256` is a **second** floating pill at
`fixed bottom-4` with the same `isRtl ? 'left-4' : 'right-4'` logic and the same `z-50` as
the operations pill. It therefore collides with the POS cart bar exactly as the operations
pill did — **and with the operations pill itself**, if both are ever visible at once.

PR #77 introduced the contract to fix this; the export pill just hasn't adopted it:

```css
/* resources/css/app.css */
:root { --floating-stack-offset: 0px; }
@media (max-width: 767px) {
  .has-bottom-bar { --floating-stack-offset: 4.5rem; }
}
```

- **Fix:** add `+var(--floating-stack-offset)` to the bottom calc on `ExportPanel.vue:256`
  (the pill) and `:125` (its panel), mirroring `OperationsCenter.vue:292` and `:163`.
- **Not covered by the contract:** two pills stacking against *each other*. If both can
  show simultaneously, they need an ordering rule — **the audit never saw both at once, so
  this is unverified.** Check before designing for it.
- **Acceptance:** on `/pos` at mobile width, no floating element overlaps the cart bar;
  `elementFromPoint` over the cart total returns the cart bar.

## Verification note

Every measurement above was taken at **485px, not 390px** — Chrome enforces a ~500px
minimum window width. 485 is below the `sm` breakpoint so the mobile branch renders, but
these numbers are not proof of behaviour at 390px. Re-measure at a true 390px (device
emulation, or a real device) before calling T4.1 done.
