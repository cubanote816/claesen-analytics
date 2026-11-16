# CLA-684 — Require 2FA for every local-password panel session

Linear: CLA-684 (In Progress). Branch `feat/cla-684-require-2fa-local-password` from `origin/main` (17a84c58 = production), worktree `.worktrees/auth-users`.
Today `EnsureAdminRoleMultiFactorAuthenticationIsEnabled` forces a second factor only for super_admin/admin (CLA-464, ADR D8); every other panel role signs in with the password alone.

## Specs

- S1. User decision (L3): "todos los usuarios en quentran con email/pass necesitan el 2FA los que entran con microsoft ya microsoft se lo aplica esos se quedan como esta". Every session in the **admin** or **bertels** panel that was not signed in through Microsoft must have a second factor configured, whatever its role (super_admin, admin, financial_manager, hr_manager, viewer, technician, and any role that can reach a panel). Without a configured factor, every panel page redirects to that panel's `multi-factor-authentication.set-up-required` route.
- S2. A local-password user of any role who already has a factor configured gets Filament's MFA challenge on the Login page; without a valid code they stay a guest.
- S3. Sessions marked by the Microsoft callback (`AuthenticatedVia::isMicrosoft`) keep skipping the gate, whatever the role (unchanged).
- S4. Unchanged: `client` is refused at the first login step; with `core.panel_mfa_enforced = false` (test suite) nothing is gated.
- S5. A technician without a factor is sent to set-up and, once a factor is configured, reaches `MyWorkOrders`.
- S6. Out of scope: password logins outside the Filament panels (`api/v1/auth/login`, `login/sport`, Safety `v1/login`, KNX `auth/login`, client portal) — CLA-691.
- S7. Docs and comments that say "MFA only for super_admin/admin" are updated; ADR D8 gets an addendum.

## Tasks

- T1. [S1-S5] Remove the role gate, rename the middleware to `EnsureLocalPasswordMultiFactorAuthenticationIsEnabled`, update both panel providers and StepUpAuthenticator comments; flip/add tests in MultiFactorAuthenticationEnforcementTest, MicrosoftSessionMfaExemptionTest, LoginMultiFactorChallengeTest. Route: inline. Commit: 44a076b6.
  Evidence: RED 6 failed / 31 passed (4 non-admin roles + technician + viewer password session); GREEN focused `MultiFactorAuthenticationEnforcementTest|MicrosoftSessionMfaExemptionTest|LoginMultiFactorChallengeTest|ClaesenBaseline|StepUp|MyWorkOrdersFilamentTest` 128 passed (635 assertions); route snapshot changed only by the class name. Pint findings on the 3 touched files are identical on HEAD (pre-existing). Regression `Modules/Core/tests tests/Feature`: 732 passed (3662 assertions).
- T2. [S6-S7] Docs: qa-test-users, release checklist, ADR D8 addendum, multiorg decisions log, Knx handover, CLAUDE.md, handoff.md. Route: inline. Commit: c103e244.
  Evidence: qa-test-users, release checklist, ADR D8 addendum, multiorg decisions log entry, Knx handover, CLAUDE.md, handoff.md row written.

## Log

- L1. User: "he iniciado sesiion en produccion con email/password y no me ha pedido el 2FA eso representa una fuga de seguridad"
- L2. Evidence: production runs 17a84c5 (contains CLA-648 fix 3d4d7743); the user is `viewer` with no factor configured, so no prompt was by design (CLA-464), not a bypass.
- L3. User: "adelante todos los usuarios en quentran con email/pass necesitan el 2FA los que entran con microsoft ya microsoft se lo aplica esos se quedan como esta"
- L4. User: "GO solo panel + ticket aparte para APIs". CLA-691 created (Backlog) for the non-panel password logins.
- L5. First RED run failed every test with `migrations` table missing: the worktree test DB was half-migrated after a run cut by a timeout; `bin/wt-test --drop-db` fixed it.
- L6. Independent verifier launch refused by the runtime (risk declaration not recognized; no retry allowed) — unavailable. Inline check: of 169 admin/bertels panel routes, the 10 without the MFA middleware are login, logout, password-reset request/reset and the MFA set-up page; `AuthenticatedVia::markMicrosoft` has a single writer (`MicrosoftAuthController.php:225`). Native assess vs 17a84c58: risk high (agent escalation item 2), reviewDue false (under_budget).
