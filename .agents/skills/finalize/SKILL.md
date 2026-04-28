---
name: finalize
description: Final verification workflow before handoff, merge, or pull request. Use when the user types /finalize or asks to finalize code, verify readiness, run tests, prepare a final summary, or check that a change is safe to ship.
---

# Finalize

Before finalizing code or a pull request, verify that the work is ready to hand off.

## Workflow

1. Inspect the current diff and changed files.
2. Confirm the implementation still matches the user's newest request.
3. Run the relevant automated tests for the changed surface.
4. For broad or release-like changes, run the full available suite, including `php artisan test` and frontend or Cypress tests when present and applicable.
5. If a test cannot be run, say exactly why.
6. Check for obvious leftover debug code, accidental files, broken formatting, or unrelated changes.
7. Provide a concise final summary with changed files, tests run, failures, and residual risks.

Do not claim the work is fully verified unless the relevant tests actually ran successfully.
