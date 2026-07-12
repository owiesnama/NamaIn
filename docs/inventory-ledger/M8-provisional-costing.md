# PRD — M8: Provisional costing + back-fill

**Status:** Draft · **Milestone:** M8 · **Depends on:** M4 · **PR grouping:** one PR — **ship together with M4** (overselling makes zero/stale COGS frequent and visible)

## 1. Problem

A sale's cost of goods is captured as a one-time snapshot of the product's `average_cost` at the moment the sale line is written, and is never revisited. When a sale happens before any purchase exists — or once free-form/overselling drives stock negative — there is no real cost basis, so the line is booked with `unit_cost = 0` (or a stale value). That silently produces **0 COGS → inflated / 100% gross margin** in the P&L, and nothing ever corrects it once the real purchase arrives. Free-form mode (M4) turns this from a rare edge into an everyday occurrence, so M8 must ship alongside it: book such sales with an explicit **provisional** cost, flag them, and **back-fill** the true cost when the purchase lands. We accept that profit reports become **eventually consistent**, and we make that visible rather than misleading.

## 2. Goals / Non-goals

**Goals**
- Flag sale lines booked without a real cost basis as provisional.
- When the real purchase cost lands, restate `unit_cost` (and thus margin) for the flagged lines it covers, then clear the flag.
- Make reports indicate when figures include provisional costs.

**Non-goals**
- Changing the costing method itself (moving weighted-average stays; see `Product::replayMovingAverageCost`).
- Perfect lot-level matching of specific purchase units to specific sale units — we restate flagged lines against the newly-known cost, not a FIFO lot ledger.
- Retroactively "locking" history: back-fill intentionally restates prior periods (eventual consistency is the accepted model).
- Provisional costing for purchase lines (purchases set cost at delivery already).

## 3. Current state (from audit)

- **Sale `unit_cost` is a snapshot of `average_cost` at line creation**, never re-snapshotted: `app/Actions/Pos/ProcessPosCheckoutAction.php:126`, `app/Actions/StoreInvoiceAction.php:68`, `app/Actions/UpdateInvoiceAction.php:64` (all `?? 0`).
- `average_cost` **defaults to `cost`** on product create (`app/Models/Product.php:41`); the moving-average replay **clamps on-hand to `max(qty,0)`** (`app/Models/Product.php:101`) and returns `null` → caller falls back to `cost` until a purchase is delivered.
- **Only purchases** recompute the average: `app/Models/Transaction.php:180` (`recalculateAverageCost()` inside `add()` ~`:164-185`); `ReceiveGoodsAction` also recalculates on receipt (`app/Actions/Purchase/ReceiveGoodsAction.php`).
- Net effect today: a zero-cost sale ⇒ `unit_cost = 0` ⇒ **0 COGS** ⇒ inflated margin, and it is **never back-filled**.
- Single authoritative margin site: `app/Queries/Reports/ProfitAndLossQuery.php` — COGS = `SUM(base_quantity * COALESCE(unit_cost,0))` (`:48`, `:97`); gross/net margin derived from it. Dashboard mirror: `DashboardController::grossProfit` (`app/Http/Controllers/Core/DashboardController.php:19`).
- No column, flag, or report currently distinguishes a provisional cost.

## 4. Design & behavior

When a sale line is created, determine whether a real cost basis exists (product `average_cost > 0` **and** on-hand not negative). If it does, behave exactly as today. If it does not, book the line with a **provisional** cost — the last known cost (`average_cost` or `cost`), or `0` if none exists — and set `cost_provisional = true`.

When a purchase for that product is delivered (the point where `recalculateAverageCost()` already runs and a real cost becomes known), enqueue `BackfillProvisionalCostsJob` for the product. The job restates `unit_cost` on the covered flagged sale lines to the now-known cost and clears their flag. The job is **idempotent** and safe to run repeatedly (it only touches still-flagged lines). Because `ProfitAndLossQuery` reads `unit_cost` live, restatement automatically corrects past-period margins on the next report render.

Reports surface a non-blocking indicator ("figures include provisional cost") for any period whose sale lines include a still-flagged row, so a reader knows the margin is not yet final.

**Coverage policy (which flagged lines a purchase back-fills):** by default, all still-flagged sale lines for that product in the tenant (simplest, matches the "eventual consistency" framing). This is called out as an open question in §10 if a time-bounded or quantity-bounded policy is preferred.

## 5. Data model / schema changes

- **New migration:** `transactions.cost_provisional` boolean, default `false`, indexed (used to find lines to back-fill and to flag report periods). Additive, reversible.
- No change to how `unit_cost` is stored; the job updates its value in place (a permitted mutation — `transactions` is not the append-only ledger).

## 6. Task specs

