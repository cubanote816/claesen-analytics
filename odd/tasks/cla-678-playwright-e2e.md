# CLA-678 — Playwright end-to-end tests in the repository

Linear: CLA-678 (In Progress). Branch `build/cla-678-playwright-e2e` from `origin/main` (2e543e88), worktree `.worktrees/cla-678`.
Add Playwright as the repo's browser test runner with one smoke spec; CI and authenticated flows are later decisions.

## Specs

- S1. `@playwright/test` as a dev dependency, `playwright.config.ts` (base URL from `E2E_BASE_URL`, default `http://localhost:8001`), specs under `tests/e2e/`, npm script `test:e2e`, Playwright artifacts git-ignored.
- S2. One smoke spec that runs green locally against the Sail app (login page renders).
- S3. A short runbook entry (how to install the browser and run the specs).
- S4. Not wired into CI; no authenticated flows yet.

## Tasks

- T1. [S1-S4] package.json/lock, config, .gitignore, smoke spec, runbook. Route: worker. Commit: pending. Evidence: `npm run test:e2e` 1 passed (Playwright 1.65.0, Chromium headless shell, no extra system deps); `npm run build` OK; `npm audit` unchanged vs main (5 pre-existing: follow-redirects, shell-quote — not from Playwright).

## Log

- L1. User: "instala playwright para que este presente de manera globar en este repo"
- L2. Plan approved: "go" (browser downloaded to ~/.cache/ms-playwright, outside the repo).
