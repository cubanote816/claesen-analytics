# CLA-705 — Electro Bertels landing: contact form + localized confirmation

Two repos: backend `feat/cla-705-bertels-contact-confirmation` (this repo) and the static landing `feat/cla-705-contact-form` (`/home/totti/electrobertels_landingpage`, clone of `cubanote816/electrobertels`).
Linear: CLA-705. Goal: the landing contact form creates a lead in the bertels panel and the visitor gets a localized acknowledgement.

## Specs

**S1 — Form submits to the intake.** User: "enlaces el formulario de contactos para que se posible el envio del mismo a su vez quede registrado en https://backoffice.claesen.local/bertels/website/consultation-requests". The landing form POSTs JSON to `POST {api}/v1/website/consultations?site=electro-bertels` (API base configurable on the form) with `name`, `email`, `message`, `locale`, honeypot `website_url`; the lead shows in the bertels panel → Website → Consultation requests.

**S2 — Form UX best practice.** Native validation with the API limits (name ≤255, email ≤255, message ≤5000, all required); hidden honeypot; button disabled while sending; `Idempotency-Key` per submission (double submit stores one lead); `aria-live` status saying the message was *received*; 422 errors inline per field; 429 message; network error fallback with `mailto:`/`tel:`; short privacy note.

**S3 — Visitor acknowledgement.** User: "cuando el user envie el formulario notificarle en su correo que se mensaje fue enviado o recibido segun las mejores practicas de la industria"; decision: "Acuse localizado nl/fr/en/de + referencia". `ConsultationConfirmationMail` renders in the request `locale` (nl/fr/en/de; otherwise the site `default_locale`; otherwise `en`), carries a reference number in subject and body, states what happens next, offers contact alternatives, a copy of the message, HTML + plain text parts, sender unchanged (`MailBrand::forSite`).

**S4 — No regression.** Existing intake and consultation mail suites stay green (CLA-699 sender/no-notice test included).

**S5 — Production rollout documented (rewritten after L7).** `backend.claesen-verlichting.be` is retired and `backoffice.claesen.local` is private; public frontends reach Laravel through the existing tunnel. No CORS change and no revival of the old vhost. The deploy order, backup, `nginx -t`, rollback and the external checks are documented; deploy is the user's decision.

**S6 — Fail closed on an unresolved site (L8).** "Sumá el guard solo a POST /v1/website/consultations": when the request names a site (`?site=`) and Laravel does not resolve that active site, respond 503 before storing, queueing or replaying an idempotent response; never log the raw client value. Without `?site=` the Claesen flow is unchanged.

**S7 — Same-origin intake on the landing (L8).** The landing posts to the relative `/intake/consultations`; nginx on the landing vhost forwards only `POST` of that exact path through the tunnel to `/v1/website/consultations?site=electro-bertels`, drops visitor query args, cookies and Authorization, replaces `X-Forwarded-For` with the client address, and caps the body. Versioned in the repo and exercised on a temporary local nginx, never on the live config.

## Tasks

| ID | Specs | Route | Commit |
|----|-------|-------|--------|
| T1 | S3, S4 | inline, test-first (backend) | backend `0bb1a094` |
| T2 | S1, S2 | inline (landing) | landing `5a7a680` |
| T3 | S1–S3 | inline (verifier had no local network), local E2E | evidence L5 |
| T4 | S5 | inline, docs | backend `45c9a57a` (CORS plan superseded by T8) |
| T5 | S6 | inline, test-first (backend guard) | backend `eb317147` |
| T6 | S7 | inline (landing URL) | landing `510b44a` |
| T7 | S7 | inline, nginx snippet + temporary local nginx | backend docs/infra commit |
| T8 | S5 | inline, docs rewrite (known-risks, handoff, readiness) | backend docs/infra commit |

## Log

**L1 — 2026-11-18, user (verbatim):** "ya he comprobado el envio de correo y el sender por defecto y funcionan bien. los necesito es que descarges este repo https://github.com/cubanote816/electrobertels.git lo copies en una carpeta con el nombre electrobertels_landingpage y enlaces el formulario de contactos para que se posible el envio del mismo a su vez quede registrado en https://backoffice.claesen.local/bertels/website/consultation-requests y cuando el user envie el formulario notificarle en su correo que se mensaje fue enviado o recibido segun las mejores practicas de la industria para esta funcionalidad"

**L2 — 2026-11-18, user decisions:** folder `/home/totti/electrobertels_landingpage`; confirmation "Acuse localizado nl/fr/en/de + referencia"; create the Linear ticket. The bertels organization/site already exists in production; locally only `claesen-verlichting` exists (create electro-bertels locally, non-destructively, for T3).

**L3 — 2026-11-18, user:** "go".

**L4 — 2026-11-18, T1 evidence:** `ConsultationConfirmationMailTest` RED 9 failed / 2 passed (the two already true: no phone line, site sender), then GREEN; intake + mail suites 57 passed (193 assertions), CLA-699 sender test included; Pint clean.

