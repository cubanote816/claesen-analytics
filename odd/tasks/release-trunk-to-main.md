# Release: electrobertels/trunk → main (production)

Merging to `main` deploys to `prod-priv-01` (`.github/workflows/deploy.yml`, push to main). This document tracks the preparation. Steps 1–2 are authorized (L3); steps 3–4 are not yet.

## Specs

**S1 — KNX behind a flag.** Proposal approved (L3): "Flag de KNX apagado por defecto, con test." Rationale given to the user: "ocultar el link no alcanza. `Modules/Knx` ya está dentro de `trunk`, así que a producción irían sus tablas, sus rutas y su API aunque el menú no lo muestre: cualquiera que conozca la URL llega. Lo correcto es un flag (por ejemplo `KNX_ENABLED`, apagado por defecto en producción) que oculte el link **y** bloquee las rutas." Original user ask (L1): "oculta antes el link de knx ya que aun no esta listo para produccion".

**S2 — Draft PR for CI.** Proposal approved (L3): "Abrir un PR `trunk → main` en draft, solo para que corra el CI. No dispara deploy."

**S3 — Migrations and deploy checklist (not yet authorized).** "Probar las migraciones sobre una copia de la base y armar la checklist de deploy (backup, comandos post-deploy, `.env` de Gemini)."

**S4 — Merge (not yet authorized).** "Recién con eso en verde, el merge."

## Tasks

| ID | Specs | Route | Commit |
|----|-------|-------|--------|
| T1 | S1 | worker: KNX feature flag (link + routes), tests | — |
| T2 | S2 | parent: push trunk, open draft PR trunk → main, watch CI | — |

## Log

**L1 — 2026-10-24, user (verbatim):** "una pregunta ya podemos hacer un merge a la rama main para subirlo a produccion? si es si oculta antes el link de knx ya que aun no esta listo para produccion"

**L2 — 2026-10-24, parent answer (evidence):** not yet. CI never ran on trunk (`tests.yml` runs only on push to main and on pull_request; `gh run list --branch electrobertels/trunk` empty); trunk is 242 commits / 56 migrations ahead of `origin/main`; no staging (CLA-530/531) and the deploy "Verify deployment" step fails since 2026-09-22; post-deploy commands needed (`website:migrate-media-to-private-disk`, `core:backfill-user-organizations`); CLA-576/577 reminders (production `GEMINI_API_URL` still `us-central1`, Claude vision direct API); the `main` checkout has 5 uncommitted superseded CLA-601 login files; `origin/main` and local `main` are both contained in trunk.

**L3 — 2026-10-24, user (verbatim):** "adelante comienza" — on the proposal: "1. Flag de KNX apagado por defecto, con test. 2. Abrir un PR `trunk → main` en draft, solo para que corra el CI. No dispara deploy. 3. Probar las migraciones sobre una copia de la base y armar la checklist de deploy (backup, comandos post-deploy, `.env` de Gemini). 4. Recién con eso en verde, el merge." with the question "¿Arranco con los pasos 1 y 2?"
