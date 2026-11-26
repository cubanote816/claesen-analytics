# CLA-731 — Cloudflare Turnstile on the Electro Bertels contact form (per-site enforcement)

Two repos: backend `feat/cla-731-turnstile-per-site` (this repo) and the landing `feat/cla-731-turnstile` (`/home/totti/electrobertels_landingpage`).
Linear: CLA-731. Complements the honeypot, the rate limits and the CLA-705/706 guards; replaces none of them.

## Specs

**S1 — Per-site enforcement.** Turnstile is verified only when the global switch is on (`services.turnstile.enforce` + `secret_key`, unchanged) AND the resolved site is listed in `website.intake_hardening.turnstile_required_site_keys` (default `['electro-bertels']`, same pattern as `consent_version_required_site_keys`). Claesen (not listed) never needs a token, on `/consultations` and `/contact-email`. Missing or invalid token on an enforced site → 422 on `turnstile_token`, nothing stored, attempt recorded; the existing fail-open on a Cloudflare network error stays.

**S2 — Landing widget.** The form renders Cloudflare Turnstile (managed, shown only when interaction is needed) when it carries a site key (`data-turnstile-sitekey`); the token is sent as `turnstile_token`; the widget is reset after every attempt (tokens are single use). No site key → no widget, current behaviour. Widget that cannot load, or a 422 on `turnstile_token` → a clear message with the phone/e-mail fallback. On localhost only, `?turnstile=<sitekey>` overrides the key for testing.

**S3 — Privacy and docs.** The privacy note mentions Cloudflare Turnstile. OpenAPI, module contracts and the deploy runbook (the user creates the widget for electrobertels.be/www, puts the secret in the production `.env`, enables enforcement only after the landing ships the widget) are updated.

## Tasks

| ID | Specs | Route | Commit |
|----|-------|-------|--------|
| T1 | S1 | inline, test-first (backend) | backend `c723bf9e` |
| T2 | S2 | inline (landing) + real-browser check with Cloudflare test keys | landing `933264c` |
| T3 | S3 | inline, docs | backend docs commit |

## Log

**L1 — 2026-11-20, user (verbatim):** "antes de respondertela me prunto su el campo trampo lo sustituminos por una implementacion mas profesional siguiendo los standart de la industria" — recommendation given: keep the honeypot and add Cloudflare Turnstile (already supported, disabled), per site.

**L2 — 2026-11-20, user (verbatim):** "crealo e implemntalo"

**L3 — 2026-11-20, T1 evidence:** existing support mapped: `IntakeSpamGuard::assertTurnstilePasses` with a GLOBAL enforce flag (fail-open on network errors, 422 on `turnstile_token`). `TurnstilePerSiteTest` RED 2/7 (Claesen and an unlisted site were required to send a token), GREEN 7/7; the 4 enforced tests of `IntakeHardeningTest` now opt the resolved site in explicitly. Intake suites 62 passed (224 assertions); Pint clean.

**L4 — 2026-11-20, T2 evidence:** temporary second app on :5173 inside the Sail container with `TURNSTILE_SECRET_KEY` (Cloudflare test secrets) and `TURNSTILE_ENFORCE=true` as process env (the `.env` was not read or touched): Bertels without token → 422, Claesen without token → 201. Real Chromium with Cloudflare test site keys: (A) no site key → Cloudflare never requested, no token, 201; (B) passing key, two submissions → two 201 with `XXXX.DUMMY.TOKEN.XXXX` (reset works); (C) blocking key → no POST, fallback links; (E) script blocked → no POST, fallback; (F) enforced backend, landing without key → 422 → fallback (why the deploy order matters); (D) failing secret → 422 → fallback. Local QA rows, deliveries and spam attempts deleted; temporary servers stopped.

**L5 — 2026-11-20, independent verification (risk item 2 escalated): S1/S2/S3 FAIL**, all accepted and fixed:
- S1, pre-existing in `IntakeSpamGuard`: `(bool) json('success')` let a string "false" pass, the HTTP status was ignored, any `Throwable` failed open, and the log carried the raw exception message. Now only a boolean `success: true` in a 2xx answer verifies; `ConnectionException` or 5xx fail open; any other error rejects; logs carry the exception class only. RED 3/10, then intake suites 65 passed (`1b67eef7`).
- S2: a reset kept the failed flag (a retry short-circuited), and a stale token could be sent. A reset of a live widget now clears the failure, and a token older than 290 s is never sent (reset and wait). JS/CSS loaded with `?v=cla731`. Real Chromium: A–F unchanged, D unchanged, new G (clock advanced 295 s) logs `reset → fetch` and gets 201 (landing `b3189eb`).
- S3: the runbook published only `index.html` and treated a 201 as proof; now it publishes the three files, requires checking in DevTools that the POST carries a non-empty `turnstile_token` before enabling, and the `curl` example sends JSON headers. OpenAPI `/contact-email` declares its request body with `turnstile_token`.
Not a defect: a POST without `site` through another public route files under Claesen, which is not listed by design.

