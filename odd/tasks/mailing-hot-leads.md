# CLA-709 — Mailing hot leads

Rank the prospects who reacted best to mailing campaigns and have not contacted us yet, so management can follow up. Epic CLA-709, sub-issues CLA-710 (T0) to CLA-715 (T5). Branch per ticket from `origin/main`; this task's branch is `feat/cla-711-prospect-contact-log`.

## Specs

- **S1** (T0, CLA-710) — Read-only audit of the first campaign before any scoring code: messages, opens, clicks, IP spread, scanner-like clicks, clicked links, prospects per signal level. Results share no e-mail addresses and no IPs.
- **S2** (T1, CLA-711) — "Already contacted" is marked manually by a system user: "por ahora que sea el usuario del sys que lo marque manual". Each mark is a history entry on the prospect: date (not in the future), direction (inbound: the prospect contacted us; outbound: we contacted them), method (phone, email, in person, website form, other), optional note (max 1000 characters), and the user who logged it.
- **S3** (T1) — Who may log and remove contact entries: option "b" — management uses the `admin` role, so `super_admin` and `admin` may; other roles may not. `technician` keeps having no access to Prospects.
- **S4** (T1) — The Prospects list shows the last contact date (sortable) and a "Contacted / Not contacted" filter; the View modal shows the contact history, newest first. Labels in en, nl and fr.
- **S5** (T1) — Removing an entry needs confirmation and leaves the prospect's other entries untouched. The existing View, Edit, Delete and "Start Mailing Campaign" actions keep working.
- **S6** (T2, CLA-712) — Deterministic, explainable engagement score; weights, decay and bot rules in config; AI is not the scorer.
- **S7** (T3, CLA-713) — Hot leads page for management (`admin`), excluding contacted, unsubscribed, suppressed, bounced and complained prospects.
- **S8** (T4, CLA-714) — AI provider and model changeable without code changes: "que sea facil de cambiar de IA y modelo sin afectar al codigo". Gemini by default.
- **S9** (T5, CLA-715) — On-demand AI summary, next step and draft follow-up mail; nothing is sent automatically.

## Tasks

| ID | Specs | Route | Commit |
|---|---|---|---|
| T0 | S1 | inline, read-only SQL validated in a throwaway MySQL 8.4; posted to CLA-710 | — (no code) |
| T1 | S2–S5 | gentle-ai-worker (context backstop) + parent review: Prospects migration, model, enums, policy, resource, lang, tests, docs | 64ce59ea |
| T2 | S6 | after T0 results | pending |
| T3 | S7 | after T1, T2 | pending |
| T4 | S8 | independent | pending |
| T5 | S9 | after T3, T4 | pending |

## Log

- **L1** (user, 2026-11-19): "quiero analisar con AI las campanas con el objetivo de encontrar los prospectos que mejor reacionaron a ella y aun no se han comunicado con nosotros para poder darle seguimiento. proponme un plan y estrategia segun las mejores practicas de la industria tanto de marketing como de desarrollo"
- **L2** (user): "1 por ahora que sea el usuario del sys que lo marque manual. 2 la gerencia. 3 ya tenemos una semana de nuestra primera campana con 150 prospecto. 4 de acuerdo pero que sea facil de cambiar de IA y modelo sin afectar al codigo"
- **L3** (user): "go" — create the epic and tickets, start T0. Linear CLA-709 to CLA-715 created; the blockedBy chain T3→T4 is ordering only.
- **L4** T0: 11 queries validated in `mysql:8.4` (exit 0, expected results on synthetic rows) and posted to CLA-710. Waiting for the user to run them in production.
- **L5** (user): "go" — prepare the T1 plan. Plan presented; role question asked.
- **L6** (user): "b y go" — management uses `admin`; implement T1.
- **L8** T1 evidence: RED 17/19 before implementation (missing `ContactLog`, unresolvable actions; the 2 passing pin existing behaviour). After the parent review (Logged by label, no per-row query for `remove_contact` visibility) `bin/wt-test Modules/Prospects/tests`: 205 passed (867 assertions); Pint passes on every new file. Native assess unassessable (untracked files); plan: self-verification, no independent verifier. Risk: none (new additive table, no consumed contract changed). Slice: one commit of 818 lines (296 of them tests, 135 translations) — one behaviour unit, kept whole; T2 starts a new branch and slice.
- **L9** (user): "go" — push, PR and GO técnico for T1; start T4. Native review approved (lineage review-b7bda791b85fab13, 5 non-blocking warnings logged in CLA-711). PR #90.
- **L7** Correction to the T0 note: `trustProxies` was fixed by CLA-661 (merged 47f01dc, deployed 2026-11-07), so tracking events after that deploy store the real client IP. Q3 still confirms it for the first campaign.
