# CLA-686 — Branded transactional email template (Claesen and Electro Bertels)

Linear: CLA-686 (In Progress). Branch `feat/cla-686-branded-mail`, stacked on `feat/cla-685-activation-email` (1509aaf2, PR #65), worktree `.worktrees/cla-686`.
Visual spec: `design/branded-mail/02-brand-band-header.html` (chosen); tokens: `design/system.md`.

## Specs

- S1. Every transactional mail rendered through Laravel's markdown mail layout (MailMessage notifications: account activation, password reset, sign-in code, security alerts, and any other using `resources/views/vendor/mail/html/message.blade.php`) uses the "brand band header" design: dark header band with the company logo and a brand accent stripe, white content card, brand-styled button with a dark label (WCAG AA), dark footer with the company name.
- S2. The brand is chosen per recipient company, the same way `MailBrand` already chooses sender and company name: Claesen (accent `#00aeef`) or Electro Bertels (accent `#EE7203`). Values come from `design/system.md`.
- S3. The logo is embedded in the message (inline CID attachment), never linked by URL — the backoffice is only reachable from the private network. Use the existing raster logos under `public/img/` suited to a dark band; if no suitable light-on-dark logo exists for a brand, report it instead of inventing one.
- S4. Dark mode: declare `color-scheme`/`supported-color-schemes` so clients that respect it keep the band; the logo must remain visible on the dark band.
- S5. The footer "All rights reserved." is translated to the recipient's language (nl: "Alle rechten voorbehouden.", en: "All rights reserved.").
- S6. Mail texts (subjects, lines, buttons) and recipients/senders are unchanged; plain-text versions keep working.

## Tasks

- T1. [S1-S6] Mail themes + layout/header/footer overrides + brand selection + logo embedding + footer translation + tests + docs. Route: worker. Commit: pending. Evidence (parent): Core + FieldOps suites 1052 passed / 2 failed (the 2 pre-existing local `MailBrandTest` failures).

## Log

- L1. User: "pienso que la plantilla del correo debe usar la identidad digital de la empresa o el system design"
- L2. User decisions: separate ticket; mockup first; chose "02 · Banda de color en el encabezado" for both companies; logo "Incrustado en el mail (recomendado)"; footer "Sí, traducirlo (recomendado)".
- L3. User triggered the canvas implement handoff for `branded-mail/02-brand-band-header.html`.
- L4. T1 implemented (tests first: 13 in BrandedMailTemplateTest). Deviation: one shared `themes/default.css` + brand colors inline from `MailBrand::current()` instead of per-brand theme files, so every MailMessage is covered without touching each notification; logo attached by a `MessageSending` listener because anonymous mail components get no `$message`.
