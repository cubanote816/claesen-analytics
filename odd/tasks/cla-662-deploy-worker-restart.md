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
| T1 | S1–S3 | inline: `infrastructure/scripts/deploy.sh`, `docs/ai/known-risks.md` | bfe9f54 |
| T2 | S1–S4 | gentle-ai-verify (Risk: item 5) | b767ac7 (S3 warnings), 1faddcd (KEEP_RELEASES=4) |
| T3 | S4 | server diff (read-only), install on `/opt` (user, sudo) 2026-11-08 13:40, PR #42 merged 4cccfd6 | done |
| T4 | S1–S3 | Unit test pinning the order (user: "Que agregue el test estático en un PR chico.") | adf2de8 |

## Log

- **L1** (user, 2026-11-07): "soluciona el 1." Item 1 was: "El deploy se colgó en el reinicio de workers … supervisor lo iba a esperar hasta una hora con la app en mantenimiento."
- **L2** Production evidence (read-only): Runner → `sudo bash /opt/claesen/scripts/deploy.sh main` → child `sudo supervisorctl restart claesen-worker:*` for 6:48; `claesen-worker_01` (launched 16:08:26, idle, 0 CPU, SIGTERM caught) logged "waiting for claesen-worker_01 to stop" about 2025 times; `stopwaitsecs=3600`; `storage/framework/down` present the whole time.
- **L3** Simulation (stub `git`/`php`/`supervisorctl`/`sudo`, isolated APP_DIR): the old script reproduces the hang (`start` exits 7 → `restart claesen-worker:*` → never reaches `artisan up`). The new script runs `queue:restart` → `up` → `start` → scheduler `restart` → `status` and completes; with a hanging scheduler, `timeout` cuts it, prints `ADVERTENCIA`, and the deploy completes.
- **L4** T2 independent verify: S4 PASS; S1 and S2 PASS by source (`up` before every supervisorctl call; a worker paused in maintenance honours `queue:restart` and exits without reserving jobs, `Illuminate/Queue/Worker.php:258–264,405–442`), but UNVERIFIED dynamically because the verifier's scratch helper failed and it does not retry. **S3 FAIL**: worker `start` and `status` swallowed their failures with no `ADVERTENCIA`. Fixed: `start` ignores only "already started" and warns on timeout (124); `status` warns when anything is not RUNNING or it times out. Parent re-ran the simulation over six scenarios (old: hangs before `up`; new: normal, stopped workers, worker start hang, scheduler hang, FATAL worker): all complete with the expected warnings.
- **L5** Server diff: `/opt/claesen/scripts/deploy.sh` (2026-09-10) ran `supervisorctl restart` unconditionally (restart first, then start), which is the version that hung, and had `KEEP_RELEASES=4`, now carried into the repo. The production instance proposed porting "restart first" to the repo; rejected, because `queue:restart` already relaunches the workers on the new release and the scheduler keeps its `restart`.
- **L6** Rollout: the new script was installed on `/opt` with `install -m 755` (backup `/root/deploy-backup-cla662-20261108-134004`, rollback `~/cla662/rollback.sh`). PR #42 merged as 4cccfd6. Its deploy (run 42482328444), the first live run, took 43 s (PR #41: 11 min). Log: `Broadcasting queue restart signal` → `[10/10] Saliendo de mantenimiento` → `claesen-scheduler: stopped/started` → status worker_01 RUNNING, worker_00 STARTING, scheduler RUNNING → COMPLETADO, with no ADVERTENCIA. Independent verify of the live run: UNVERIFIED (the verifier has no network); the user chose to add the static test (T4).
- **L7** T4: `tests/Unit/DeployScriptOrderTest.php` — RED against the pre-CLA-662 script (3/4) and against the current one with the workers switched to `restart` (1/4); GREEN 4/4.
