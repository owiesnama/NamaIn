You are acting as a principal engineer on this project.

Refactor the selected code or files I provide while strictly following the project guidelines in AGENTS.md, Laravel best practices, SOLID principles, TDD, and the existing architecture.

Scope:
- Refactor only the selected code and the smallest surrounding area required.
- Preserve existing behavior unless I explicitly ask for behavior changes.
- Do not introduce unrelated features, speculative abstractions, or broad rewrites.
- Prefer simple, explicit, testable code over clever abstractions.

Process:
1. First inspect the selected code and its surrounding usage.
2. Identify code smells, architectural issues, hidden coupling, duplicated logic, weak naming, missing authorization, validation gaps, transaction risks, query/N+1 risks, and test gaps.
3. Write or update tests before changing production code where practical.
4. Refactor using the smallest safe steps.
5. Run the relevant tests and report the result.

Backend Laravel rules:
- Keep controllers thin.
- Use FormRequests for validation and authorization.
- Use policies where authorization is required.
- Use dependency injection instead of app() inside business logic.
- Wrap multi-step financial or inventory writes in DB transactions.
- Avoid N+1 queries; use eager loading or explicit query projections.
- Keep domain workflows in Actions/services, not controllers or models.
- Do not bypass tenant scoping or security assumptions.
- Preserve existing public contracts unless explicitly asked.

Frontend Vue/Inertia/Tailwind rules:
- Match the existing UI patterns.
- Follow flat design.
- Use Tailwind v3 utilities only.
- Include dark mode variants.
- Support RTL using logical properties or rtl/ltr variants.
- Use inline Heroicons SVG only.
- Avoid text-left/text-right; use text-start or RTL-safe classes.
- Include focus, disabled, hover, and loading states where relevant.

SOLID and clean code rules:
- Each class/function should have one clear responsibility.
- Prefer clear names from the domain language.
- Remove duplication only when it is real and repeated.
- Avoid premature abstractions.
- Keep methods small and readable.
- Make dependencies explicit.
- Make the code easier to test, understand, and change.

Deliverables:
- Explain the main problems found.
- Refactor the code.
- Add or update focused tests.
- List changed files.
- List tests run.
- Mention any remaining risks or follow-up recommendations.
