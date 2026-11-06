# CLA-654 — One send per recipient, idempotent inline campaigns

Branch `fix/cla-654-multi-recipient-send` (worktree `.worktrees/cla-654`, from `origin/main` c19d692).
Blocks the first real campaign: 479 sendable prospects have 2+ addresses (prod count, 2026-11-06).

## Specs

- S1: A prospect with N email addresses produces N separate sends (`sendCampaign` called once per address, `$emails = [address]`) and N `mailing_messages` rows, each with a distinct `tracking_token`. Each send uses its own tracking token for the open pixel and click links. User decision: "¿Creo el ticket en Linear con el camino de un envío por destinatario?" → "si".
- S2: A failure for one recipient (mailer returns false or throws any `\Throwable`) is recorded as a `failed` message for that address and does not abort the remaining recipients or prospects.
- S3: Retrying the inline job (same dispatched job instance re-run) never creates a second Campaign and never resends to an address that already has a `sent` message in that campaign. Idempotency key: a `dispatchKey` generated once at dispatch (constructor), stored on `mailing_campaigns.dispatch_key` (new nullable unique column).
- S4: When the job fails definitively, `failed()` moves its campaign out of `sending` to `failed` with `finished_at`, for the inline path too (found by `dispatch_key`).
- S5: Counters stay consistent with `mailing_messages`: `sent_count`/`failed_count` count recipient messages; skip rows keep `skipped_count`.
- S6: Suppression is checked per address: a suppressed secondary address is skipped (skip row) while the other addresses are still sent. Prospect-level skips (unsubscribed prospect, category opt-out, no email) behave as today: one skip row per prospect.
- S8: Sending respects the Exchange Online per-mailbox limit of 30 messages per minute: the default `mailing.send_delay_ms` is at least 2000 and the delay applies per recipient send (not per prospect). A throttled send (HTTP 429 / mailer failure) is recorded as `failed` for that address and does not abort the run.
- S9: A campaign of any size completes although each worker run is limited (production worker `queue:work --tries=3 --max-time=3600` without `--timeout` = 60 s; redis `retry_after` 90 s). The inline job sends for at most a configurable time budget per run (default 40 s, below both limits), then re-dispatches itself with the same `dispatchKey` and returns; the chain ends when no address remains, then the campaign is completed/failed. Runs never overlap (only the finishing run dispatches the next). A retried or continued run creates no duplicate skip rows and does not re-increment counters (skip rows are idempotent per campaign + prospect + address; `sent_count`/`failed_count`/`skipped_count` are incremented per message as it is written).
- S10: Exactly one live run per inline campaign. The campaign stores a `run_generation`; each continuation carries the generation it was dispatched for. A run proceeds only when its generation equals the campaign's, and hands over by atomically advancing the generation (update ... where run_generation = N) before dispatching the successor. A replayed/stale run (older generation) exits without sending or dispatching. `failed()` only closes the campaign when its generation is still current. An inline run exits without sending when the campaign is no longer `sending`. Worst-case run time stays below the job `$timeout`, which stays below redis `retry_after` (90 s): job `$timeout` 85 s; default `run_budget_seconds` 20, measured from the start of the run and checked before every send and skip; Graph request 30 s + OAuth 15 s + delay 2 s fit inside. Known, accepted limit: a crash between Graph accepting a send and the message row being written can resend that one address on the next run.
- S7: Existing behaviour pinned: single-email prospects, A/B paths (groups, fallback, winner send `alreadySentIds`), existing-campaign path, and `ExecuteCampaignJobCounterTest` stay green.

Production evidence (2026-11-04, campaigns 21-23, failed_job 1191):
"1062 Duplicate entry '<token>' for key 'mailing_messages.mailing_messages_tracking_token_unique'" at `ExecuteCampaignJob.php:528`.

## Tasks

