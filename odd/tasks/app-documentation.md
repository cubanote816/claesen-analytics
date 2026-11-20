# App documentation (epic CLA-717)

Document every production module in-app like Mailing (CLA-693), make documentation part of closing every feature, and lay the RAG foundations.
Branch: `docs/app-documentation`. Phase 0 ticket: CLA-718. Dutch text is reviewed by management.

## Specs

- S1 — "quiero comensar a documentar toda lo que esta en produccion. ya hemos hecho la de mailing." Every module that runs in production gets an in-app user guide like Mailing.
- S2 — "crear tambien una regla o skill que una ves que una funcionalidad este terminada se cree la documentacion de la misma." One canonical skill in `.agents/skills/` plus an always-on rule, referenced from the other harnesses; the Definition of Done covers every closed feature.
- S3 — "tambien queremos crear las bases para crear nuestro RAG para la documentacion o comenzar con la creacion del mismo." Now: foundations only (chunk contract, export command, eval set). Chatbot later.
- S4 — RAG v1 audience: end-user guides only.
- S5 — Module order: Core → Website (admin + Bertels) → Safety → Intelligence/BI → FieldOps (2-3 slices) → Performance/Employee/Cafca → Prospects (after its refactor). Analytics and KNX stay out.
- S6 — Production inventory `docs/ai/production-inventory.md` (module, screens, panel, live, evidence), confirmed by the user before module guides start.
- S7 — The guide system covers modules beyond Mailing: nested resources (FieldOps `Catalogs/*`), `app/Filament` clusters mapped to their module (Website), actions built in traits, guide pages on the Bertels panel.

## Tasks

| ID | Specs | Ticket | Route | Status | Commit |
| --- | --- | --- | --- | --- | --- |
| T1 | S6 | CLA-718 | inline + explore | written; waits for user confirmation | 782ce4aa |
| T2 | S7 | CLA-718 | inline, test-first | done (Bertels page registration moved to T5) | 744f51ca |
| T3 | S2 | CLA-718 | inline | done | 782ce4aa |
| T4 | S1, S5 | CLA-719 | Core guide | pending | |
| T5 | S1, S5 | CLA-720 | Website guide | pending | |
| T6 | S1, S5 | CLA-721 | Safety guide | pending | |
| T7 | S1, S5 | CLA-722 | Intelligence/BI guide | pending | |
| T8 | S1, S5 | CLA-723 | FieldOps guide | pending | |
| T9 | S1, S5 | CLA-724 | Performance/Employee/Cafca | pending | |
| T10 | S1, S5 | CLA-725 | Prospects guide (after refactor) | pending | |
| T11 | S3, S4 | CLA-726 | RAG foundations | pending | |

## Log

- L1 — 2026-11-19, user: "quiero comensar a documentar toda lo que esta en produccion. ya hemos hecho la de mailing. quieor que crees un plan para documentar la app y crear tambien una regla o skill que una ves que una funcionalidad este terminada se cree la documentacion de la misma. a aprte de la documentacion tradicional que hemos publicado para mailing tambien queremos crear las bases para crear nuestro RAG para la documentacion o comenzar con la creacion del mismo. puedes analizar la app y crear un plan"
- L2 — 2026-11-20, user on the plan's decisions: "acepto tu recomendacion. 2 me sirve. 3 de acuerdo. 4 ok. 5 gerencia. go" (1: canonical skill in `.agents/skills/`; 2: module order; 3: RAG foundations only; 4: user guides only; 5: management reviews Dutch).
- L3 — Linear: epic CLA-717; CLA-718 (Phase 0, In Progress); CLA-719..CLA-726 (Backlog).
- L4 — T1 evidence: production = `main` (every push deploys via `.github/workflows/deploy.yml` on the `prod` runner); `Deploy: success` on runs 44611632678 (PR #87, `b540fb1f`), 44532932786, 44292620336; `Verify deployment` is the known false negative.
- L5 — T2: first RED 4 failed (no `filamentRoots()`, grouped scope). Real-module check then showed trait-in-trait (`BuildsSiteSettingsSection` → `ShowsSitePublicationState`) and the factory `PublishSiteNowAction::make()` → `Action::make('publish_now')` named wrongly; second RED observed, then GREEN `tests/Feature/Documentation` 25/25, Mailing page 9/9, Mailing inventory unchanged (13). Anchors: FieldOps 137, Website+cluster 48, Core 15, Safety 11, Prospects 12, Intelligence 8, Cafca 8, Performance 4, Employee 4. Pint issues are pre-existing on HEAD.
- L6 — Decision: Bertels panel guide registration moves to T5 (CLA-720), where the first Bertels guide exists; no meaningful RED without a page.
- L7 — `.git/info/exclude` (local) ignores `/.agents/skills/`; the skill is tracked with `git add -f`.
- L8 — Slice boundary: CLA-718 = 744f51ca + 782ce4aa (546 changed lines incl. fixtures and docs) on `docs/app-documentation`; one PR. Each later ticket (CLA-719+) is its own branch and PR from `main`. `gentle_review assess` (base origin/main): risk medium, reviewDue false, no independent verifier.
- L9 — Native review (lineage review-ca82597cd41122cf, readability + reliability) found 4 candidate-caused blockers: factory name taken from the whole file instead of `make()` (R2-001, R3-002) and traits matched by short name (aliases, same-named traits; R2-002, R3-001). One bounded correction (plan 190 lines): RED observed, GREEN `tests/Feature/Documentation` 26/26; real-module anchor counts unchanged.
- L10 — The scope_changed recover could not be called (tool layer turns its JSON input into an object); inspect then offered a fresh START on the corrected candidate (lineage review-1ed8a4dc7e331fcb). It found 8 more blockers: 6 on factory detection edge cases, 2 on grouped imports. Decision: drop factory detection (back to baseline, documented as a known limit for CLA-720) and parse grouped/list imports. RED observed for the grouped import, GREEN 26/26; Website anchors 49.
