# CLA-693 — In-app module Documentation section (pilot: Mailing)

- **Branch:** `feat/cla-693-module-documentation` (from `origin/main` 182c79fe, no upstream). Worktree: `.worktrees/growth-mailing`.
- **Linear:** CLA-693 (In Progress). Engram mirror: `odd/cla-693-module-documentation/tasks`. Tests: `bin/wt-test` (isolated DB, `APP_LOCALE=en`).

## Specs

- **S1** — "crea la documentacion del modulo" → user guide for the Mailing module.
- **S2** — "crearemos una nueva seccion en el menu que sera documentacion donde tendremo la documentacion de cada modulo con sus funcionalidad" → new navigation group **Documentation** (separate from Guides & Procedures), one page per module.
- **S3** — "con los nombre de los botones exactos" → button names resolved from the same translation keys the UI uses (`[[action:<lang key>]]`, `[[nav:<lang key>]]`); content written in en + nl (fr/de fall back to en).
- **S4** — "con link a la funcionalidad o proceso dentro de la misma docuemntacion" → stable anchors (`{#action-<name>}`), internal links and a generated table of contents.
- **S5** — "esta documentacion deberia de actualizarse automaticamente cunado se crea un cambio o nueva funcionalidad" → live labels + CI test that fails when a module Filament action has no section in every locale, a placeholder key does not resolve, or en/nl anchors differ.
- **S6** — "a futuro crearemos un RAG por modulo para complementarla" → stable anchors + frontmatter so sections can be chunked later (RAG itself out of scope).
- **S7** — Access (user decision): whoever can access the module sees its docs (Mailing: everyone except `technician`).

## Tasks

| ID | Task | Specs | Route | Commit |
|---|---|---|---|---|
| T1 | Linear ticket + feature branch | — | inline | — (CLA-693) |
| T2 | Framework: renderer, abstract page, nav group, lang keys + tests | S2–S4, S6, S7 | inline | a5c2fdc6 |
| T3 | Mailing doc page + user guide en/nl | S1, S3, S4, S7 | worker (quota-failed after writing; parent reviewed/fixed) | f78cb7c4 |
| T4 | CI coverage test (actions, keys, en/nl parity) | S5 | inline | b43bdf77 |
| T5 | Convention doc, protocol DoD, handoff, Engram, baseline snapshots | S5, S6 | inline | (this commit) |
| T6 | Complete Mailing nl keys (53 missing), Mailing screens pass the UI locale to enum labels, `[[enum:FQCN::value]]` placeholder; labels follow the UI locale even when the guide text falls back | S3 | inline | 761d531a |

## Log

- **L1** (user, verbatim): "crea la documentacion del modulo, crearemos una nueva seccion en el menu que sera documentacion donde tendremo la documentacion de cada modulo con sus funcionalidad, con los nombre de los botones exactos con link a la funcionalidad o proceso dentro de la misma docuemntacion, esta documentacion deberia de actualizarse automaticamente cunado se crea un cambio o nueva funcionalidad. a futuro crearemos un RAG por modulo para complementarla y que el user puede lacalizar la documentacion puntual rapidamente con un chatbot."
- **L2** (user decisions): language "Multi-idioma (en + nl)"; access "Quien tenga acceso al módulo"; menu "Grupo nuevo 'Documentation'"; auto-update "Etiquetas vivas + test CI".
- **L3** (user): "go". T1 done: Linear CLA-693 created In Progress; branch created.
- **L4** (evidence): T2 committed a5c2fdc6. Explorer found `fallback_locale` = nl and 53 of 155 Mailing en keys missing in nl (nl UI showed raw keys), and enum labels (`CampaignStatus`, `TemplateCategory`, `AudienceType`, `FollowUpTrigger`, `MessageEventType`) called without locale (always English).
- **L5** (user decision): "Completar nl dentro de CLA-693" → T6.
- **L6** (evidence): Tests via `bin/wt-test`: tests/Feature/Documentation 16/16, MailingDocumentationPageTest 9/9 (campaign_manager/marketer need a panel role too — `User::hasPanelAccess`; tested on top of viewer, guide says so), MailingDutchLocaleTest 2/2. Baseline snapshots regenerated with `UPDATE_BASELINE_SNAPSHOTS=1`: diff = +Documentation group, +MailingDocumentation page, +1 route `documentation/mailing` (490→491). Full suite run aborted mid-way by a Docker daemon failure (socket 500, all containers gone) — no full-suite verdict yet. RED not observed for T2 (tests written after code); T4 coverage test verified against fixture.
- **L7** (evidence): Independent verifier: S2/S4/S6/S7 pass; S1/S3/S5 needed fixes (guide facts, nl 'Nu versturen' vs real 'Nu verzenden', inventory missed named args and use-aliases) — fixed in 805532dc, native review (readability, reliability) approved and acknowledged. Docker recovered; full suite: 2799 passed, 3 skipped, 5 failed: Website ConsultationEndpoint201Test x2 + IntakeHardeningTest x2 (pre-existing on main, Engram obs 588) and Mailing InlineCampaignAudienceTest multi-generation (timing-dependent, run_budget_seconds=0; 3/3 green rerun in isolation). Pending: technical GO, Linear Done, push/PR (user decisions).