**L5 — 2026-11-18, T3 evidence:** `gentle-ai-verify` could not reach the local network (NOT VERIFIED, not retried); its review found that a retry after a network failure reused the idempotency key even after an edit, fixed in the landing (key reset on input). Inline E2E with real Chromium (landing on 127.0.0.1:8080 → API localhost:8001): empty submit → 3 field errors, 0 requests; valid submit → 201, button disabled while sending, success text, form reset, double click → 1 POST with `Idempotency-Key` and `locale: nl`; honeypot → 201, not stored. Mocked: 422 → inline email error + focus; 429 → wait text; network → tel:/mailto: links, same key on retry, new key after edit. DB: requests stored with `site=electro-bertels` (what the panel lists via `ScopedToPanelSite`), confirmation deliveries `queued`. Mail rendered to Mailpit through `smtp` (not Graph): «Wij hebben uw bericht ontvangen (CR-000001)» and «Nous avons bien reçu votre message (CR-000003)», From `Electro Bertels <info@electrobertels.be>`, HTML + text parts, tel link. Panel UI login not exercised. QA rows and Mailpit cleaned; local electro-bertels org/site kept.

**L6 — 2026-11-18, production finding:** nginx owns `/v1/` CORS in production: `cors-map.conf` lacks the electrobertels.be origins and the preflight `Access-Control-Allow-Headers` lacks `Idempotency-Key`. Documented in `known-risks.md`; not changed (deploy is the user's decision).

**L7 — 2026-11-19, user (verbatim):** "algo a tener en cuenta que creo que no has tenido el ya no usaos backend.claesen... ahora es https://backoffice.claesen.local/ y no tiene alcanse desde internet solo es visible en nuestra red local ademas se comunica con las app de forntend que si estan en internet y son publicas a traves de un tunel." Effect: S5 rewritten; the CORS plan of L6 is superseded.

**L8 — 2026-11-19, user-relayed read-only production diagnosis:** route `service.claesen-verlichting.be` (sbapu03) → `proxy_pass https://127.0.0.1:9443` (tunnel) → prod-priv-01 vhost `backoffice.claesen.local`, which already allows `127.0.0.1` on `/v1/website`; Laravel trusts `X-Forwarded-For` only from `127.0.0.1`; `electro-bertels` active; `contact_consent_version` not set in production; sender and notice recipient are `@electrobertels.be`. Agreed: same-origin `/intake/consultations` on the landing vhost, plus a fail-closed guard because `ResolveRequestSite` falls back to Claesen when the named site is not active.

**L9 — 2026-11-19, user (verbatim):** "vamos a organizarnos mejor dime si ya tienes todo lo necesario para terminar la implementacion? si es si hacerlo y luego hacemos el deploy y probamos en produccion"

**L10 — 2026-11-19, user (verbatim):** "la landing page esta en otro servidor desplegado en produccion 192.168.60.10". Effect: the nginx upstream is host-specific (`intake-upstream.conf`); the deploy must confirm how that host reaches the backoffice and that Laravel sees the visitor IP.

**L11 — 2026-11-19, T5–T7 evidence:** guard RED 3 failed / 2 passed (suspended and unknown `?site=` were 201 under Claesen), GREEN 5/5. Intake, mail, contract, route-snapshot and OpenAPI suites 74 passed (293 assertions). Route baseline regenerated: one additive middleware entry. Temporary `nginx:alpine` with the versioned snippets and a mock TLS backoffice on 127.0.0.1:9443: GET/OPTIONS/PUT 403; POST with `?site=claesen-verlichting&x=1`, spoofed XFF, Cookie and Authorization reached the upstream as `/v1/website/consultations?site=electro-bertels`, Host/SNI `backoffice.claesen.local`, XFF = client address, no cookie or authorization, `Idempotency-Key` kept, upstream `Set-Cookie` hidden; 70 kB body 413; `/v1/website/*`, `/api/user`, `/bertels/...`, `/admin`, `/intake/other` 404; 8 rapid POSTs from one client 6×201 then 2×429, a second client 201. Chromium through that nginx: relative URL, 201, success state. Slice note: the backend branch passes the ~400-line slice; review it commit by commit (`0bb1a094` mail, `eb317147` guard, docs/infra).

**L12 — 2026-11-19, independent verification (risk item 2 escalated):** `gentle-ai-verify` S6 FAIL, S7 NOT VERIFIED (static review consistent; nginx run not repeated; tests not run in its sandbox). Fixed with RED first (2 new tests, 201 instead of 503): a present-but-blank `site` passed as omitted and fell back to Claesen; an explicitly named suspended Claesen passed because the fallback does not check status. Guard now keys on the parameter's presence and requires an active resolved site (`83a451c0`).

**L13 — 2026-11-19, re-verification S6 FAIL:** blank/suspended fixes accepted; still open: (a) repeated `site` params (PHP keeps the last one) silently discard an earlier selector — fixed, RED 1 (201) → guard counts selectors on the raw query string and rejects more than one; IntakeSiteGuardTest 8/8, intake suites 77 passed (302 assertions). (b) `site[]=x` makes the shared `ResolveRequestSite` return 500 before the guard runs — no data path, unreachable through the landing proxy (it replaces the query), but it is not the 503 of S6; needs the user's decision because the fix touches shared code. Accepted follow-ups: `/contact-email` unguarded (out of scope), idempotency cache not site-scoped (pre-existing).

**L14 — 2026-11-19, user decision on (b):** "Dejarlo así y abrir ticket aparte (recomendado)". Follow-up CLA-706 (malformed `site` → 400 on every public route; also lists `/contact-email` without the guard and the idempotency cache not site-scoped). S6 closes with that documented exception; implementation complete, deploy pending.

