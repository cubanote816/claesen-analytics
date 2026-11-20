# CLA-729 — Migrate all Gemini calls off the retired gemini-2.5-flash

- **Ticket:** CLA-729 (In Progress, urgent). **Branch:** `fix/cla-729-gemini-model-migration`. **Worktree:** `.worktrees/cla-729` (from `origin/main` db47d4d6). Tests: `bin/wt-test`.
- **Why:** Vertex retired `gemini-2.5-flash` on 2026-10-20; every AI feature still targets it. Target: `gemini-3.5-flash-lite` on the Vertex `global` endpoint (Standard PayGo is offered in `global`, `us`, `eu`; not `us-central1`).

## Specs

User approval of the plan (verbatim): "GO". Production answer (verbatim): "No está definida" (`GEMINI_API_URL` is not set in production, so the code default is what runs). Live check (verbatim): "Sí, una llamada de prueba". Audit report (verbatim): "Sí, incluirlo en CLA-729".

- **S1** — One endpoint builder reads `services.gemini.project`, `services.gemini.location` (default `global`), `services.gemini.model` (default `gemini-3.5-flash-lite`) and `services.gemini.timeout`. Host is `aiplatform.googleapis.com` for `global` and `{location}-aiplatform.googleapis.com` otherwise. `services.gemini.url` (`GEMINI_API_URL`), when set, is used unchanged as a full override.
- **S2** — The six call sites use the builder: `GeminiService.php:19`, `GenerateBudgetSimulationJob.php:110`, `BudgetAssistantService.php:106`, `MaterialIntelligenceService.php:26`, `AuditProjectService.php:53`, `TechnicianAnalysisService.php:42`. No runtime `env()`, no hardcoded URL, project or model left in application code.
- **S3** — Payloads drop `temperature` and `topK`, set `generationConfig.thinkingConfig.thinkingLevel = "MINIMAL"`, and every request has an explicit HTTP timeout from config. Existing response parsing and error handling keep their current behavior.
- **S4** — Tests with `Http::fake` pin, for each call site, the URL it hits and the payload it sends; plus override and regional-location cases for the builder.
- **S5** — One real Vertex call with the service account confirms the model answers on `global`; only the HTTP status and the reported model version are shown.
- **S6** — Docs: env key names only (never values) in `docs/ai/commands-runbook.md`; `docs/ai/module-contracts.md`, `docs/ai/known-risks.md`, `handoff.md`; and `docs/ai/translation-system-audit.md` added to the repo.

## Tasks

| ID | Specs | Route | Status | Commit |
|---|---|---|---|---|
| T1 | S1, S4 | worker — builder + config + builder tests (RED → GREEN) | done | — |
| T2 | S2, S3, S4 | worker — six call sites + per-call-site tests (RED → GREEN); thinkingConfig only for Gemini ≥ 3 (L3) | done (16/16 Gemini tests, 71 related green) | — |
| T3 | S5 | parent — live smoke call | done (L3, L4) | — |
| T4 | all | independent verifier (risk item 5) | done (L5; S3 defect fixed) | — |
| T5 | S6 | parent — docs + handoff | done (wording fixed per L5) | — |
| T6 | all | parent — full suite, diff for technical GO, single commit | suite green (3087 passed, 4 skipped); awaiting technical GO | — |

Out of scope (follow-up ticket): source-language detection and relocation; audit findings 1–6.

Risk: item 5 (external provider / environment configuration: a wrong endpoint silently disables every AI feature).

## Log

- **L1** (2026-11-20, user, verbatim): "si Limpiar el worktree .worktrees/cla-700 y continuar con El ticket del motor Gemini"
- **L6** (2026-11-20): first full suite: 113 failed / 2974 passed / 4 skipped, all in Filament page tests (ManualAudience, MaintenanceWorkOrderFilament, StructureFilament, TerrainFilament, AudiencePreview, CampaignFormScheduledAtTimezone, …). Cause: worktree setup, not the change — the Vite build had landed in `public/` instead of `public/build/` (the four untracked `public/assets/*` + `public/manifest.json` files), so pages had no manifest. Removed those four files (byte-identical to the main checkout's build), copied the build into `public/build/`; `ManualAudienceTest|StructureFilamentTest` 34/34. Full suite rerun.
- **L5** (2026-11-20): independent `gentle-ai-verify`: S1 PASS, S2 PASS (no other Gemini caller, no runtime `env('GEMINI`), S3 FAIL — `GenerateBudgetSimulationJob` sent `"generationConfig":[]` with a 2.x override (Vertex expects an object); S4 PARTIAL (verifier could not run `bin/wt-test` from its scratch dir); S5/S6 PARTIAL (runbook said "names only" while listing defaults and claimed the override beats the timeout; module-contracts kept a GeminiService-only rule the five direct callers contradict). Fixed: RED test `test_budget_simulation_job_omits_an_empty_generation_config_for_a_2x_override_url` failed, job now omits `generationConfig` when empty → `GeminiCallSitesTest|GeminiEndpointTest` 17/17 (parent run). Docs reworded. The other three payloads always carry `response_mime_type`, so never empty. Untracked `public/assets/*` and `public/manifest.json` are not part of the ticket.
- **L4** (2026-11-20, user, verbatim): "Sí, una segunda llamada". Second call with `services.gemini.url` nulled in-process only: `gemini-3.5-flash-lite` on `global` answered with valid JSON `{"en":"Maintenance","fr":"Maintenance","de":"Wartung"}`, 24 input / 29 output tokens, 0 thinking tokens, 1 part, no error line. The HTTP-status/modelVersion line was cut by the command's own `tail`; success is shown by the parsed answer and the absent error line.
- **L3** (2026-11-20): first live call (user-authorized) resolved to `us-central1 … gemini-2.5-flash` because the dev `.env` sets `GEMINI_API_URL` (inferred from the resolved URL; `.env` not read). Vertex still accepts requests for 2.5-flash, but rejected the new payload: HTTP 400 "thinking_level is not supported by this model". Fix: `GeminiEndpoint::model()` + `thinkingConfig()` — thinkingLevel MINIMAL only for Gemini ≥ 3; override-URL tests added. Production does not set the override (user), so it uses the default 3.5-flash-lite/global.
- **L2** (2026-11-20): call-site map from a bounded grep (the explore subagent timed out). No call site sends frequency/presence penalties (they would throw on 3.x); `temperature`/`topK` are ignored by 3.x. No existing test fakes an aiplatform URL.
