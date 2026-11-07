# CLA-662 — Deploy hangs in maintenance mode restarting queue workers

Branch `fix/cla-662-deploy-worker-restart` from `main` (47f01dc). The PR #41 deploy sat about 10 minutes in `supervisorctl restart claesen-worker:*` with the app down; `infrastructure/scripts/deploy.sh` must never keep the site in maintenance mode on a worker restart.

## Specs

- **S1** — The deploy leaves maintenance mode (`artisan up`) before any `supervisorctl` call, so a supervisor hang cannot keep the site down. User words: "soluciona el 1" (item 1: "El deploy se colgó en el reinicio de workers").
- **S2** — Queue workers are never `supervisorctl restart`ed: `queue:restart` runs before `up` (paused workers exit and supervisor relaunches them on the new release, so no job runs old code against the migrated schema), and `supervisorctl start 'claesen-worker:*'` only brings up stopped ones.
- **S3** — Every `supervisorctl` call is bounded by `timeout`; a hang prints an `ADVERTENCIA` and the deploy still completes. The scheduler still gets `restart` (it does not listen to `queue:restart`).
- **S4** — Rollout: `deploy.yml` runs the server copy `/opt/claesen/scripts/deploy.sh`, not the repo copy. The fix only takes effect once installed there, after diffing the server copy against the repo (the server ran the restart at line 86, the repo has it at line 82).

## Tasks

| ID | Specs | Route | Commit |
|---|---|---|---|
| T1 | S1–S3 | inline: `infrastructure/scripts/deploy.sh`, `docs/ai/known-risks.md` | — |
| T2 | S1–S4 | gentle-ai-verify (Risk: item 5) | — |
| T3 | S4 | server diff (read-only), install on `/opt` (user, sudo), then PR merge whose deploy is the first live run | — |

## Log

- **L1** (user, 2026-11-07): "soluciona el 1." Item 1 was: "El deploy se colgó en el reinicio de workers … supervisor lo iba a esperar hasta una hora con la app en mantenimiento."
- **L2** Production evidence (read-only): Runner → `sudo bash /opt/claesen/scripts/deploy.sh main` → child `sudo supervisorctl restart claesen-worker:*` for 6:48; `claesen-worker_01` (launched 16:08:26, idle, 0 CPU, SIGTERM caught) logged "waiting for claesen-worker_01 to stop" about 2025 times; `stopwaitsecs=3600`; `storage/framework/down` present the whole time.
- **L3** Simulation (stub `git`/`php`/`supervisorctl`/`sudo`, isolated APP_DIR): the old script reproduces the hang (`start` exits 7 → `restart claesen-worker:*` → never reaches `artisan up`). The new script runs `queue:restart` → `up` → `start` → scheduler `restart` → `status` and completes; with a hanging scheduler, `timeout` cuts it, prints `ADVERTENCIA`, and the deploy completes.
