# CLA-681 — Retry transient Microsoft Graph errors (429/503) and store the real failure reason

Linear: CLA-681 (In Progress). Branch `fix/cla-681-graph-transient-retries` from `origin/main`, worktree `.worktrees/cla-681`.
Production campaign #32 lost 2 of 4 mails to transient Graph 502/503; retry only what Graph did not process and record the real reason.

## Specs

- S1. On 429/503 the Graph send is retried up to 2 times with increasing backoff, honouring `Retry-After` when present.
- S2. Retries respect the per-generation run budget (CLA-654): if the remaining budget cannot fit the wait, the message is marked failed as today instead of overrunning.
- S3. 502/504 and any other error are not retried.
- S4. `error_message` stores a stable, non-localized technical reason, e.g. `graph 503 UnknownError` (status + code, plus Graph message when present), never secrets.
- S5. Existing behavior otherwise unchanged (counts, final status, notifications).

## Tasks

- T1. [S1-S5] Graph send retry + structured failure reason through the mailer to the job, tests. Route: worker. Commit: pending. Evidence: RED (503→202 single call; 502 localized text); GREEN `GraphTransientRetryTest` 17 passed; Mailing suite 372 passed / 2 skipped. Native review review-2d5add8c44811fcd approved after one bounded correction (R4); advisory R3-001 (WARNING, non-blocking).

## Log

- L1. Production evidence (user, 2026-11-12): "lanze una campana en produccion y de 4 prospecto solo envio a 2" — log: `sendMail failed {"status":502,...,"code":"UnknownError"}` and `{"status":503,...}`.
- L2. User decisions: "No reintentar 502/504 (recomendado)"; "Puntos 1+2 y 3, dos tickets (recomendado)"; "Reenviar a los fallidos": "Más adelante (recomendado)".
- L3. Independent verify: (P1) runs without a deadline (existing campaigns, A/B) could spend up to ~94 s on one message (1 + 2 attempts × 30 s), beyond the 85 s job timeout, with a duplicate-send window on redelivery — now retries stop after `mailing.graph_retry_window_seconds` (20) when no deadline is set; (P2) HTTP-date `Retry-After` was ignored — now parsed and capped at 10 s. Three regression tests added (after the fixes).
- L4. Native review (lineage review-2d5add8c44811fcd) finding R4 (resilience, CRITICAL): the L3 fallback window restarted per message, so five 429s with Retry-After 9 s could still push an existing-campaign run past 85 s. Correction: `ExecuteCampaignJob` sets one run-wide retry deadline (`startedAt + mailing.graph_retry_window_seconds`) for runs without a send budget; the per-message window remains only for non-job callers. RED `test_retry_window_is_shared_across_the_run` (4 calls vs 3) before the fix.
