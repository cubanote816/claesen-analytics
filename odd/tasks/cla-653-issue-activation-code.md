# CLA-653 — A new local account can never set its first password

Linear: CLA-653 (In Progress). Branch `fix/cla-653-issue-activation-code` from `origin/main`, worktree `.worktrees/cla-653`.
The Users resource stores new local accounts with `password = null`; `sendResetLink()` refuses them, so nothing ever issues their activation code.

## Specs

- S1. `core:issue-activation-code {email}` issues the one-time code for an eligible local account and prints it with the reset URL, so an operator can hand it over.
- S2. Code generation, hashing and TTL live once in `PasswordResetService::issueActivationCode()`, shared with `sendResetLink()`; the forgot-password flow behaves exactly as before.
- S3. Refuses, with a clear reason and a non-zero exit, and without touching the account: unknown address, an address matching more than one account after normalization (L4), inactive account, Microsoft-linked account, account that already completed setup.
- S4. `--panel=` (default `admin`) chooses which Filament panel the printed reset URL points at (`admin` or `bertels`); an unknown panel is refused and lists the available ones. Added during implementation, accepted by the user (L3).
- S5. Out of scope: creating accounts; the employee/corporate-address guard is not bypassed.

## Tasks

- T1. [S1-S5] Command + service extraction + tests + runbook/handoff. Route: inline (ported from the WIP on `fix/cla-653-activation-code`). Commit: pending. Evidence: RED 7/7 on origin/main + ambiguous-address RED; GREEN `IssueActivationCodeCommandTest` 11 passed; Core suite 405 passed / 2 failed — both `MailBrandTest` (reset subject language), which fail identically on origin/main locally (pass in CI): pre-existing, local environment. Independent re-verify: S1, S3-S5 met; S2 met once the baseline confirmed the MailBrand failures pre-exist. Native assess: high (security escalation), review not due (under budget).

## Log

- L1. Ticket CLA-653 (Gap/Fix/Out of scope) as written in Linear on 2026-11-04.
- L2. User: "Terminar CLA-653. que falta?"
- L3. User asked to explain `--panel=`; recommendation to keep it (default `admin`, needed for Bertels accounts) and document it in the ticket. User: "go".
- L4. Independent verify (changes required): P2 ambiguous normalized email picked the first row → now refused (fail closed); P2 test gaps → added --panel=bertels link, unknown panel keeps an existing code (exact message), refused account keeps its code; P2 password-without-timestamp accepted on purpose (`hasCompletedPasswordSetup()` is the project's single source for "activated"; the provisioning migration backfilled `password_set_at`); P1 was the verifier's environment (no test run). RED: ambiguous-address test failed (exit 0 vs 1) before the fix; the 7 original tests failed 7/7 on origin/main.
- L5. Baseline: `MailBrandTest` fails 2/12 on origin/main locally too, so it is not caused by this change.
