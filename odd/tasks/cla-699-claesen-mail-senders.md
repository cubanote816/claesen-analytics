# CLA-699 — Claesen mail senders: backoffice@ (automatic), sport@ (campaigns), offerte@ (quotes)

Linear: CLA-699 (In Progress). Branch `feat/cla-699-claesen-mail-senders` from `origin/main` (06cb20ca), worktree `.worktrees/auth-users`.
Today Graph uses the From address as the sending mailbox, and Core automatic mails share the site sender with the Website quote mails, so Claesen cannot send automatic mail from an alias. `AGENTS.md` carries a temporary, uncommitted CLA-699 exception added by the user: never stage or commit `AGENTS.md`.

## Specs

- S1. User (L2): "el email para el envio de correo de nuestra plataforma sera backoffice@claesen-verlichting.be, sport@.. solo envia las campanas de mailing, offerte@.... es el que envia las respuesta de la consulta de presupuesto de la web y es el email de respuesta para las campanas." and (L3) "aclarar que esto aplica solo a la empresa claesen."
  - Claesen automatic platform mail is sent From the **platform sender** `config('mail.from.address')` / `config('mail.from.name')` (production sets `MAIL_FROM_ADDRESS=backoffice@claesen-verlichting.be` at rollout). This covers the Core notifications (sign-in code `VerifyEmailAuthenticationWithBrand`, `PasswordResetNotification`, `AccountActivationNotification`) for users of the Claesen site, every mail/notification that already falls back to the global sender, and the four mails of S4.
  - Claesen Website quote mails (`ConsultationConfirmationMail`, `NewConsultationRequestMail`) are sent From `offerte@claesen-verlichting.be`: set the Claesen site's `mail_from_address` with a data migration (precedent `Modules/Core/database/migrations/2026_10_04_120000_set_bertels_mail_sender.php`; `down()` restores `null`, never deletes rows).
  - Campaigns keep From `config('mailing.from_address')` and Reply-To `config('mailing.reply_to')` (production: `sport@` / `offerte@`); no campaign code change.
  - Electro Bertels is unchanged: its automatic and quote mails keep the Bertels site sender (`info@electrobertels.be`) and name.
  - No recipient changes anywhere (e.g. the internal consultation notice keeps `Site::notificationEmail()`).
- S2. Graph alias sending. User (L4): "usa el ID o UPN del buzón sport@claesen-verlichting.be en /users/{id-o-UPN}/sendMail y pon backoffice@claesen-verlichting.be en message.from.emailAddress.address. Si el código usa el mismo valor para ambos, ahí es donde hay que hacer el cambio."
  - New config `mail.mailers.microsoft-graph.mailbox_aliases` (map alias => mailbox), read from env `MICROSOFT_GRAPH_MAILBOX_ALIASES` in the format `alias@x=mailbox@x[,alias2=mailbox2]` inside `config/mail.php` only (config:cache-safe, no `env()` elsewhere); default empty.
  - `MicrosoftGraphTransport` posts to `/users/{mailbox}/sendMail` where mailbox = map[lowercased From] ?? From; `message.from` and `message.sender` keep the original From (the alias). An unmapped sender behaves exactly as today. No global fallback to sport@.
- S3. Automatic-mail notice. User (L3): "backoffice@claesen-verlichting.be este deberia advertir que son correos automaticos que no se deben responder a ellos".
  - Every Claesen automatic mail of S1/S4 shows a visible notice, translated EN and NL (recipient locale, same mechanism the mail already uses), with this meaning: "This is an automated message. Please do not reply to this email; this mailbox is not monitored."
  - The notice never appears in campaign mails, in the Website quote mails, or in any Bertels mail.
