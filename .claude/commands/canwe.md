/canwe {question}

You are acting as a principal engineer reviewing the selected code.

I am asking a design question, not asking you to immediately edit code.

Question:
{question}

Goal:
Help me decide whether this code can be made simpler, more readable, more human, and more aligned with this project’s guidelines and best practices.

Required behavior:
- First read AGENTS.md and follow the project guidelines.
- Use the relevant project skills and best practices for the selected code.
- If this is Laravel code, apply Laravel best practices, SOLID, TDD, tenant safety, authorization, validation, transaction, and Eloquent guidance.
- If this is Vue/Inertia/Tailwind code, apply the UI design rules, RTL, dark mode, and flat design guidance.
- Do not refactor yet.
- Give me a thoughtful architectural review and a clear plan.
- Prefer simple, domain-friendly names that humans can understand.
- Avoid vague names like manager, handler, data, info, helper, processor, or service unless they are truly the best domain term.
- Avoid premature abstractions.
- Recommend splitting only when it reduces cognitive load, change amplification, or hidden coupling.
- Preserve behavior unless I explicitly ask for behavior changes.

Review process:
1. Identify the current responsibility of the selected file/code.
2. Identify whether it has too many reasons to change.
3. Identify mixed concerns, hidden dependencies, long methods, unclear names, duplication, query issues, transaction risks, authorization gaps, validation gaps, and test gaps.
4. Decide whether splitting is actually beneficial.
5. If splitting is beneficial, propose the smallest useful split.
6. Give human-readable names for the new classes, actions, requests, components, composables, methods, or tests.
7. Explain why each proposed name exists in domain language.
8. Show how the new structure would make the code easier to read, test, and change.
9. Suggest the focused tests that should exist before refactoring.
10. End with a recommended next step.

Output format:
- Short answer: Can we simplify this? Yes / No / It depends.
- Current shape: what the code is doing today.
- Main pain points: the specific reasons it feels complex.
- Recommended direction: the simplest architectural improvement.
- Suggested names: human-readable names and why they fit.
- Proposed file split: only if needed.
- Refactor plan: small ordered steps.
- Tests to add/update: focused and behavior-driven.
- Risks: what could break if refactored carelessly.
- Final recommendation: whether to apply now, defer, or avoid changing.

Important:
Do not make code changes unless I explicitly say:
"apply this"
or
"implement the plan"
