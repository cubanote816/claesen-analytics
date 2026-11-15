# CLA-688 — Project display name (id · project name · client)

CAFCA `project.name` holds the client name (~95%); the real project name is `project.descr` (CLA-173, 50fc4eb9).
Backoffice screens still read `name`. Branch `feat/cla-688-project-display-name`. Linear CLA-688.

## Specs

- **S1** — Billing Control: "debemos mostrar id del proyecto nombre del proyecto y cliente". Every alert with a project shows the project id, the project name (`descr`, falling back to `name` when `descr` is empty) and the client (mirror relation name, never `project.name`). Applies to the table (all sections), the detail modal and the alert recommendation text/evidence. Old persisted alerts also show the project name (rendered live from the mirror).
- **S2** — "el alcance que abarque a los demás ya que es el mismo error solución muy similar": the same rule applies to the other places that present `project.name` as the project name (Project Intelligence Detail, Performance project insights / cash-flow watchdog / employee performance, Safety compliance, Intelligence AI prompts).
- **S3** — "pero dejarlo documentado": document the rule so new screens follow it (module contracts + handoff).

## Tasks

| ID | Specs | Route | Status | Commit |
|----|-------|-------|--------|--------|
| T1 | S1,S2 | inline | done | 060077c4 | `MirrorProject::display_name` accessor (descr ?: name ?: id) + test |
| T2 | S1 | inline | done | 060077c4 | Billing Control page/table/modal show id · name · client + render test |
| T3 | S1 | inline | done | 060077c4 | Guardian service texts + `project_name`/`client_name` evidence for all alert types + tests |
| T4 | S2 | worker | done | 87807439 | Project Intelligence Detail title = project name, subtitle = client |
| T5 | S2 | worker+inline | done | 87807439 | Performance: ProjectInsightResource, CashFlowWatchdog, EmployeePerformance, AnalyzeEmployeeJob, ProjectStrategy, PopulateProjectInsights |
| T6 | S2 | worker | done | 87807439 | Safety ComplianceService project_name + Intelligence AI prompts (MaterialIntelligence, ProjectSimilarity) |
| T7 | S3 | inline | done | (this commit) | Docs: module-contracts rule + handoff entry |

## Log

- **L1** (user, 2026-11-15): "el gerente se queja de que puede ver el nombrede  cliente pero no el nombre del proyecto, ese problema ya lo tubimos en safety y lo solucionamos antes de implementar investiga dodne tenemos ese problema y propon un plan para su solucion debemos mostrar id del proyecto nombre del proyecto y cliente"
- **L2** (user, 2026-11-15): "el alcance que avarque a laos demas ya que es el mismo error solucion muy similar pero dejarlo docmentado, tienes mi go"
- **L4** (evidence): second root cause — `MonthlyBillingControlPage::getProjectContext()` merged two Eloquent collections loaded without `id`; Eloquent merges by primary key, so the table never showed project name or client (matches the manager's screenshot). Fixed with `toBase()->merge()`.
- **L5** (slice): slice 1 = 060077c4 (T1–T3, ~411 lines). T4–T6 go in slice 2, T7 docs in slice 3.
- **L3** (evidence): CLA-173 commit message — "project.descr in SQL Server is the real project name (127/127 active projects populated). project.name equals the client name in 95% of cases".
- **L6** (evidence): slice 2 = 87807439 (T4–T6). Suites: Intelligence 207, Performance 7, Safety 120, Employee 43 passed. Not covered by tests (Cafca sqlsrv models): ProjectInsightResource, AnalyzeEmployeeJob, ProjectStrategyService — `php -l` + review only. Safety compliance API: `project_name` value now the real project name, `client_name` added (additive). Follow-up noted: ProjectSimilarityService ranks by `name LIKE` (client), left as is.
