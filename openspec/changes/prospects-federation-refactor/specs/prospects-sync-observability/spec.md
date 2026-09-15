# Prospects Sync Observability Specification

**Change**: `prospects-federation-refactor` (CLA-535) — Slice A
**Type**: Full spec (no canonical `openspec/specs/prospects-sync-observability/spec.md` exists yet)
**Findings addressed**: proposal findings 6, 7, 8, 9, 10, 16 (+ 11 as mitigation-only log)

## Purpose

Sync failure semantics for the six federation commands MUST be observable and non-stuck: per-club and per-series failures are logged, job exit codes are respected, the master chain always reaches a terminal state, `records_count` reports persisted successes, and HTTP calls are hardened (timeout/retry/TLS) with consistent error granularity.

## Requirements

### Requirement: VAL per-club failures are logged, not swallowed

`SyncValClubsCommand::syncClub()` MUST NOT use an empty `catch (\Exception $e)` block. On any per-club exception the command MUST call `failSyncLog(...)` (or `logSyncEvent(..., 'error', ...)`) with the club identifier and exception message, and MUST continue processing remaining clubs.

#### Scenario: One VAL club fetch throws

- GIVEN the VAL sync runs against a source where one club detail request throws
- WHEN `syncClub()` handles the exception
- THEN a `SyncHistory` log entry of type `error` records the club and the exception message
- AND the remaining clubs in the run are still processed
- AND the sync does not report the failed club as persisted

#### Scenario: VAL failures surface in history

- GIVEN at least one club failed during a VAL sync
- WHEN the dashboard reads the last `SyncHistory` for `prospects:sync-val-clubs`
- THEN the failure is visible in the failed-syncs feed / logs instead of a clean success

### Requirement: ExecuteSyncJob respects the Artisan exit code

`ExecuteSyncJob` MUST check the return value of `Artisan::call(...)`. On a non-zero exit code the job MUST mark the sync run failed and MUST NOT send a success ("ha terminado satisfactoriamente") notification. On success the existing notification behavior is preserved.

#### Scenario: Command exits non-zero