### T8.1 — Provisional flag on zero-basis sale lines · **M**
- **Behavior:** add `cost_provisional`; at sale-line creation, set it `true` when no real cost basis exists (`average_cost = 0`, or on-hand negative for the product/storage), booking a provisional `unit_cost` (last known cost, else `0`). Normal sales set it `false`.
- **Files:** *new* migration for `transactions.cost_provisional`; `app/Actions/Pos/ProcessPosCheckoutAction.php:126`; `app/Actions/StoreInvoiceAction.php:68`; `app/Actions/UpdateInvoiceAction.php:64`.
- **Edge cases:** invoice edit (`UpdateInvoiceAction` deletes+recreates lines) must re-evaluate the flag; negative-on-hand detection must use the same balance source the strategy layer uses (M4); a product with a configured `cost` but no purchases is provisional against that `cost`, not a hard zero.
- **Acceptance criteria:** a sale of a product with `average_cost = 0` (or into negative stock) is flagged `cost_provisional = true`; a sale with a real average is `false`.
- **Test plan:** feature test — sale before any purchase → flagged + provisional unit_cost; sale after purchase → not flagged; edited invoice re-evaluates.

### T8.2 — Back-fill job on purchase delivery · **L**
- **Behavior:** `BackfillProvisionalCostsJob($productId, $tenantId)` restates `unit_cost` to the now-known cost for still-flagged sale lines it covers and clears their flag; recomputes/marks affected margins implicitly (P&L reads `unit_cost` live). Idempotent — no-op when nothing is flagged.
- **Files:** *new* `app/Jobs/BackfillProvisionalCostsJob.php`; dispatch hooks in `app/Actions/Purchase/ReceiveGoodsAction.php` and `app/Models/Transaction.php` `add()` (~`:164-185`, right after `recalculateAverageCost()`).
- **Edge cases:** must run **outside** the guarded ledger (it mutates `transactions`, not `stock_movements` — no immutability conflict); concurrent purchases → job idempotency + a row lock on the lines being restated; a later purchase at a different cost should not double-apply (flag already cleared); partial coverage policy (see §4 / §10); queue failure → retry-safe.
- **Acceptance criteria:** after a late purchase, previously-flagged sale lines carry the real `unit_cost`, flags cleared; re-running the job changes nothing; P&L COGS/margin for the affected period reflects the restated cost.
- **Test plan:** feature test — sell into zero/negative basis, deliver a purchase, assert flagged lines restated + unflagged and `ProfitAndLossQuery` margin corrected; idempotency test (run twice); concurrency test (two deliveries).

### T8.3 — Reports indicate provisional figures · **M**
- **Behavior:** `ProfitAndLossQuery` exposes whether the period includes any still-flagged lines (e.g. a `has_provisional_costs` flag / count); the P&L Vue page and dashboard render a subtle "includes provisional cost" indicator (localized, RTL, dark).
- **Files:** `app/Queries/Reports/ProfitAndLossQuery.php:48,97`; its Vue page (`resources/js/Pages/Reports/…` P&L); `app/Http/Controllers/Core/DashboardController.php:19` + `Dashboard.vue` gross-profit tile.
- **Edge cases:** period with zero flagged lines shows no indicator; export formats should carry the same caveat; indicator must not alter the numeric COGS, only annotate it.
- **Acceptance criteria:** a period containing a flagged line renders the provisional indicator; once back-filled, the indicator disappears; numbers unchanged by the indicator itself.
- **Test plan:** feature test asserting the query flag toggles with presence of flagged lines; Vue smoke test that the indicator renders when flagged.

## 7. Edge cases (cross-task)

- Invoice edit re-creating lines must not orphan or double-count flags.
- A product with a nonzero configured `cost` but no purchase history is provisional against `cost`, not zero — back-fill still corrects it to the true purchased cost.
- Back-fill mutates `transactions.unit_cost`; this is deliberately allowed (transactions are not the append-only `stock_movements` ledger from M1).
- Overselling (M4) is the primary generator of provisional lines — this is why M8 ships with M4.

## 8. Test plan (summary)

- Flagging on zero/negative basis; not flagging on real basis (T8.1).
- Back-fill restates + clears flags; idempotent; concurrency-safe (T8.2).
- P&L/dashboard provisional indicator toggles correctly (T8.3).
- Regression: existing `ProfitAndLossQuery` numbers unchanged for fully-costed periods.

## 9. Rollout & backwards compatibility

Additive column + a queued job; no destructive change. Existing (purchase_driven) tenants rarely hit the provisional path, so behavior is effectively unchanged for them; the value appears mainly once free-form/overselling (M4) is enabled — hence the joint release. Reversible: drop `cost_provisional` and remove the dispatch hooks; restated `unit_cost` values remain valid.

## 10. Open questions

- **Coverage policy:** should a purchase back-fill *all* still-flagged lines for the product, or only up to the purchased quantity / within a time window? (Lean: all still-flagged lines — simplest, matches eventual-consistency framing.)
- **Provisional basis when none exists:** book `0`, or the product's configured `cost`? (Lean: last known cost = `average_cost ?: cost`, else `0`.)
- Should back-fill run inline on delivery or strictly via the queue? (Lean: queue, for POS latency.)
