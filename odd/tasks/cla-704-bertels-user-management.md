# CLA-704 — Users section in the Electro Bertels panel

Linear: CLA-704 (In Progress). Branch `feat/cla-704-bertels-user-management` from `origin/main` (653b2b35), worktree `.worktrees/auth-users`.
The Bertels panel (`/bertels`) registers no resources and Claesen's Users > Create always assigns the Claesen organization, so no Bertels user can be managed. `AGENTS.md` carries a temporary, uncommitted CLA-704 exception added by the user: never stage or commit `AGENTS.md`. Risk: item 2 (security: permissions and the organization boundary).

## Specs

- S1. User (L1, L3): a Users section in the Bertels panel, like Claesen's. Only `super_admin` can open it and use any of its actions ("Solo super_admin").
- S2. Organization boundary, independent of `ORGANIZATIONS_ENFORCE` (off in production): the section lists, opens and edits **only** users of the Bertels organization (`Site::forPanel('bertels')->organization_id`); a Claesen user's record is unreachable by URL (404/403). Every user created there gets the Bertels organization. When the Bertels site/organization does not exist, the section is unavailable (fail closed), never falls back to Claesen.
- S3. User (L3): "seria igual que claesen los user con correo @electrobertels.be usan microsoft y su 2FA nativo y los que usan email/contrasena nuestro sistema de 2FA".
  - Account with an `@electrobertels.be` email: created without a password; signs in with Microsoft (Entra second factor, exempt from app MFA as Claesen's Microsoft sessions are); no activation email.
  - Any other email: local account; created without a password and receives the activation email (Bertels brand, sender `info@electrobertels.be` from the Bertels site) whose link opens the **/bertels** reset page; signs in with email + password and the app 2FA (CLA-684).
  - The domain is server-side validated by account type (an `@electrobertels.be` email cannot be created as a local account and vice versa), like Claesen's Internal employee / External worker split.
- S4. User (L3): assignable roles are `viewer` and `admin` only; any other role id (e.g. `super_admin`, `client`) is rejected server-side on create and on role change. Role changes keep Claesen's step-up (MFA code) and `syncRoles` behaviour. User (L4): viewer and admin keep identical access in the Bertels panel for now.
- S5. Edit-page actions as Claesen's where they apply to the account type: change roles (step-up), resend activation email (local accounts that have not set a password; link to /bertels), revoke sessions.
- S6. Microsoft sign-in per panel: the "Sign in with Microsoft" button on `/bertels/login` returns the user to `/bertels`; after a Microsoft sign-in a Bertels-organization user always lands on the Bertels panel and a Claesen user on the Claesen panel — never a 403 caused by the wrong panel. Matching stays by email; existing roles are never overwritten.
- S7. Claesen APIs: password and token sign-ins of Claesen apps (`api/v1/auth/login`, `api/v1/auth/login/sport`, Safety `v1/login`, client-portal login, and the Microsoft API sign-in for Claesen apps) reject a user whose `organization_id` is set and is not Claesen, with the same response as an unauthorized account, issuing no token or session. KNX (Bertels') is excluded. Existing Claesen users behave exactly as today.
- S8. Docs: ADR D10 addendum with the user's decision to advance it, multi-organization decisions log, `handoff.md` row (end of table), `docs/qa-test-users.md`, module contracts (Core). The stale `BertelsPanelAccessTest` is brought in line with the CLA-611 access rule.
- S9. Out of scope: read-only viewer, a Bertels admin role, KNX access for Bertels users, turning on `ORGANIZATIONS_ENFORCE`, creating the Bertels organization/site rows.

## Tasks

- T1. [S1, S2, S3, S4, S5] Bertels Users resource (list/create/edit, two account types, roles allowlist, actions, activation link to /bertels) + tests. Route: worker. Commit: pending.
  Evidence: RED `BertelsUserManagementTest` 28 failed / 1 passed (Claesen activation-link preserve); GREEN 32 passed (159 assertions); `--filter='ClaesenBaseline|BertelsUserManagement|ExternalWorker|AccountActivation|StepUpAuthentication'` 145 passed after deliberately regenerating snapshots (+3 bertels/users routes, 491→494; PanelRegistry lists BertelsUserResource). Worker decisions: resend activation hidden for @electrobertels.be accounts; email read-only on edit; no delete action. Not proven by a test: the trait's defence-in-depth abort_if on change-roles (the form option check rejects first).
- T2. [S6] Microsoft sign-in per panel + tests. Route: worker. Commit: pending.
- T3. [S7] Claesen APIs reject non-Claesen users + tests. Route: worker. Commit: pending.
- T4. [S8] Docs and the stale panel-access test. Route: worker. Commit: pending.

## Log

- L1. User: "quieor que tambien dejes configurado para bertels el email info@electrobertels.be como sender principal para esta empresa"; then "la web de bertels no tiene la seccion de administra users como claesen"; then, after being told it advances ADR D10: "si vamos a crearlo ahora".
- L2. Exploration: Bertels panel has no resources by design; Claesen CreateUser hardcodes `Organization::claesenId()`; `ORGANIZATIONS_ENFORCE` off in production so `RequireOrganization`/`EnsureUserBelongsToPanelSite` are no-ops; a Bertels `admin` could get a token on Claesen password APIs; `MicrosoftAuthController` matches by email, never checks organization and always redirects to the admin panel; `BertelsPanelAccessTest` is stale. `info@electrobertels.be` verified sending in production (CLA-699).
- L3. User decisions: "Solo super_admin (Recomendado)"; login: "aplica los dos casos que me propusistes. seria igual que claesen los user con correo @electrobertels.be usan microsoft y su 2FA nativo y los que usan email/contrasena nuestro sistema de 2FA"; roles: "viewer, admin, Otro / no sé todavía".
- L4. User: viewer vs admin "Dejarlo igual por ahora (Recomendado)"; plan T1–T4: "GO". Temporary AGENTS.md exception for CLA-704 added by the user; "adelante".
