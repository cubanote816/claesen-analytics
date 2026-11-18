# CLA-705 — Electro Bertels landing: contact form + localized confirmation

Two repos: backend `feat/cla-705-bertels-contact-confirmation` (this repo) and the static landing `feat/cla-705-contact-form` (`/home/totti/electrobertels_landingpage`, clone of `cubanote816/electrobertels`).
Linear: CLA-705. Goal: the landing contact form creates a lead in the bertels panel and the visitor gets a localized acknowledgement.

## Specs

**S1 — Form submits to the intake.** User: "enlaces el formulario de contactos para que se posible el envio del mismo a su vez quede registrado en https://backoffice.claesen.local/bertels/website/consultation-requests". The landing form POSTs JSON to `POST {api}/v1/website/consultations?site=electro-bertels` (API base configurable on the form) with `name`, `email`, `message`, `locale`, honeypot `website_url`; the lead shows in the bertels panel → Website → Consultation requests.

**S2 — Form UX best practice.** Native validation with the API limits (name ≤255, email ≤255, message ≤5000, all required); hidden honeypot; button disabled while sending; `Idempotency-Key` per submission (double submit stores one lead); `aria-live` status saying the message was *received*; 422 errors inline per field; 429 message; network error fallback with `mailto:`/`tel:`; short privacy note.

**S3 — Visitor acknowledgement.** User: "cuando el user envie el formulario notificarle en su correo que se mensaje fue enviado o recibido segun las mejores practicas de la industria"; decision: "Acuse localizado nl/fr/en/de + referencia". `ConsultationConfirmationMail` renders in the request `locale` (nl/fr/en/de; otherwise the site `default_locale`; otherwise `en`), carries a reference number in subject and body, states what happens next, offers contact alternatives, a copy of the message, HTML + plain text parts, sender unchanged (`MailBrand::forSite`).

**S4 — No regression.** Existing intake and consultation mail suites stay green (CLA-699 sender/no-notice test included).

**S5 — Production rollout documented.** The landing origin is missing in `infrastructure/nginx/sbapu03/cors-map.conf`; document it. Deploy is the user's decision.

## Tasks

| ID | Specs | Route | Commit |
|----|-------|-------|--------|
| T1 | S3, S4 | inline, test-first (backend) | backend `0bb1a094` |
| T2 | S1, S2 | inline (landing) | landing `5a7a680` |
| T3 | S1–S3 | inline (verifier had no local network), local E2E | evidence L5 |
| T4 | S5 | inline, docs | backend docs commit |

## Log

**L1 — 2026-11-18, user (verbatim):** "ya he comprobado el envio de correo y el sender por defecto y funcionan bien. los necesito es que descarges este repo https://github.com/cubanote816/electrobertels.git lo copies en una carpeta con el nombre electrobertels_landingpage y enlaces el formulario de contactos para que se posible el envio del mismo a su vez quede registrado en https://backoffice.claesen.local/bertels/website/consultation-requests y cuando el user envie el formulario notificarle en su correo que se mensaje fue enviado o recibido segun las mejores practicas de la industria para esta funcionalidad"

**L2 — 2026-11-18, user decisions:** folder `/home/totti/electrobertels_landingpage`; confirmation "Acuse localizado nl/fr/en/de + referencia"; create the Linear ticket. The bertels organization/site already exists in production; locally only `claesen-verlichting` exists (create electro-bertels locally, non-destructively, for T3).

**L3 — 2026-11-18, user:** "go".

**L4 — 2026-11-18, T1 evidence:** `ConsultationConfirmationMailTest` RED 9 failed / 2 passed (the two already true: no phone line, site sender), then GREEN; intake + mail suites 57 passed (193 assertions), CLA-699 sender test included; Pint clean.

**L5 — 2026-11-18, T3 evidence:** `gentle-ai-verify` could not reach the local network (NOT VERIFIED, not retried); its review found that a retry after a network failure reused the idempotency key even after an edit, fixed in the landing (key reset on input). Inline E2E with real Chromium (landing on 127.0.0.1:8080 → API localhost:8001): empty submit → 3 field errors, 0 requests; valid submit → 201, button disabled while sending, success text, form reset, double click → 1 POST with `Idempotency-Key` and `locale: nl`; honeypot → 201, not stored. Mocked: 422 → inline email error + focus; 429 → wait text; network → tel:/mailto: links, same key on retry, new key after edit. DB: requests stored with `site=electro-bertels` (what the panel lists via `ScopedToPanelSite`), confirmation deliveries `queued`. Mail rendered to Mailpit through `smtp` (not Graph): «Wij hebben uw bericht ontvangen (CR-000001)» and «Nous avons bien reçu votre message (CR-000003)», From `Electro Bertels <info@electrobertels.be>`, HTML + text parts, tel link. Panel UI login not exercised. QA rows and Mailpit cleaned; local electro-bertels org/site kept.

**L6 — 2026-11-18, production finding:** nginx owns `/v1/` CORS in production: `cors-map.conf` lacks the electrobertels.be origins and the preflight `Access-Control-Allow-Headers` lacks `Idempotency-Key`. Documented in `known-risks.md`; not changed (deploy is the user's decision).
