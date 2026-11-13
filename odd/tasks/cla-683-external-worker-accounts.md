# CLA-683 — Backoffice accounts for internal workers who are not in Cafca

Linear: CLA-683 (In Progress). Branch `feat/cla-683-external-worker-accounts`, stacked on `fix/cla-653-issue-activation-code` (CLA-653), worktree `.worktrees/cla-683`.
Users > Create only accepts an active Cafca employee with a corporate address, or a client contact.

## Specs

- S1. New account type "External worker" in Users > Create: free name + email, role checkboxes.
- S2. Saved with `employee_id = null`, organization Claesen, `password = null`, `password_set_at = null`, `microsoft_id = null`; the password is set with the one-time activation code (CLA-653).
- S3. Server-side refusals (422, nothing created): empty name, invalid email, email already used (case/whitespace-insensitive), a `@claesen-verlichting.be` address ("Use the Internal employee type for @claesen-verlichting.be addresses."), no role, Super Admin or Client role.
- S4. The form does not offer Super Admin or Client to external workers; Internal employee still offers every role.
- S5. Internal employee and Client creation unchanged.

## Tasks

- T1. [S1-S5] CreateUserForm + CreateUser::externalWorkerData() + lang en/nl + tests + docs. Route: inline. Commit: pending. Evidence: RED 10/10 with exact reasons; GREEN `ExternalWorkerAccountTest` 14 passed (incl. 4 verify probes: forged attributes + real activation, crafted form payloads, default internal flow); `UserProvisioningTest` 16 passed; Core suite 415 passed / 2 failed (`MailBrandTest`, pre-existing locally, see CLA-653 L5). Independent verify: approve (S1-S5 met once probes passed).

## Log

- L1. User: "desde la web tenemos una restrincion que debemos eliminar solo puedo crear user con los empleados de la empresa que tienen cunatas de correo @claesen-verlichting.be tambien deberia de permitir la creacion de nuevos cuenta con correos fuera de la empresa trabajadores internos"
- L2. User decisions: who = "Personas que no están en Cafca"; order = "Mergear CLA-653 primero (recomendado)"; roles = "Todos menos Super Admin y Client (recomendado)"; corporate email = "No, rechazarlo (recomendado)".
- L3. User: "go".
- L4. RED: first run had 7 refusal tests passing for the wrong reason (the internal path refused "Empleado no encontrado."); tests now assert the exact reason, and all 10 failed before the implementation.
- L5. Independent verify approved conditionally; its probes (forged attributes, activation via core:issue-activation-code, crafted payloads, default internal form) were added and pass. Known follow-up, out of scope: EditUser "Change roles" could later grant Super Admin to an external worker (no marker distinguishes them from legacy accounts without an employee).