| ID | Specs | Route | Commit |
|----|-------|-------|--------|
| T1 | S1, S2, S5, S6 | worker, test-first | 4933860, f9ba4f6, 2dd11ab, b5cd2eb |
| T2 | S3, S4 (+ migration) | worker, test-first | 4933860, f9ba4f6, 2dd11ab, b5cd2eb |
| T3 | S7, S8, docs/handoff | worker + parent check | 4933860, f9ba4f6, 2dd11ab, b5cd2eb |
| T4 | S9 (+ S3/S5 retry idempotency of skips and counters) | worker, test-first | 4933860, f9ba4f6, 2dd11ab, b5cd2eb |
| T5 | S10 (verifier findings: timing, replay, stale failed, closed campaign, failed handover) | worker, test-first | 4933860, f9ba4f6, 2dd11ab, b5cd2eb |

## Log

- L1 (user, 2026-11-06): "vamos a implementarlo siempre que al sincronizarlos la proxima vez no se pisen los cambios CLA-654 (espera tu GO) y los bounces con 404. Y antes del 1 de diciembre hay que arreglar el emparejamiento del sync RBFA, o se vuelven a crear duplicados."
- L3 (2026-11-06): Exchange Online limits a mailbox to 30 messages/minute; per-recipient sends make ~2,700 messages, so the 500 ms delay would be throttled. Added S8.
- L2: Docker Desktop WSL integration dropped at session time (`docker` not found); tests wait for job-1. Engram unavailable (runtime contract mismatch); no mirror written.
- L4 (2026-11-06): Implemented T1-T3 in `ExecuteCampaignJob` (one send/message/token per address, per-address suppression, `dispatchKey` idempotency, `failed()` for inline path), migration `2026_11_06_000016`, default `send_delay_ms` 2000. Tests: `MultiRecipientSendTest`. Worktree test runs need `APP_BASE_PATH` + a Modules autoload override because `vendor` symlinks to the main checkout.
- L5 (parent, 2026-11-06): repo `docker/8.4/supervisord.conf` runs `queue:work --sleep=3 --tries=3 --max-time=3600` (no --timeout => 60 s) and `config/queue.php` redis `retry_after` = 90. A single long campaign job is killed at 60 s; before this fix each retry created a new campaign and resent. Added S9. Test infra fixed: the worktree now has its own vendor copy + `composer dump-autoload` (supersedes the L4 workaround); run tests with plain `php artisan test` from the worktree.
- L6 (2026-11-06): Implemented S9 in the inline path: `mailing.run_budget_seconds` (default 40), continuation job with the same `dispatchKey`, idempotent skip rows, per-message atomic counters. Addresses with a `failed` message are terminal for the campaign (no automatic resend). Follow-up: continuation for the existing-campaign/A-B path.
- L7 (2026-11-06): Implemented S10: `run_generation` column (same migration), generation-checked inline runs with atomic handover, generation-scoped `failed()`, closed-campaign exit, job `$timeout` 85, default budget 20 s measured from the start of `handle()` and checked before every send and skip write (idempotent no-op skips do not count as progress). Crash window documented in the job docblock.
- L8 (2026-11-06): Handover (generation advance + successor dispatch) now runs in one `DB::transaction`; if `dispatch()` throws, the generation rolls back and the queue retry of the same run hands over again. Dispatch stays immediate (no afterCommit).
- L9 (2026-11-06): Native review review-ef8b17dfa40787be escalated: its validator rejected f9ba4f6 (run_owner retry regression: MySQL reports 0 affected rows when `run_owner` already holds the value, so a queue retry of a claimed run exited as stale; plus missing tests). Fixed here: the claim now decides by re-reading the campaign row. Added tests for the retry, publication after commit, and a real legacy-payload deserialization. Durable recovery relies on the queue's own redelivery (`retry_after` 90 s re-runs the parent, which re-publishes an unclaimed successor). A watchdog for campaigns stuck in `sending` is a follow-up.
- L10 (parent, 2026-11-06): native review review-ef8b17dfa40787be escalated (validator rejected f9ba4f6: MySQL 0-affected-rows on an unchanged run_owner claim). Fixed in 2dd11ab. Second native review review-e29607f1396bbce4 required one correction (orphaned handover when the successor push fails on the parent's final attempt) -> b5cd2eb; targeted validator APPROVED, acknowledgement burned. Non-blocking advisories (follow-up ticket): cross-prospect address deduplication; existing-campaign and A/B paths still run unbudgeted in a single job. Final: Mailing 241 passed / 2 skipped / 0 failed.
