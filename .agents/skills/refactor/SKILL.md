---
name: refactor
description: Refactor selected code or files as a principal engineer. Use when the user types /refactor or asks to clean up, simplify, reorganize, rename, split, or improve code while following AGENTS.md, project skills, SOLID, TDD, Laravel, Vue/Inertia, and Tailwind best practices.
---

# Refactor

Act as a principal engineer on this project.

Refactor only the selected code and the smallest surrounding area required. Preserve existing behavior unless the user explicitly asks for behavior changes.

## Workflow

1. Read the nearest project instructions, especially `AGENTS.md`.
2. Use relevant project skills for the touched stack.
3. Inspect the selected code, nearby usage, tests, routes, requests, policies, and UI components as applicable.
4. Identify code smells, hidden coupling, duplicated logic, weak names, missing authorization, validation gaps, transaction risks, N+1 risks, and test gaps.
5. Write or update focused tests before production code where practical.
6. Refactor in small behavior-preserving steps.
7. Prefer clear domain names over generic technical names.
8. Avoid broad rewrites, speculative abstractions, and unrelated cleanup.
9. Run the relevant tests and report the result.

## Laravel Guidance

- Keep controllers thin.
- Use FormRequests for validation and authorization.
- Use policies where authorization is required.
- Use dependency injection instead of `app()` inside business logic.
- Wrap multi-step financial, inventory, or cross-table writes in database transactions.
- Avoid N+1 queries with eager loading, subqueries, or explicit projections.
- Keep domain workflows in Actions or focused services, not controllers or fat models.
- Preserve tenant scoping and security assumptions.

## Vue/Inertia/Tailwind Guidance

- Match existing UI patterns.
- Follow the flat design system.
- Use Tailwind v3 utilities only.
- Include dark mode variants.
- Support RTL with logical properties or `rtl:` / `ltr:` variants.
- Use inline Heroicons SVG only.
- Avoid `text-left` and `text-right`; use `text-start` or RTL-safe classes.
- Include focus, disabled, hover, and loading states where relevant.

## Deliverables

- Main problems found.
- Refactor summary.
- Changed files.
- Tests added or updated.
- Tests run.
- Remaining risks or follow-up recommendations.
