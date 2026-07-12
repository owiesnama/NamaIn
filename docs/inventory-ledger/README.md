# Inventory Ledger — PRDs

Product requirements for migrating NamaIn's inventory from a mutable `stocks.quantity` source of truth to an **append-only, signed stock-movement ledger** with a **per-tenant inventory strategy** (`purchase_driven` vs `free_form` + nested `allow_overselling`).

See the audit & verdict that motivate this work in the approved plan (Phase 1 findings + Phase 2 verdict). **Headline:** the ledger (`stock_movements`) already exists, is written through a single choke point (`Storage::addStock/deductStock/setStockTo`), is signed + polymorphic + append-only in practice, and is currently latent (no readers). `stocks.quantity` already behaves as an incrementally-updated, row-locked cached balance. The one unavoidable structural change is that `stocks.quantity` is **unsigned** today, so negatives are impossible. We therefore **extend, not replace**, and migrate incrementally (parallel-run), defaulting every existing tenant to `purchase_driven`.

## Milestones

| PRD | Milestone | One PR? | Depends on |
|---|---|---|---|
| [M1](M1-ledger-hardening.md) | Ledger hardening (movement type, append-only) | yes | — |
| [M2](M2-balance-reconciliation.md) | Balance helper + reconciliation | yes | M1 |
| [M3](M3-strategy-setting.md) | Strategy setting + policy layer | yes (+ M3.T3.3 separate) | M2 |
| [M4](M4-free-form-mode.md) | Free-form mode + single-point enforcement | yes | M3 |
| [M5](M5-product-edit-delta.md) | Product-edit-as-adjustment-delta | yes | M2 |
| [M6](M6-strategy-switch-audit.md) | Strategy-switch flow + audit event | yes | M3 |
| [M7](M7-negative-stock-report.md) | Negative-stock reconciliation report | yes | M2 |
| [M8](M8-provisional-costing.md) | Provisional costing + back-fill | yes | M4 (ship together) |
| [M9](M9-opening-balance-migration.md) | Data migration: opening balances | yes (+ M9.T9.2 separate) | M1, M2 |
| [M10](M10-cutover.md) | Cutover: reads → ledger | yes | M9 |

**Critical path:** M1 → M2 → M3 → M4; M8 ships with M4; M9 → M10. M3.T3.3 (tenant-qualify preferences cache) and M9.T9.2 (fix seeder bypasses) are independent hygiene PRs that unblock their milestones.

## Cross-cutting conventions (apply to every task)

- **TDD** — Pest feature/unit test written first (`php artisan make:test --pest`); run with `php artisan test --compact --filter=…`.
- **Localization** — every user-facing string via `__()`; Arabic-first.
- **RTL + dark mode** — every Vue element per `.ai/Design rules`.
- **Pint** — `vendor/bin/pint --dirty` on touched PHP before finalizing.
- **Additive & reversible migrations only** until M10; nothing destructive before cutover.
- **Tenant scoping** — new tables carry `tenant_id` and extend `BaseModel` (except pivots).

## PRD template

Each milestone PRD follows this structure (standard depth, ~1 page + task specs):

```
# PRD — Mx: <Title>
Status · Milestone · Depends on · PR grouping
## 1. Problem
## 2. Goals / Non-goals
## 3. Current state (from audit, with file:line)
## 4. Design & behavior
## 5. Data model / schema changes
## 6. Task specs   → per task: Behavior · Files · Edge cases · Acceptance criteria · Test plan · Size
## 7. Edge cases (cross-task)
## 8. Test plan (summary)
## 9. Rollout & backwards compatibility
## 10. Open questions
```
