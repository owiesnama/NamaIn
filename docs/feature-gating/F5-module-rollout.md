# PRD — F5: Module rollout

**Status:** Draft · **Phase:** F5 · **Depends on:** F2, F3 · **PR grouping:** one PR per module group

## 1. Problem

F2 proved the enforcement pattern on `bookings` + `max_products`. Every other gateable module is still ungated — its routes carry no `feature:` gate and its nav/buttons no `hasFeature()` check, so hiding a nav item (if any) is the *only* thing standing between an un-entitled tenant and the feature. This phase **systematically applies gates and limits across the remaining catalog**, module by module, with a repeatable checklist so nothing is missed and no ungated backdoor lingers.

## 2. Goals / Non-goals

**Goals**
- For each gateable module: add `feature:<key>` to **all** its routes (server boundary), gate its nav/buttons on `hasFeature()` (F3 UX), and enforce any relevant limits (`WithinPlanLimit` + domain-action enforcement where over-shoot is unacceptable).
- A **route-coverage test per module** (extending the F2 pattern) proving no route in the module lacks its gate.
- Keep the `Feature` enum, `PlanSeeder`, and `LimitUsage` in sync as features are added.

**Non-goals**
- New engine/middleware/rule work (all from F1/F2).
- New admin UI (F4 already renders any enum case).
- Billing.

## 3. Current state (from audit)

Gateable modules (from `routes/tenant.php` + `resources/js/Pages/`): Catalog/Products, Contacts (Customers/Suppliers), Sales invoices, **POS** (sessions/checkout), Purchases, **Quotes**, **Bookings** (done in F2), Inventory/Storages/Transfers (**multi-warehouse**), Expenses, Payments/**Cheques**, Treasury, **Reports** (advanced), **Exports**. Existing `can:` permission gates and policies are the placement precedent; `feature:` composes alongside them.

## 4. Design & behavior

Group modules into shippable PRs (suggested):
- **F5a — Point of sale:** gate `pos` routes; POS nav/buttons; (limit `max_pos_sessions` if adopted).
- **F5b — Multi-warehouse:** gate `multi_warehouse` (storages beyond the first, stock transfers); enforce `max_warehouses` (`WithinPlanLimit` + block warehouse creation in the domain action). Existing single-warehouse tenants unaffected.
- **F5c — Quotes & advanced reports:** gate `quotes` and `advanced_reports`.
- **F5d — Exports & cheques:** gate `exports` (export routes/jobs) and `cheques` (`payments.manage-cheques`).
- **F5e — Team size:** enforce `max_users` on invitations/user-add — **in the domain action**, not only the FormRequest (over-shoot unacceptable for seat limits).

Each module follows the **rollout checklist** (§6). Feature keys already exist in the F1 enum (or are added here with matching `PlanSeeder`/`LimitUsage`/tier updates).

**Limit enforcement placement.** Advisory limits (products) stay FormRequest-only. **Hard limits** (`max_users`, `max_warehouses`) are enforced in the creating **domain action/service** so background/bulk/import paths (`ProcessImportJob`, bulk product actions) cannot bypass them.

## 5. Data model / schema changes

None, unless a new limit feature needs a `LimitUsage` mapping (code, not schema). Any newly added enum case must get: `PlanSeeder` values, a `LimitUsage` mapping (if a limit), and tier review.

## 6. Task specs

> Each module PR (F5a–F5e) instantiates this checklist. Sizes are per module: **S–M**.

### Rollout checklist (per module)
- **Routes:** add `feature:<key>` to every route in the module group (inside the authenticated, post-`ResolveTenant` group), composed with existing `can:`.
- **Limits:** apply `WithinPlanLimit` on relevant store requests; for hard limits, also enforce in the domain action; add/verify the `LimitUsage` mapping.
- **Frontend:** gate nav entries and action buttons on `hasFeature() && can()`; add `<FeatureLockHint>`/Upgrade affordances where an upsell is intended.
- **Seeder/tiers:** ensure Free/Basic/Pro include/exclude the feature intentionally so **no current tenant regresses** (back-fill or default-plan coverage verified before merge).
- **Tests:** entitled vs un-entitled behavior; a **route-coverage test** asserting no module route lacks the gate; limit at/over/unlimited; bypass-path test for hard limits (import/bulk cannot exceed).
- **Pint + localization + RTL/dark** on all touched files.

### T5a — POS · **M** · gate `pos`.
### T5b — Multi-warehouse · **M** · gate `multi_warehouse`, enforce `max_warehouses` (action-level).
### T5c — Quotes & advanced reports · **S** · gate `quotes`, `advanced_reports`.
### T5d — Exports & cheques · **S** · gate `exports`, `cheques` (incl. export jobs).
### T5e — Team seats · **M** · enforce `max_users` on invitation/user-add domain action.

(Each expands the checklist above with module-specific route files, Vue pages, and the store/action paths — filled in when the PR is picked up.)

## 7. Edge cases (cross-task)

- **No ungated backdoor:** every route (incl. nested `create`/`update`/`destroy`, print, export-job triggers) must carry the gate — the route-coverage test is the guard.
- **Bypass paths:** jobs, bulk actions, imports, and API tokens must respect hard limits — enforce in the domain layer, not just FormRequests.
- **Regression safety:** gating a module that existing tenants use requires the seeded tiers (or default plan) to include it, or a back-fill assigning the right plan — verify per module.
- **Grandfathering:** tenants already over a newly introduced limit keep existing rows; the limit blocks new creation only (consistent with F2).

## 8. Test plan (summary)

Per module: entitled/un-entitled route behavior, route-coverage completeness, limit enforcement (request + domain/bypass), nav/button gating, no-regression for current tenants. Cross-cutting arch test: every `Limit` feature has a `LimitUsage` mapping (kept green as features are added).

## 9. Rollout & backwards compatibility

Highest-risk phase for **regressions**, because it turns gates on for features tenants already use. Ship **one module group per PR**, each preceded by confirming tier/seed coverage or a tenant-plan back-fill so no active merchant loses access. Roll out behind the already-shipped engine; each PR is independently revertible.

## 10. Open questions

- Final tier matrix (which of Free/Basic/Pro includes POS, multi-warehouse, quotes, advanced reports, exports, cheques, and the numeric caps) — decide against real pricing before F5a; F4's admin UI lets you tune without redeploying.
- Which limits are "hard" (action-enforced) vs "advisory" (request-only) — proposed: `max_users`, `max_warehouses` hard; `max_products` advisory. Confirm.
- Do exports/cheques warrant limits (e.g. monthly export count) or pure on/off? Lean: on/off for v1.