- S4. User (L5): "aceptado cambiar a backoffice@para seguir recibiendo las notificaciones". `InspectionReminderMail` (Safety), `ImmediateRiskAlertMail`, `WatchdogRiskReportMail`, `VanguardImmediateAlertMail` (Performance) stop hardcoding `hostmaster@` / `vanguard@` and use the platform sender; recipients, schedules, subjects and existing Reply-To behaviour stay as they are. Nothing is deleted or disabled.
- S5. Deploying the code alone must not break production mail: with the current production values (global `offerte@`, empty alias map) every mail still sends from an existing mailbox. The switch to backoffice@ is a separate, explicitly authorized config step (runbook in `docs/ai/production-readiness.md`), verified with one controlled external test message.
- S6. Out of scope: deleting obsolete mailboxes, changing campaign behaviour, changing Bertels, adding a Reply-To to the internal consultation notice.

## Tasks

- T1. [S2, S5] Graph alias mailbox routing in `Modules/Mailing/Mail/Transport/MicrosoftGraphTransport.php` + `config/mail.php`; unit tests (mapped alias → mailbox URL with from/sender kept; unmapped sender unchanged; case-insensitive match; empty map). Route: worker. Commit: 2226a19a.
  Evidence: RED `MicrosoftGraphTransportMailboxAliasTest` 4 failed / 2 passed (the 2 passing are the preserve cases: unmapped sender, empty map); GREEN `--filter=MicrosoftGraphTransport` 8 passed (26 assertions), incl. the 2 existing inline-attachment payload tests.
- T2. [S1, S3, S4, S5] Separate Claesen automatic vs quote identity (MailBrand / Core notifications, data migration, four mails), EN/NL notice, tests (Claesen vs Bertels, campaigns From/Reply-To pinned, quote mails offerte@ without notice), docs (`module-contracts.md`, `production-readiness.md` rollout + rollback, `handoff.md`, `CLAUDE.md` if a rule changes). Route: worker. Commit: pending.
  Evidence: RED `--filter=ClaesenMailSenderTest` 17 failed / 5 passed (the 5 are preserve cases: Bertels notifications, no notice leak into a Bertels send, Reply-To); quote test RED, campaign no-notice test preserve-pass. GREEN `ClaesenMailSenderTest` 22 passed (113 assertions); `bin/wt-test Modules/Core/tests/Feature Modules/Website/tests Modules/Mailing/tests Modules/Performance/tests Modules/Safety/tests` 1289 passed, 2 skipped. The first worker run timed out on that broad run; the parent re-ran it. Notice rendering only asserted as text (not visually checked).

## Log

- L1. User: "ok como lo dejamos listo en prod" (after the sender discussion); alias confirmed: backoffice@ is an alias of the sport@ mailbox ("como alia", "ha sport@"); Exchange "Turn on sending from aliases" enabled by the user (no runtime evidence yet).
- L2. User: "el email para el envio de correo de nuestra plataforma sera backoffice@claesen-verlichting.be, sport@.. solo envia las campanas de mailing, offerte@.... es el que envia las respuesta de la consulta de presupuesto de la web y es el email de respuesta para las campanas."
- L3. User: "aclarar que esto aplica solo a la empresa claesen. backoffice@claesen-verlichting.be este deberia advertir que son correos automaticos que no se deben responder a ellos"
- L4. User: "En el transporte de la webapp, usa el ID o UPN del buzón sport@claesen-verlichting.be en /users/{id-o-UPN}/sendMail y pon backoffice@claesen-verlichting.be en message.from.emailAddress.address. Si el código usa el mismo valor para ambos, ahí es donde hay que hacer el cambio." Then "go".
- L5. User first: "(hostmaster@ y vanguard@);estos no se usan descartalos"; after seeing the four live send paths: "aceptado cambiar a backoffice@para seguir recibiendo las notificaciones" (supersedes the exclusion).
- L6. User created the temporary AGENTS.md exception ("hecho", uncommitted in this worktree) and asked: "cuando termines me recueras quitarla".
- L7. T1 committed 2226a19a. T2 implemented by a worker (MailBrand::forUser uses the platform sender for the Claesen site; forSite flags quote mails; layout footer + the four custom views print `core::mail.automated_notice`; migration 2026_11_17_100000 sets the Claesen site sender to offerte@); docs: module-contracts, production-readiness runbook, handoff row.