- GIVEN an `ExecuteSyncJob` dispatches a federation command that exits non-zero
- WHEN the job finishes
- THEN the job records the failure (history marked failed via the command's `failSyncLog` or job-level handling)
- AND the triggering user receives a failure notification, not a success notification

#### Scenario: Command exits zero

- GIVEN an `ExecuteSyncJob` dispatches a command that exits 0
- WHEN the job finishes
- THEN the existing success notification behavior is unchanged

### Requirement: Master chain always reaches a terminal state

`MasterSyncJob` MUST guarantee that a failure in any `ExecuteSyncJob` results in: (a) the master `SyncHistory` row transitioning to `failed` (never stuck in `pending`/`running`), and (b) `SendMasterSyncFinishedNotificationJob` always firing (e.g. via `Bus::chain(...)->catch(...)`). The existing chain order (LBFA → AFT → Hockey → TPV → VAL → RBFA) MUST be preserved — no orchestration rewrite.

#### Scenario: A chained job throws

- GIVEN a master sync where one `ExecuteSyncJob` throws
- WHEN the chain halts
- THEN the master `SyncHistory` row is marked `failed`
- AND `SendMasterSyncFinishedNotificationJob` still fires
- AND the dashboard no longer shows an indefinitely active master; `sync_all` is re-enabled after the failure transition

#### Scenario: All jobs succeed

- GIVEN a master sync where every job succeeds
- WHEN the chain completes
- THEN the master history completes and the finished notification fires as today (no behavior change on the happy path)

### Requirement: records_count reports persisted successes

`finishSyncLog($count)` in all six commands MUST count clubs persisted via a successful `Prospect::updateOrCreate` (and location persistence), not loop iterations. Commands SHOULD additionally log an attempts-vs-persisted breakdown so partial failures are distinguishable on the dashboard.

#### Scenario: Partial persistence in one run

- GIVEN a sync processes 50 clubs and 5 fail to persist
- WHEN the sync finishes
- THEN `records_count` equals 45 (persisted), not 50 (attempts)
- AND the logs contain a breakdown showing 50 attempts / 45 persisted / 5 failed

#### Scenario: TPV quality line

- GIVEN the TPV sync finishes (existing quality line in `handle()`)
- WHEN `finishSyncLog` is called
- THEN the reported number is persisted-based (the appended quality line remains for richer context)

### Requirement: RBFA enrichment call is hardened

The per-club RBFA enrichment POST MUST include `->timeout(30)->retry(3, 5000)` (matching the discovery call's hardening pattern). A hanging enrichment call MUST NOT block the sync indefinitely.

#### Scenario: Enrichment endpoint hangs

- GIVEN the RBFA per-club enrichment POST is issued
- WHEN the endpoint does not respond within 30 seconds
- THEN the HTTP call times out and is retried up to 3 times with 5s delay
- AND a timeout that survives retries is logged as an error event for that club (not silently swallowed)

### Requirement: Consistent HTTP stacks and per-club error granularity

Commands using raw Guzzle (AFT, Hockey, TPV) MUST NOT disable TLS verification (`'verify' => false` MUST be removed) and MUST use a consistent User-Agent convention aligned with the `Http::`-based commands. The Hockey, RBFA, VAL, and LBFA per-club loops MUST log per-club errors via `logSyncEvent(..., 'error', ...)` on failure paths (matching the existing TPV pattern).

#### Scenario: Scrapers use verified TLS

- GIVEN `SyncAftClubsCommand`, `SyncHockeyClubsCommand`, or `SyncTpvClubsCommand` constructs a Guzzle client
- WHEN the client options are inspected (or a request is made through a mocked handler)
- THEN TLS verification is enabled (no `'verify' => false`)

#### Scenario: Hockey per-club scrape failure is visible

- GIVEN a hockey.be club page fetch fails during the sync loop
- WHEN the command handles the failure
- THEN a `logSyncEvent(..., 'error', ...)` entry with the club identifier exists in the SyncHistory logs

### Requirement: TPV pagination truncation is logged (mitigation-only)

When `SyncTpvClubsCommand` reaches its `1000` offset safety cap while more pages remain, the command MUST log a warning event indicating truncation. No structural pagination fix is in scope for this slice.

#### Scenario: Offset cap reached with more pages

- GIVEN the TPV pagination loop reaches the offset cap while the source still returns clubs
- WHEN the loop exits
- THEN the SyncHistory logs contain a warning that discovery was truncated at the cap

## RED test seams (strict TDD — write before implementation, co-located)

| New/extended test | Pattern reused | Covers |
|---|---|---|
| `Modules/Prospects/tests/Feature/ExecuteSyncJobTest.php` (new) | `Tests\TestCase` + `RefreshDatabase` (as `SyncDashboardGuardTest`) | exit-code requirement |
| `Modules/Prospects/tests/Feature/MasterSyncJobTest.php` (new) | `Bus::fake()` / `Queue::fake()` (as `SyncDashboardGuardTest`) | chain failure → master history failed + notification always fires |
| `Modules/Prospects/tests/Feature/SyncRbfaGraphqlCommandTest.php` (new) | `Http::fake()` with request assertions | enrichment timeout/retry options; discovery failure logging; per-club error logging |
| `Modules/Prospects/tests/Feature/SyncValClubsCommandTest.php` (new) | `Http::fake()` | swallowed-exception requirement |
| `Modules/Prospects/tests/Feature/LogsSyncEventsTest.php` (extend) | existing `StubSyncCommand` stub pattern | crash-safe flush / per-club error entries |
| `Modules/Prospects/tests/Feature/SyncTpvClubsCommandTest.php` (extend) | existing Guzzle `MockHandler` constructor injection | records_count persisted-based; truncation warning; TLS verify enabled |

Note: Hockey/AFT records_count coverage lands with their command tests introduced in Slices B/C; the persisted-count contract above applies to all six commands.

## Acceptance criteria (mapped to findings)

- Finding 6 (VAL swallow) → Requirement "VAL per-club failures are logged" + `SyncValClubsCommandTest` RED→GREEN.
- Finding 7 (exit code ignored) → Requirement "ExecuteSyncJob respects the Artisan exit code" + `ExecuteSyncJobTest`.
- Finding 8 (master chain stuck) → Requirement "Master chain always reaches a terminal state" + `MasterSyncJobTest`.
- Finding 9 (records_count) → Requirement "records_count reports persisted successes" + extended `SyncTpvClubsCommandTest`.
- Finding 10 (RBFA enrichment hang) → Requirement "RBFA enrichment call is hardened" + `SyncRbfaGraphqlCommandTest`.
- Finding 16 (mixed stacks / error granularity) → Requirement "Consistent HTTP stacks" + command tests.
- Finding 11 (TPV cap, mitigation) → Requirement "TPV pagination truncation is logged" + extended `SyncTpvClubsCommandTest`.
- Baseline preserved: 32 tests / 88 assertions stay green after this slice.