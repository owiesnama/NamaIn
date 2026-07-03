# Offline Version — PRD Index

Derived from [`docs/offline/roadmap.md`](../roadmap.md). Read the roadmap first: it
contains the current-state analysis (§1) and the settled decisions (§4) that every PRD
inherits.

| PRD | Title | Roadmap phase | Depends on |
|---|---|---|---|
| [PRD-01](prd-01-sync-foundations.md) | Sync foundations | Phase 0 | — |
| [PRD-02](prd-02-sync-protocol-api.md) | Sync protocol & cloud API | Phase 1 | PRD-01 |
| [PRD-03](prd-03-offline-client.md) | Offline client (NativePHP) | Phase 2 | PRD-01, PRD-02 |
| [PRD-04](prd-04-reconciliation-and-pilot.md) | Reconciliation, device management & pilot | Phase 3 | PRD-02, PRD-03 |

## Inherited decisions (apply to all PRDs)

1. **Packaging:** NativePHP desktop app running the existing Laravel codebase on SQLite.
2. **Serials:** per-register series (`INV-SA-26-R{register}-{seq}`); numbers printed
   offline are final.
3. **MVP scope:** POS + expenses offline. Purchases/receiving/treasury
   transfers/reports stay online-only until Phase 4.
4. **Credit sales:** allowed offline against cached limit/balance; breaches flagged on
   sync, never blocked retroactively.
5. **Multi-device:** multiple concurrent offline registers per store from v1. The cloud
   is the single source of truth; devices are edge writers.

## Working agreement for implementers

- Every PRD lists numbered functional requirements (`FR-x`) — reference them in design
  docs, commits, and review discussions.
- "Open questions" sections are inputs to the design phase, not blockers to start.
- All user-facing strings must be localized (en/ar) via the existing `__()` catalogs.
- Follow the project conventions in `CLAUDE.md` / `AGENTS.md` (TDD, Pest feature tests,
  Action classes for writes, Money value object for amounts).
