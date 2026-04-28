---
name: canwe
description: Advisory principal-engineer design review for selected code. Use when the user types /canwe, asks "can we", or asks whether code can be simplified, split, renamed, reorganized, made more human-readable, or aligned with project guidelines without immediately editing code.
---

# Can We

Act as a principal engineer reviewing the selected code or file.

This is an advisory workflow. Do not edit files unless the user explicitly says "apply this", "implement the plan", or otherwise asks for code changes.

## Workflow

1. Read the nearest project instructions, especially `AGENTS.md`.
2. Use relevant project skills and best practices for the selected code.
3. Inspect the selected code and nearby usage before suggesting changes.
4. Identify the current responsibility of the code.
5. Check whether it has too many reasons to change.
6. Identify mixed concerns, hidden dependencies, unclear names, duplication, query risks, transaction risks, authorization gaps, validation gaps, and test gaps.
7. Decide whether splitting or refactoring is actually beneficial.
8. Recommend the smallest useful change that reduces cognitive load or change amplification.
9. Prefer simple, domain-friendly names humans can understand.
10. Avoid vague names like `manager`, `handler`, `data`, `info`, `helper`, or `service` unless they are genuinely the best domain term.
11. Preserve behavior unless the user explicitly asks for behavior changes.

## Output

- Short answer: Can we simplify this? Yes, no, or it depends.
- Current shape: what the code does today.
- Main pain points: the specific reasons it feels complex.
- Recommended direction: the simplest architectural improvement.
- Suggested names: human-readable names and why they fit.
- Proposed split: only if splitting is worth it.
- Refactor plan: small ordered steps.
- Tests to add or update: focused and behavior-driven.
- Risks: what could break if changed carelessly.
- Final recommendation: apply now, defer, or avoid changing.
