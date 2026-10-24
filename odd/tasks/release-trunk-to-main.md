# Release: electrobertels/trunk → main (production)

Merging to `main` deploys to `prod-priv-01` (`.github/workflows/deploy.yml`, push to main). This document tracks the preparation. Steps 1–2 are authorized (L3); steps 3–4 are not yet.

## Specs

**S1 — KNX behind a flag.** Proposal approved (L3): "Flag de KNX apagado por defecto, con test." Rationale given to the user: "ocultar el link no alcanza. `Modules/Knx` ya está dentro de `trunk`, así que a producción irían sus tablas, sus rutas y su API aunque el menú no lo muestre: cualquiera que conozca la URL llega. Lo correcto es un flag (por ejemplo `KNX_ENABLED`, apagado por defecto en producción) que oculte el link **y** bloquee las rutas." Original user ask (L1): "oculta antes el link de knx ya que aun no esta listo para produccion".

**S2 — Draft PR for CI.** Proposal approved (L3): "Abrir un PR `trunk → main` en draft, solo para que corra el CI. No dispara deploy."

**S2b — Dependency security fixes.** Parent proposal approved (L7): "tratarlo como **un ticket aparte, antes del merge**: resolver el conflicto de Filament, actualizar estos paquetes, correr la suite completa y hacer QA del panel." Scope: the 7 `composer audit` advisories of CI run `40112299269` (L5): `filament/filament`, `laravel/framework`, `league/commonmark` (×2), `livewire/livewire`, `symfony/http-foundation`, `symfony/yaml`. Done when `composer audit` is clean (or every remaining advisory has a documented, user-accepted reason) and the suite and panel QA are green.

**S3 — Migrations and deploy checklist (not yet authorized).** "Probar las migraciones sobre una copia de la base y armar la checklist de deploy (backup, comandos post-deploy, `.env` de Gemini)."

**S4 — Merge (not yet authorized).** "Recién con eso en verde, el merge."

## Tasks

| ID | Specs | Route | Commit |
|----|-------|-------|--------|
| T1 | S1 | worker: KNX feature flag (link + routes), tests | `122a1d9` |
| T2 | S2 | parent: push trunk, open draft PR trunk → main, watch CI | PR #29 (CI run 40112299269) |
| T3 | S2b | worker: resolve the Filament conflict, upgrade the advised packages, audit clean, suites + panel smoke; parent: CI on PR #29 | — |

## Log

**L1 — 2026-10-24, user (verbatim):** "una pregunta ya podemos hacer un merge a la rama main para subirlo a produccion? si es si oculta antes el link de knx ya que aun no esta listo para produccion"

**L2 — 2026-10-24, parent answer (evidence):** not yet. CI never ran on trunk (`tests.yml` runs only on push to main and on pull_request; `gh run list --branch electrobertels/trunk` empty); trunk is 242 commits / 56 migrations ahead of `origin/main`; no staging (CLA-530/531) and the deploy "Verify deployment" step fails since 2026-09-22; post-deploy commands needed (`website:migrate-media-to-private-disk`, `core:backfill-user-organizations`); CLA-576/577 reminders (production `GEMINI_API_URL` still `us-central1`, Claude vision direct API); the `main` checkout has 5 uncommitted superseded CLA-601 login files; `origin/main` and local `main` are both contained in trunk.

**L3 — 2026-10-24, user (verbatim):** "adelante comienza" — on the proposal: "1. Flag de KNX apagado por defecto, con test. 2. Abrir un PR `trunk → main` en draft, solo para que corra el CI. No dispara deploy. 3. Probar las migraciones sobre una copia de la base y armar la checklist de deploy (backup, comandos post-deploy, `.env` de Gemini). 4. Recién con eso en verde, el merge." with the question "¿Arranco con los pasos 1 y 2?"

**L4 — 2026-10-24, T1 evidence (worker + parent):** `config('knx.enabled')` (env `KNX_ENABLED`, default `false`) gates the 44 `/api/v1/knx/*` routes and `/bertels/knx` with a `knx.enabled` middleware that answers a plain 404 (before auth, so anonymous callers also get 404), hides the Bertels nav item, and makes the KNX error envelope stand down. No KNX schedule, Filament surface, listener, mail or command exists; `GenerateKnxExportJob` is only dispatched from a gated controller; roles and migrations untouched. Route snapshot regenerated (only the 45 KNX lines gain the middleware; count unchanged). Suite runs with `KNX_ENABLED=true` (phpunit.xml); dev `.env` gets `KNX_ENABLED=true`. RED observed (5 failed) before the gate. Tests: `KnxFeatureFlagTest` 7/7; parent re-run `KnxFeatureFlagTest` + `RouteInventorySnapshotTest` + `BertelsSidebarTest` 13/13; worker `Modules/Knx/tests` 188 passed / 1 skipped (the 1 failure was the new test's own assertion, fixed and re-run green), `ClaesenBaseline` 78/78.

**L5 — 2026-10-24, T2 evidence (parent):** trunk pushed; draft PR https://github.com/cubanote816/claesen-analytics/pull/29 (`trunk → main`, "DO NOT MERGE — CI only", supersedes #26). First CI run on the trunk tip (run `40112299269`): **PHPUnit (PHP 8.4) success — 2349 passed, 3 skipped (8939 assertions)**; **Static checks failure only at `composer audit`**: 7 advisories in 6 packages (`filament/filament`, `laravel/framework`, `league/commonmark` ×2, `livewire/livewire` — authorization bypass in `#[Authorize]`, affected `>=4.3.0,<4.4.8`, installed v4.4.2 —, `symfony/http-foundation` CVE-2026-108082 ReDoS via Cache-Control, installed v8.1.5, `symfony/yaml` CVE-2026-109413, installed v8.1.5). The same vulnerable versions are on `origin/main`, i.e. already in production; trunk does not introduce them. A `composer update --dry-run --with-all-dependencies` of those packages fails on a Filament dependency conflict (`filament/support` versions "not loaded … conflicts with another require"), so the fix is a dependency upgrade (Task Size high risk item 5), not a lock refresh — needs its own decision.

**L6 — 2026-10-24, parent proposal:** "Mi recomendación es tratarlo como **un ticket aparte, antes del merge**: resolver el conflicto de Filament, actualizar estos paquetes, correr la suite completa y hacer QA del panel." Question: "¿Arranco con ese ticket de dependencias, o preferís mergear primero y actualizar después?"

**L7 — 2026-10-24, user (verbatim):** "arranca con el ticket"

