# CLA-685 — Email the activation link to new External workers

Linear: CLA-685 (In Progress). Branch `feat/cla-685-activation-email` from `origin/main` (8a0a6bf4), worktree `.worktrees/cla-685`.
Today an External worker (CLA-683) only gets a first-password link when an operator runs `core:issue-activation-code` (CLA-653) and hands it over; that link lasts 60 minutes.

## Specs

- S1. Creating an **External worker** account (Users > Create, `account_type = external`) sends exactly one activation email to the new account, after the account and its roles are committed (never for a creation that rolled back).
- S2. Creating an **Internal employee** or a **Client contact** account sends no activation email (behavior unchanged).
- S3. The activation link in that email is valid for **72 hours**. The forgot-password flow (`sendResetLink()`) keeps **60 minutes**. `core:issue-activation-code` keeps its current lifetime (60 minutes). Code generation and hashing stay in one place (`PasswordResetService::issueActivationCode()`), with the lifetime as a parameter.
- S4. The email follows the existing reset mail conventions (`MailBrand::forUser()` sender and company, the recipient's language via `preferredLocale()`, queued, `afterCommit`, retries like `PasswordResetNotification`). It contains: a greeting with the user's name, a button to create the password pointing at the panel reset URL (`Filament::getPanel('admin')->getResetPasswordUrl($code, $user)`), the 72-hour validity, the notice that the backoffice is only reachable from the private network — **VPN or office** —, and what to do if the link expired (ask an administrator to resend it). Texts in `en` and `nl`.
- S5. EditUser gets a **"Resend activation email"** header action, visible only for accounts that are active, have no `microsoft_id` and have not completed password setup (`! hasCompletedPasswordSetup()`). It requires confirmation, issues a new 72-hour code (the previous code stops working because the hash is replaced), sends the same email, and shows a success notification.
- S6. Out of scope: 2FA for local accounts (CLA-684); emailing Internal employees or Clients; changing the forgot-password lifetime.

## Tasks

- T1. [S1-S6] Service TTL parameter + AccountActivationNotification + CreateUser hook + EditUser resend action + lang en/nl + tests + docs (module-contracts Core section, handoff row, commands-runbook note). Route: worker. Commit: pending. Evidence: RED 7 failed / 3 passed (guards); GREEN `AccountActivationEmailTest` 12 passed (incl. 2 verify probes: outer rollback cancels the email, forced resend on ineligible accounts changes nothing); ExternalWorkerAccountTest 16, UserProvisioningTest 16, IssueActivationCodeCommandTest 11, PasswordResetFlowTest 12, BackofficePasswordResetTest 10 green; Core suite 431 passed / 2 failed (`MailBrandTest`, pre-existing locally). Independent verify: approve (S1-S6 met).
  Evidence: AccountActivationEmailTest 10/10 (RED first: 7 failed, 3 passed); ExternalWorkerAccountTest, UserProvisioningTest, IssueActivationCodeCommandTest, PasswordResetFlowTest and BackofficePasswordResetTest all green.

## Log

- L1. User: "funciono sudo -n -u www-data env HOME=/tmp php artisan core:issue-activation-code... me pregunto con el proximo como seria? ese enlace no deberiamos enviarlo por correo al user que se acaba de loguear de hecho el correo deberia de aclararle al nuevo user de que debe de estar en nuestra red privada ya que fuera de la red privada no tiene acceso"
- L2. User decisions: "Solo External worker (recomendado)"; "72 horas (recomendado)"; resend button "Sí (recomendado)"; private network: "VPN u oficina".
- L3. User: "go".
- L4. T1 implemented test-first by the worker: RED 7 failed / 3 passed, then GREEN 10/10; regression files green. Not committed.
- L5. Independent verify approved; its two probes were added and pass. Noted (baseline, unchanged): the queued notification payload carries the URL, as the reset mail already does.
