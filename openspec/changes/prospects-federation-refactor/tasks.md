# SDD Tasks — prospects-federation-refactor (CLA-535)

**Status**: `applying`
**Phase**: `sdd-apply`
**Ticket**: CLA-535 — "Prospects: federation data-source refactor + audit fixes"
**Branch**: `audit/prospects-module`
**Module**: `Modules/Prospects`
**Inputs**: `proposal.md`, `design.md`, `specs/prospects-sync-observability/spec.md`, `specs/prospects-data-correctness/spec.md`, `specs/prospects-federation-contracts/spec.md`, `specs/prospects-aft-source/spec.md`, `specs/prospects-brussels-cadastre/spec.md`, `specs/prospects-ops-followup/spec.md`, `openspec/config.yaml`
**Execution order**: A → B → D1 → D2 → C → E → F
**Delivery**: `ask-on-risk`
**Review budget**: 400 changed lines per slice
**Baseline**: 32 tests / 88 assertions (must stay green after every slice)

---

## Finding numbering — clarification (added by CLA-544, 2026-09-18)

The 16 audit findings are numbered per `explore.md` §2. All 16 are mapped across the slice
headers below (A: 6,7,8,9,10,16 · B: 2,3,4,5,12,15 · D1: 11,13,14 · D2: 13,14 · C: 1) and all
16 are resolved in code, verified item by item on 2026-09-18.

Two of them remain **partial by design**, both documented in `docs/ai/known-risks.md`:

* **#2** — the Hockey club list is still hardcoded (~117 clubs). The test clubs were removed
  (CLA-543), but there is no discovery: the federation publishes no API.
* **#16** — Guzzle remains in the Hockey/TPV commands. The actionable half was done: TLS
  verification restored and per-club error logging added.

CLA-543's commit message (`78bbbe3`) describes its two fixes as "original findings #15 and #10
lost in the SDD renumbering". That framing is wrong and this note supersedes it:

* The Hockey **test clubs** are a sub-item of `explore.md` **#2**, not #15 (#15 is duplicated /
  magic region inference). The slice simply did not execute that sub-item.
* The `records_count` **overwrite in the master chain** is not among the original 16 at all
  (#10 is the missing timeout/retry on RBFA enrichment). It is a distinct bug found afterwards.

The accurate account is **16 findings mapped, plus 2 additional issues discovered later** — not
two findings lost in a renumbering. The fixes themselves are correct and covered by tests; only
the bookkeeping was wrong. The published commit message is left untouched on purpose: the record
is corrected here rather than by rewriting git history.

---

## Review Workload Forecast

| Field | Value |
|-------|-------|
| Estimated changed lines | ~1,900–2,400 total across 7 slices |
| 400-line budget risk | High |
| Chained PRs recommended | Yes |
| Suggested split | A → B → D1 → D2 → C → E → F (7 sequential PRs) |
| Delivery strategy | ask-on-risk |
| Chain strategy | pending |

```text
Decision needed before apply: Yes|No
Chained PRs recommended: Yes
Chain strategy: pending
400-line budget risk: High
```

> **Note**: `Decision needed before apply` is `Yes` — delivery is `ask-on-risk`. The apply phase must pause and ask the user at every slice boundary, not self-select chaining. D1 and D2 are both borderline ~350–480 lines; if either exceeds 400 at apply time, the phase must stop and propose a further split before opening the PR.

---

## Per-Slice Workload Forecast

| Slice | Est. changed lines | New files | Modified files | Test files added | Budget risk |
|---|---|---|---|---|---|
| A — Error-handling/Observability | ~300–380 | 3 (MarkMasterSyncFailedJob, 2 test files) | 10+ (6 commands, ExecuteSyncJob, MasterSyncJob, LogsSyncEvents trait, SyncDashboardPage) | 2 new, 2 extended | Medium (test-infra bootstrap for 5 untested commands inflates file count) |
| B — Data-correctness fixes | ~260–340 | 2 test files | 5 (4 commands + config/rbfa.php) | 2 new, 2 extended | Low |
| D1 — FederationDataSource + RBFA adapter | ~350–480 | 5 (contract, 2 DTOs, adapter, contract test) | 3 (ServiceProvider binding, SyncRbfaGraphqlCommand refactor, test) | 2 new, 1 extended | **High — borderline** |
| D2 — Normalization + migrations | ~350–420 | 4 (ContactType enum, ClubPersister, 2 migrations) | 4 (SyncRbfaGraphqlCommand, LeadService, test) | 2 new, 1 extended | **High — borderline** |
| C — AFTT PDF source | ~310–380 | 3 (adapter, migration, fixture PDF) | 3 (SyncAftClubsCommand refactor, ServiceProvider, composer.json) | 2 new | Medium |
| E — Brussels cadastre | ~250–320 | 3 (adapter, command, fixture CSV) | 3 (ServiceProvider, MasterSyncJob chain, SyncDashboardPage) | 2 new | Low |
| F — Ops follow-up | ~10–20 (docs only) | 0 | 2 (handoff.md, context-map.md) | 0 | None |

---

## Slice A — Error-handling / Observability (findings 6, 7, 8, 9, 10, 16)

### RED tests (write before any implementation)

- [x] Write `Modules/Prospects/tests/Feature/ExecuteSyncJobTest.php` with test `test_command_exit_non_zero_marks_job_failed`: create a test-only Command stub that exits code 1, bind it in `ExecuteSyncJob`, assert the user receives a failure notification (not success) and the history row is marked failed. <!-- sdd-owner: implementation -->
- [x] Write `Modules/Prospects/tests/Feature/ExecuteSyncJobTest.php` with test `test_command_exit_zero_preserves_success_notification`: create a Command stub that exits 0, dispatch `ExecuteSyncJob`, assert the user receives a success notification and behaviour is unchanged from existing baseline. <!-- sdd-owner: implementation -->
- [x] Write `Modules/Prospects/tests/Feature/MasterSyncJobTest.php` with test `test_chain_job_throw_marks_master_failed`: using `Bus::fake()` and a stubbed `ExecuteSyncJob` that throws, assert the master `SyncHistory` row transitions to `failed`, `SendMasterSyncFinishedNotificationJob` still fires, and the dashboard's `sync_all` is re-enabled afterwards. <!-- sdd-owner: implementation -->
- [x] Write `Modules/Prospects/tests/Feature/MasterSyncJobTest.php` with test `test_all_jobs_succeed_master_completes_normally`: using `Bus::fake()`, assert that when no job throws the master history completes with status `completed` and the finish notification fires. <!-- sdd-owner: implementation -->
- [x] Extend `Modules/Prospects/tests/Feature/LogsSyncEventsTest.php` with test `test_guardedSync_catch_marks_failed_and_rethrows`: create a `StubSyncCommandGuard` that uses `guardedSync` with a body that throws, assert `failSyncLog` is called, the history is `failed`, and the exception propagates. <!-- sdd-owner: implementation -->
- [x] Extend `Modules/Prospects/tests/Feature/LogsSyncEventsTest.php` with test `test_error_log_flushes_immediately`: call `logSyncEvent(..., 'error')` then simulate a crash, assert the error entry persisted before the crash (using `assertDatabaseHas` on `SyncHistory` logs). <!-- sdd-owner: implementation -->
- [x] Extend `Modules/Prospects/tests/Feature/LogsSyncEventsTest.php` with test `test_persisted_and_failed_counts_tracked`: call `markPersisted()` and `markFailed()` in sequence, then `finishSyncLog()`, assert `records_count` equals persisted calls and logs contain breakdown. <!-- sdd-owner: implementation -->
- [x] Extend `Modules/Prospects/tests/Feature/SyncTpvClubsCommandTest.php` with test `test_records_count_is_based_on_persisted_not_attempts`: mock one club that fails to persist and one that succeeds, assert `records_count = 1` and logs show "processed 2 | persisted 1 | failed 1". <!-- sdd-owner: implementation -->
- [x] Extend `Modules/Prospects/tests/Feature/SyncTpvClubsCommandTest.php` with test `test_tpv_pagination_cap_logs_truncation_warning`: mock enough responses to trigger the 1000-offset cap, assert a warning log entry about truncation exists in `SyncHistory`. <!-- sdd-owner: implementation -->
- [x] Extend `Modules/Prospects/tests/Feature/SyncTpvClubsCommandTest.php` with test `test_tpv_uses_verified_tls`: when the command constructs a Guzzle client, assert `verify` option is not `false` (verify the `defaults` or request options). <!-- sdd-owner: implementation -->
- [x] Write `Modules/Prospects/tests/Feature/SyncRbfaGraphqlCommandTest.php` (first pass) with test `test_rbfa_enrichment_has_timeout_and_retry`: using `Http::fake()`, run the RBFA command, assert `Http::assertSent` matches a request with `timeout: 30` and `retry: [3, 5000]` on the enrichment POST. <!-- sdd-owner: implementation -->
- [x] Write `Modules/Prospects/tests/Feature/SyncRbfaGraphqlCommandTest.php` with test `test_rbfa_enrichment_timeout_logged_as_error`: mock a hanging enrichment that throws after retries, assert a per-club `error` log entry exists in `SyncHistory`. <!-- sdd-owner: implementation -->
- [x] Write `Modules/Prospects/tests/Feature/SyncValClubsCommandTest.php` (first pass) with test `test_val_club_exception_is_logged_not_swallowed`: mock `Http::fakeSequence()` where one club detail request throws, assert `SyncHistory` contains an `error` log with the club identifier and exception message, and the remaining clubs still persist. <!-- sdd-owner: implementation -->

### Implementation tasks

- [x] Extend `Traits/LogsSyncEvents.php`: add `$persistedCount`, `$failedCount` properties; add `markPersisted()`, `markFailed()` methods; add `flushSyncLog()` method for immediate DB write; add `guardedSync(callable $body): int` template method that wraps the try/catch/finally lifecycle (start → body → finish/fail + rethrow); modify `logSyncEvent(..., 'error')` to flush immediately instead of waiting for the 5-event batch threshold. <!-- sdd-owner: implementation -->
- [x] Modify `Jobs/ExecuteSyncJob.php`: check `Artisan::call()` exit code; on non-zero, do NOT send success notification; send failure notification ("ha finalizado con errores"); throw `RuntimeException` so the chain catch fires. For zero, preserve existing success notification. <!-- sdd-owner: implementation -->
- [x] Create `Jobs/MarkMasterSyncFailedJob.php`: receives `$userId`, `$historyId`, `$errorMessage` from the chain catch; loads `SyncHistory` by id; if still `pending`/`running`, sets `status = 'failed'`, `finished_at = now()`, appends error log; sends danger notification to `$userId` ("La sincronización maestra ha fallado — cadena detenida"). <!-- sdd-owner: implementation -->
- [x] Modify `Jobs/MasterSyncJob.php`: attach a Laravel-compatible chain catch closure that dispatches `MarkMasterSyncFailedJob`. Chain order preserved (LBFA → AFT → Hockey → TPV → VAL → RBFA). <!-- sdd-owner: implementation -->
- [x] Modify `SyncValClubsCommand.php`: in the per-club exception handler, replace empty `catch (\Exception $e)` with `$this->logSyncEvent("Error: club <id>: {$e->getMessage()}", 'error', '❌')` + `$this->markFailed()`, then continue. <!-- sdd-owner: implementation -->
- [x] Modify `SyncRbfaGraphqlCommand.php` (Slice A part): add `->timeout(30)->retry(3, 5000)` to the per-club enrichment POST call. Add per-club error logging via `logSyncEvent(..., 'error')` on failure paths. <!-- sdd-owner: implementation -->
- [x] Modify `SyncHockeyClubsCommand.php` (Slice A part): add per-club error logging via `logSyncEvent(..., 'error')` on failure paths. Remove any `'verify' => false` TLS option. Add consistent User-Agent. <!-- sdd-owner: implementation -->
- [x] Modify `SyncTpvClubsCommand.php` (Slice A part): add pagination cap warning log when offset reaches 1000 ("pagination safety cap reached — results truncated"). Remove any `'verify' => false` TLS option. Convert `finishSyncLog` call to pass `$this->persistedCount` instead of the raw count. Add attempts-vs-persisted quality log before finish. <!-- sdd-owner: implementation -->
- [x] Modify `SyncLbfaClubsCommand.php` and `SyncAftClubsCommand.php` (Slice A part): add per-club error logging via `logSyncEvent(..., 'error')` on failure paths. Remove any `'verify' => false` TLS option. Convert `finishSyncLog` to pass persisted count. <!-- sdd-owner: implementation -->
- [x] Refactor all 6 commands so `handle(): int` routes the sync body through `guardedSync(...)`. **Apply deviation:** inline closures were retained instead of extracting `doSync()` methods to avoid legacy-formatting churn and keep each review unit under 400 diff lines. <!-- sdd-owner: implementation -->
- [x] Run scoped test suite and verify no baseline regressions: `DB_HOST=127.0.0.1 DB_PORT=3308 php artisan test --filter=Prospects Modules/Prospects/tests`. <!-- sdd-owner: implementation -->

---

## Slice B — Data-correctness fixes (findings 2, 3, 4, 5, 12, 15)

### RED tests (write before any implementation)

- [x] Write `Modules/Prospects/tests/Feature/SyncHockeyClubsCommandTest.php` (new) with test `test_francophone_hockey_club_gets_language_fr`: mock a hockey.be club with federation `FR-LFH` via Guzzle `MockHandler` (TPV pattern), assert the persisted `Prospect` has `language = 'fr'`. <!-- sdd-owner: implementation -->
- [x] Write `SyncHockeyClubsCommandTest.php` with test `test_flemish_hockey_club_gets_language_nl`: mock a club with federation `VL-VHL`, assert `language = 'nl'`. <!-- sdd-owner: implementation -->
- [x] Write `SyncHockeyClubsCommandTest.php` with test `test_arbh_club_external_id_uses_arbh_prefix`: mock a club with federation `ARBH-KBHB`, assert `external_id` starts with `ARBH-`. <!-- sdd-owner: implementation -->
- [x] Extend `Modules/Prospects/tests/Feature/SyncRbfaGraphqlCommandTest.php` (from Slice A) with test `test_rbfa_discovery_covers_vlaams_brabant_and_brussel`: mock `config(['rbfa.provinces' => ...])` to include the newly uncommented provinces, assert discovery series includes Vlaams-Brabant and Brussel. <!-- sdd-owner: implementation -->
- [x] Extend `SyncRbfaGraphqlCommandTest.php` with test `test_rbfa_series_failure_is_logged_as_error`: mock a discovery series POST that returns a 500, assert an `error` log entry exists for that series in `SyncHistory`. <!-- sdd-owner: implementation -->
- [x] Extend `SyncRbfaGraphqlCommandTest.php` with test `test_rbfa_region_inference_uses_handles_club_regions`: mock a club with a known postal code, assert the persisted region matches the `HandlesClubRegions` postal-code mapping (not a hardcoded province array). <!-- sdd-owner: implementation -->
- [x] Extend `Modules/Prospects/tests/Feature/SyncValClubsCommandTest.php` (from Slice A) with test `test_val_terreinen_section_parses_into_locations`: mock a club detail page HTML that includes a "Terreinen" section with multiple venue entries, assert the persisted prospect has multiple `ProspectLocation` rows and sections after Terreinen are still parsed. <!-- sdd-owner: implementation -->
- [x] Write `Modules/Prospects/tests/Feature/SyncAftClubsCommandTest.php` (first pass) with test `test_aft_uses_getFallbackRegionId_not_magic_11`: mock the AFT command's scrape, assert the region resolution calls `getFallbackRegionId()` (testable by inspecting the persisted region or by stubbing the trait method). <!-- sdd-owner: implementation -->

### Implementation tasks

- [x] Modify `SyncHockeyClubsCommand.php`: fix language derivation for actual federation values and extract one external-id helper. New ARBH rows emit the canonical `ARBH-HOCKEY-<id>` scheme; legacy re-stamping remains in D2. <!-- sdd-owner: implementation -->
- [x] Edit `config/rbfa.php`: uncomment the province series entries for Vlaams-Brabant and Brussel so discovery covers all Belgian provinces. <!-- sdd-owner: implementation -->
- [x] Modify `SyncRbfaGraphqlCommand.php` (Slice B part): add per-series failure logging — when a discovery POST returns non-2xx or unparseable, call `logSyncEvent("Series {series} failed: {status}", 'error')`. Replace hardcoded province-name array for region inference with `HandlesClubRegions::getRegionIdFromPostalCode()`. <!-- sdd-owner: implementation -->
- [x] Modify `SyncValClubsCommand.php` (Slice B part): replace `return false` inside the DomCrawler `each()` closure with an actual filter/break (`$crawler->filter(...)` approach). Ensure Terreinen venues parse as `ProspectLocation` rows. <!-- sdd-owner: implementation -->
- [x] Modify `SyncAftClubsCommand.php` (Slice B part): replace `region_id ?? 11` with a `resolveRegionId()` helper that delegates missing postal codes to `getFallbackRegionId()`. <!-- sdd-owner: implementation -->
- [x] Run scoped test suite and confirm no baseline regressions. Run full Prospects suite unfiltered. <!-- sdd-owner: implementation -->

**Verification evidence:** isolated MySQL database `testing_cla535`; 71 tests / 200 assertions passed; PHP syntax and `git diff --check` passed. Native review preflight is available again, but the ambient target includes unrelated pre-existing tracked changes, so review start is deferred until the Prospects units are isolated.

---

## Slice D1 — FederationDataSource contract + RBFA adapter (findings 11→D, 13, 14 partial)

### RED tests (write before any implementation)

- [x] Write `Modules/Prospects/tests/Feature/FederationDataSourceContractTest.php` (abstract contract test) with test `test_every_adapter_implements_interface`: iterate over classes in `Modules/Prospects/DataSource/`, assert each is an instance of `FederationDataSource`. <!-- sdd-owner: implementation -->
- [x] Write `FederationDataSourceContractTest.php` with test `test_every_adapter_returns_normalized_club_shape`: for each adapter's `fetchClubs()` stub/mock, assert each returned array has keys `externalId`, `federation`, `name`, `type`, `language`, and values are non-empty. <!-- sdd-owner: implementation -->
- [x] Write `FederationDataSourceContractTest.php` with test `test_external_id_prefix_matches_scheme`: for each adapter, assert every `externalId` matches regex `/^(VL|FR|ARBH|BR)-/`. <!-- sdd-owner: implementation -->
- [x] Write `FederationDataSourceContractTest.php` with test `test_fromArray_rejects_bad_external_id`: pass an array with external_id `FOO-123` to `NormalizedClub::fromArray()`, assert `\InvalidArgumentException` is thrown. <!-- sdd-owner: implementation -->
- [x] Write `FederationDataSourceContractTest.php` with test `test_fromArray_rejects_empty_name`: pass an array with empty `name`, assert `\InvalidArgumentException` is thrown. <!-- sdd-owner: implementation -->
- [x] Write `Modules/Prospects/tests/Feature/RbfaGraphQLSourceTest.php` with test `test_fetch_clubs_returns_normalized_clubs`: mock a discovery series response and enrichment response sequence via `Http::fake()`, call `RbfaGraphqlSource::fetchClubs()`, assert `NormalizedClub[]` is returned with correct fields. <!-- sdd-owner: implementation -->
- [x] Verify CAFCA independence through the adapter HTTP contract test: discovery and enrichment target only `RbfaGraphqlSource::DEFAULT_URL` (RBFA GraphQL), with no CAFCA ERP dependency. (No club-level "CAFCA affiliation" field exists in the RBFA payload; "No CAFCA" refers to the ERP sync boundary, not a club filter.) <!-- sdd-owner: implementation -->
- [x] Write `RbfaGraphQLSourceTest.php` with test `test_fetch_clubs_populates_failures_on_series_500`: mock a series that returns 500, assert `$source->failures()` contains the series identifier. <!-- sdd-owner: implementation -->
- [x] Write `RbfaGraphQLSourceTest.php` with test `test_fetch_clubs_throws_DataSourceException_when_all_series_fail`: mock all series returning 500, assert `DataSourceException` is thrown. <!-- sdd-owner: implementation -->
- [x] Write `RbfaGraphQLSourceTest.php` with test `test_enrichment_has_timeout_and_retry_headers`: assert via `Http::assertSent()` that the enrichment POST uses `timeout: 30` and `retry: [3, 5000]`. <!-- sdd-owner: implementation -->
- [x] Write `RbfaGraphQLSourceTest.php` with test `test_federation_derived_from_postal_code`: mock a club with a Flemish postal code (2000), assert `NormalizedClub.federation` equals `'VL-VV'` and `language` equals `'nl'`; mock a Walloon postal code (4000), assert `'FR-ACFF'` / `'fr'`. <!-- sdd-owner: implementation -->
- [x] Write `RbfaGraphQLSourceTest.php` with test `test_brussels_rbfa_club_keeps_FR_ACFF`: mock a club with postal code 1000 (Brussels), assert federation is `'FR-ACFF'` (consistent with existing dataset). <!-- sdd-owner: implementation -->
- [x] Write `RbfaGraphQLSourceTest.php` with test `test_headquarters_location_includes_sourceLocationId`: assert the returned `NormalizedClub` has `headquarters` with `sourceLocationId` matching the RBFA club id string. <!-- sdd-owner: implementation -->
- [x] Extend `SyncRbfaGraphqlCommandTest.php` with test `test_refactored_command_consumes_injected_adapter`: bind a stub `RbfaGraphqlSource` that returns 2 pre-built `NormalizedClub` instances, run the command, assert the stub's output is persisted to `Prospect` rows. <!-- sdd-owner: implementation -->

### Implementation tasks

- [x] Create `Modules/Prospects/Contracts/FederationDataSource.php`: minimal interface with `name(): string` and `fetchClubs(): array` (returning `NormalizedClub[]`). Docblock with expected exception type `DataSourceException`. <!-- sdd-owner: implementation -->
- [x] Create `Modules/Prospects/DataObjects/ClubLocation.php`: final readonly class with `address`, `contactName`, `email`, `phone`, `sourceLocationId` (all typed). <!-- sdd-owner: implementation -->
- [x] Create `Modules/Prospects/DataObjects/NormalizedClub.php`: final readonly class with `externalId`, `federation`, `name`, `type`, `language`, `postalCode`, `website`, `logoUrl`, `vatNumber`, `contactPerson`, `channel`, `headquarters`, `venues`. Static `fromArray(array $raw): self` factory validates non-empty required keys and `externalId` matches prefix scheme `/^(VL|FR|ARBH|BR)-/`. Throws `\InvalidArgumentException` on malformed input. <!-- sdd-owner: implementation -->
- [x] Create `Modules/Prospects/Exceptions/DataSourceException.php`: extends `\RuntimeException`. <!-- sdd-owner: implementation -->
- [x] Create `Modules/Prospects/DataSource/RbfaGraphqlSource.php`: implements `FederationDataSource`. Constructor accepts injectable `$apiUrl` (defaults to production) and `$provincesConfig` (defaults to `config('rbfa.provinces')`). `fetchClubs()` iterates provinces, sends persisted-query POSTs (`0a53124a…` for `GetSeriesRankings`, `7c1bd99f…` for `getClubInfo`), builds `NormalizedClub` with `headquarters` `ClubLocation`, derives federation/language from postal via region mapping. Populates `failures()` on non-200 per-series/per-club responses. `DataSourceException` when zero successful series. Preserves 1s inter-series sleep and 250-char truncation on joined emails/phones. Remains independent of the internal CAFCA ERP flow (no club-level CAFCA filter — none exists in the RBFA payload). <!-- sdd-owner: implementation -->
- [x] Create `Config/rbfa.php` (or ensure it exists): configure provinces array for RbfaGraphqlSource discovery iteration. <!-- sdd-owner: implementation -->
- [x] Modify `Providers/ProspectsServiceProvider.php` `register()`: add a contextual binding for the concrete `RbfaGraphqlSource` dependency used by `SyncRbfaGraphqlCommand` (the concrete type exposes `failures()` and selection controls beyond the minimal interface). <!-- sdd-owner: implementation -->
- [x] Refactor `Console/Commands/SyncRbfaGraphqlCommand.php`: constructor injects `RbfaGraphqlSource` (typed as concrete class so `$source->failures()` is accessible). `handle()` reduces to: `startSyncLog` → `$source->fetchClubs()` → inline `updateOrCreate` loop (ClubPersister not ready until D2) → drain `$source->failures()` into error logs → if any failures, `failSyncLog()` → exit 1; else `finishSyncLog(persistedCount)`. Remove old inline GraphQL fetch logic. <!-- sdd-owner: implementation -->
- [x] Run scoped test suite and confirm no baseline regressions. <!-- sdd-owner: implementation -->

**D1 apply split and evidence:** D1a contract/DTO foundation = 225 lines (5 tests / 15 assertions); D1b RBFA source + source tests = exactly 400 lines (6 tests / 22 assertions); D1c command injection + provider binding was kept as a separate review unit. Full Prospects regression: 83 tests / 245 assertions passed on isolated database `testing_cla535_d1`; PHP syntax and `git diff --check` passed.

---

## Slice D2 — Normalization + migrations + LeadService fix (findings 13, 14, LeadService orphan)

### RED tests (write before any implementation)

- [x] Write `Modules/Prospects/tests/Feature/ClubPersisterTest.php` with test `test_persist_upserts_prospect_by_external_id`: persist a `NormalizedClub` via `ClubPersister`, assert the `Prospect` row is created with matching external_id. <!-- sdd-owner: implementation -->
- [x] Write `ClubPersisterTest.php` with test `test_persist_updates_existing_prospect`: persist the same external_id twice with a different `name`, assert name is updated (not duplicate). <!-- sdd-owner: implementation -->
- [x] Write `ClubPersisterTest.php` with test `test_persist_uses_getRegionIdFromPostalCode`: persist a club with postal code 2000, assert region matches Antwerpen. <!-- sdd-owner: implementation -->
- [x] Write `ClubPersisterTest.php` with test `test_persist_falls_back_to_Overige_on_null_postal`: persist a club with null postalCode, assert region is `Overige`. <!-- sdd-owner: implementation -->
- [x] Write `ClubPersisterTest.php` with test `test_persist_headquarters_location_uses_external_id_key`: assert the `ProspectLocation` for headquarters is keyed on `['prospect_id', 'external_id']` with value `{clubExternalId}::hq`. <!-- sdd-owner: implementation -->
- [x] Write `ClubPersisterTest.php` with test `test_persist_venue_locations_use_external_id_key`: assert each venue location gets `external_id = {clubExternalId}::{sourceLocationId ?? 'v'.$n}` and is keyed on `(prospect_id, external_id)`. <!-- sdd-owner: implementation -->
- [x] Write `ClubPersisterTest.php` with test `test_address_change_updates_existing_location_not_duplicate`: persist a club, change the address, persist again, assert the location is updated (not a new row) — regression for finding 14. <!-- sdd-owner: implementation -->
- [x] Write `ClubPersisterTest.php` with test `test_persistAll_returns_correct_tallies`: persist 3 clubs where 2 succeed and 1 fails, assert `persistAll()` returns `['persisted' => 2, 'failed' => 1]`. <!-- sdd-owner: implementation -->
- [x] Extend `Modules/Prospects/tests/Feature/SyncRbfaGraphqlCommandTest.php` with test `test_command_uses_club_persister_after_d2`: run the refactored command, assert the persisted Prospect rows match adapter output (tests that the RBFA command forwards data to `ClubPersister`). <!-- sdd-owner: implementation -->
- [x] Write `Modules/Prospects/tests/Feature/ProspectLocationKeyTest.php` (model test) with test `test_unique_index_allows_null_external_id`: create two `ProspectLocation` rows for the same prospect with null `external_id` and different `contact_type`, assert both are allowed (MySQL null-unique semantics). <!-- sdd-owner: implementation -->
- [x] Write `ProspectLocationKeyTest.php` with test `test_unique_index_rejects_duplicate_external_id`: attempt to create two rows with same `(prospect_id, external_id)`, assert unique violation. <!-- sdd-owner: implementation -->
- [x] Write `Modules/Prospects/tests/Feature/LeadServiceOrphanTest.php` with test `test_unique_violation_does_not_leave_orphan_prospect`: force a unique-violation scenario in LeadService, assert no partial/orphan Prospect row persists, and the LeadService public API signature is unchanged. <!-- sdd-owner: implementation -->

### Implementation tasks

- [x] Create `Modules/Prospects/Support/ContactType.php`: backed enum `ContactType` with cases `Headquarters = 'headquarters'`, `Venue = 'venue_name'`, `Primary = 'primary'`. Values intentionally match existing DB literals to avoid a value-rename migration. <!-- sdd-owner: implementation -->
- [x] Create `Modules/Prospects/Services/ClubPersister.php`: class using `HandlesClubRegions` trait. `persist(NormalizedClub $club): Prospect` calls `Prospect::updateOrCreate(['external_id' => $club->externalId], [...])` with region resolved via `getRegionIdFromPostalCode`. Location persistence: `ProspectLocation::updateOrCreate(['prospect_id' => $prospect->id, 'external_id' => $key], [...])` where `$key` is `{externalId}::hq` for headquarters and `{externalId}::{sourceLocationId ?? 'v'.$n}` for venues. `persistAll(array $clubs): array` wraps `persist` in a loop returning `['persisted' => int, 'failed' => int]`. <!-- sdd-owner: implementation -->
- [x] Create migration `add_external_id_to_prospects_locations_table.php`: add nullable `string('external_id')` to `prospects_locations`; create unique index `('prospect_id', 'external_id')`; backfill existing rows: `headquarters` → `{prospect.external_id}::hq` for rows with `contact_type = 'headquarters'`; `venue_name` → `{prospect.external_id}::v{n}` using `ROW_NUMBER()` per prospect. Down(): drop column and index. Follow precedent pattern from `2026_04_05_163711_update_prospect_federation_prefixes.php` (raw `DB::table` style). <!-- sdd-owner: implementation -->
- [x] Create migration `normalize_prospect_location_conventions.php`: (a) `UPDATE prospects_locations … JOIN prospects_prospects … SET contact_type='headquarters'` where current `venue_name` and federation in (`VL-TPV`, `VL-VHL`, `FR-LFH`, `ARBH-KBHB`, `FR-AFT`) — these rows hold the club's single main address. (b) `UPDATE prospects_prospects SET external_id = CONCAT('ARBH-', external_id) WHERE federation = 'ARBH-KBHB' AND external_id LIKE 'HOCKEY-%'`. (c) Delete `prospects_locations` duplicate `(prospect_id, address)` rows keeping the lowest id (finding 14 existing duplicates). Down(): reverse the contact_type re-stamp and ARBH prefix change. <!-- sdd-owner: implementation -->
- [x] Modify `Services/LeadService.php`: wrap the Prospect creation + location assignment in a nested savepoint transaction so that a unique-violation catch rolls back only the inner transaction, not the entire unit of work. Public API signature unchanged. <!-- sdd-owner: implementation -->
- [x] Update `SyncRbfaGraphqlCommand.php` to use `ClubPersister` instead of inline `updateOrCreate` after D2's schema migration is applied. <!-- sdd-owner: implementation -->
- [x] Run scoped test suite and confirm no baseline regressions. <!-- sdd-owner: implementation -->

**D2 apply split and evidence:** D2a (ContactType enum, ClubPersister, external_id schema migration + RBFA command switch to ClubPersister) = 320 lines (10 tests / 15 assertions across ClubPersisterTest and ProspectLocationKeyTest). D2b (convention migration + LeadService fix + LeadServiceOrphanTest + migration test) = 303 lines (6 tests / 8 assertions across LeadServiceOrphanTest and the new NormalizeProspectLocationConventionsMigrationTest.php). Full Prospects regression after D2: 99 tests / 268 assertions passed on isolated database `testing_cla535_d2`; PHP syntax and `git diff --check` passed.

**Apply-time finding (undocumented, discovered during TDD):** `prospects_prospects.region_id` is `NOT NULL` with a foreign key to `prospects_regions` and no default. `LeadService::persistContactLead()` never set it, so every brand-new lead insert (any email not already present) threw a 1364/FK error in production — confirmed with an isolated repro outside test scaffolding, independent of this change. User approved fixing it inside this D2b LeadService edit rather than opening a separate ticket: `region_id` now defaults to `HandlesClubRegions::getFallbackRegionId()` (`Overige`).

**Test design note (LeadServiceOrphanTest):** a true cross-connection race for the identical email deadlocks under MySQL's default REPEATABLE READ locking, because the existing `lockForUpdate()` check takes a gap lock that blocks a second connection's competing INSERT (confirmed via an isolated repro with a bounded `innodb_lock_wait_timeout`). The orphan-prevention test therefore forces a same-connection collision (via a `Prospect::created` hook) inside the nested savepoint; verified RED against the pre-fix flat-transaction code (silently commits an orphan, no exception) and GREEN against the fix (savepoint rollback removes the orphan; the exception then correctly propagates since the same-connection race winner is rolled back too).

---

## Slice C — AFTT PDF source + fake-data removal (finding 1)

### RED tests (write before any implementation)

- [x] Create the test fixture: cut a representative subset of the verified AFTT annuaire PDF (`/tmp/aftt_annuaire.pdf`) and save as `Modules/Prospects/tests/fixtures/aftt/annuaire_sample.pdf`. Include clubs with email, multi-line addresses, no-email clubs, edge cases. Commit this fixture file BEFORE any parser implementation. <!-- sdd-owner: implementation -->
- [x] Write `Modules/Prospects/tests/Feature/AfttPdfSourceTest.php` with test `test_parse_returns_clubs_from_fixture`: create `AfttPdfSource` with the fixture path and a real `Smalot\PdfParser\Parser`, call `fetchClubs()`, assert returned array has >2 clubs and each has non-empty `name` and `language = 'fr'`. <!-- sdd-owner: implementation -->
- [x] Write `AfttPdfSourceTest.php` with test `test_parse_extracts_postal_code_from_address`: assert returned clubs have valid 4-digit `postalCode` extracted from their address text. <!-- sdd-owner: implementation -->
- [x] Write `AfttPdfSourceTest.php` with test `test_parse_handles_club_with_and_without_email`: assert clubs have properly assigned email when present and null when absent. <!-- sdd-owner: implementation -->
- [x] Write `AfttPdfSourceTest.php` with test `test_garbage_bytes_throws_DataSourceException`: pass garbage bytes as the source, assert `DataSourceException` is thrown. <!-- sdd-owner: implementation -->
- [x] Write `AfttPdfSourceTest.php` with test `test_empty_text_after_parse_throws_DataSourceException`: create a minimal PDF with no club entries, assert `DataSourceException` is thrown. <!-- sdd-owner: implementation -->
- [x] Write `AfttPdfSourceTest.php` with test `test_external_id_uses_aftt_club_number_when_available`: assert clubs with a recognizable AFTT club number use `external_id = 'FR-AFTT-{clubNumber}'`. <!-- sdd-owner: implementation -->
- [x] Extend `Modules/Prospects/tests/Feature/SyncAftClubsCommandTest.php` (from Slice B) with test `test_command_uses_injected_aftt_source_not_fake_array`: bind `AfttPdfSource` returning 5 clubs, run the command, assert 5 prospects are persisted and 0 hardcoded fake clubs are created. <!-- sdd-owner: implementation -->
- [x] **Apply deviation (see design.md D7):** no `test_fake_prospects_replaced_in_place` scenario was written. Design D7 explicitly rejects re-stamping the fake rows (no trustworthy identity mapping to any real AFTT club number); the two fake prospects are soft-retired by a dedicated migration instead, covered by `SoftRetireFakeAftProspectsMigrationTest.php` (2 tests: fake rows get `unsubscribed_at`, unrelated rows are untouched).

### Implementation tasks

- [x] Run `composer require smalot/pdfparser:^2.0` at the repository root. Verify against lockfile + PHP 8.4. If `^2.0` conflict, pin a compatible version (e.g. `^2.7`). <!-- sdd-owner: implementation -->
- [x] Create `Modules/Prospects/DataSource/AfttPdfSource.php`: implements `FederationDataSource`. Constructor accepts `$source` (URL or local path), injectable `Parser`. `fetchClubs()`: if URL, `Http::timeout(120)->retry(2, 5000)->get()`; if non-200 or non-PDF → `DataSourceException`. Parse via `(new Parser())->parseContent($bytes)->getText()`. Extract clubs via a private line-oriented `parseAnnuaireText()` method (isolated for layout drift). Build `NormalizedClub` records with `external_id = 'FR-AFTT-' . ($clubNumber ?? Str::slug($clubName))`, `federation = 'FR-AFTT'`, `type = 'table_tennis_club'`, `language = 'fr'`, `postalCode` from address regex, `headquarters` location from parsed address/contact. Fatal conditions → `DataSourceException`. <!-- sdd-owner: implementation -->
- [x] Refactor `Console/Commands/SyncAftClubsCommand.php`: constructor injects `AfttPdfSource` (typed as concrete class for AFPadel scrape extensions). `handle()`: fetch clubs via adapter, persist via `ClubPersister` (available from D2), drain errors. Remove the fake hardcoded `$fallbackClubs` array; remove dead CSRF/Guzzle path (`tennis.tppwb.be`). On zero-club parse → `failSyncLog`. Document AFPadel gap in command description. <!-- sdd-owner: implementation -->
- [x] Create migration `soft_retire_fake_aft_prospects.php`: `UPDATE prospects_prospects SET unsubscribed_at = now() WHERE external_id IN ('FR-AFT-tc-de-wavre', 'FR-AFT-royal-leopold-club') AND unsubscribed_at IS NULL`. Idempotent, reversible via `UPDATE ... SET unsubscribed_at = NULL`. <!-- sdd-owner: implementation -->
- [x] Update `Providers/ProspectsServiceProvider.php` `register()`: add contextual binding mapping `FederationDataSource` to `AfttPdfSource` when `SyncAftClubsCommand::class` resolves it. <!-- sdd-owner: implementation -->
- [x] Run scoped test suite and confirm no baseline regressions. <!-- sdd-owner: implementation -->

**C apply split and evidence:** C1 (`AfttPdfSource` + `AfttPdfSourceTest.php` + real, cut-down 4-page PDF fixture with email/no-email/multi-page-multi-venue edge cases) = 229 lines (7 tests / 26 assertions). C2 (command refactor removing the fake array and dead CSRF/Guzzle path, provider binding, soft-retire migration, rewritten `SyncAftClubsCommandTest.php`, `SoftRetireFakeAftProspectsMigrationTest.php`) = 325 lines (5 tests / 16 assertions). Full Prospects regression after C: 108 tests / 305 assertions passed on isolated database `testing_cla535_c`; PHP syntax and `git diff --check` passed.

**Apply-time findings (discovered during TDD):**
- The real annuaire mixes a `"Liste des clubs"` index (one-liner `CODE - Name - email`) with the detailed per-club sections; only the detailed sections (identified by the line `Informations générales` immediately following the header) are parsed, so index entries are correctly ignored.
- A club can have multiple `Locaux` (venues) that span across PDF page boundaries without repeating the club header; pages are joined into one text stream before splitting on club headers so this is handled correctly. Verified against the full real 356-page annuaire (not just the fixture): 278 clubs parsed (matching the index count), 7 without email, 1 without a postal code.
- PCRE's `U` (ungreedy) modifier interacted incorrectly with the club-splitting lookahead (caused a single match spanning to end-of-string instead of stopping at the next club); replaced with explicit lazy `.+?`/`.*?` quantifiers.
- `\s*` next to a field's colon (e.g. `Email :`) can cross a line boundary since `\s` matches `\n`, causing an empty field to swallow the next line's content; replaced with `[ \t]*` for the colon-adjacent whitespace.
- `composer require smalot/pdfparser` triggered `artisan vendor:publish --force`, modifying unrelated tracked Filament asset files under `public/`; these were reverted via `git checkout -- public/css public/js` before continuing, keeping only the intended `composer.json`/`composer.lock` change.
- `AfttPdfSource` could not stay `final` (matching `RbfaGraphqlSource`'s earlier precedent) because Mockery needs to subclass it for the command test's mock.
- A same-process migration test that rolls back "the last migration" (`--step=1`) is fragile once a later migration is added (this happened: adding the C soft-retire migration broke D2b's convention-migration test, which assumed it was still last). Both migration tests were fixed to target their exact migration file by name (delete its `migrations` table row, then `artisan migrate --path=<file>`) instead of relying on batch/step order.

---

## Slice E — Brussels cadastre CSV adapter (new coverage)

### RED tests (write before any implementation)

- [x] Create the test fixture: save a representative subset of the Brussels cadastre CSV as `Modules/Prospects/tests/fixtures/brussels/infra_sample.csv`. Include rows with the literal `Plca_street_nl` header typo, rows with multiple name languages, edge cases. <!-- sdd-owner: implementation -->
- [x] Write `Modules/Prospects/tests/Feature/BrusselsCadastreSourceTest.php` with test `test_parse_returns_records_from_fixture`: create `BrusselsCadastreSource` with the fixture CSV path, call `fetchClubs()`, assert returned array has rows matching the fixture count. <!-- sdd-owner: implementation -->
- [x] Write `BrusselsCadastreSourceTest.php` with test `test_csv_header_typo_is_consumed_literally`: assert the Dutch street column is read from the literal `Plca_street_nl` header, not a corrected name. <!-- sdd-owner: implementation -->
- [x] Write `BrusselsCadastreSourceTest.php` with test `test_external_id_uses_BR_CAD_prefix`: assert each returned club has `external_id = 'BR-CAD-{IN_ID}'`. <!-- sdd-owner: implementation -->
- [x] **Apply rename:** implemented as `test_region_mapping_via_postal_code` — verifies the source extracts a Brussels postal code (1000–1299) as `postalCode`; the actual region_id resolution happens in `ClubPersister::getRegionIdFromPostalCode()` at persist time (same pattern as every other adapter), not inside the source itself.
- [x] Write `BrusselsCadastreSourceTest.php` with test `test_language_from_name_fields`: assert `language = 'fr'` when only `name_fr` present, `'nl'` otherwise. <!-- sdd-owner: implementation -->
- [x] Write `BrusselsCadastreSourceTest.php` with test `test_download_failure_throws_DataSourceException`: give a non-200 URL, assert `DataSourceException` is thrown. <!-- sdd-owner: implementation -->
- [x] Write `Modules/Prospects/tests/Feature/SyncBrusselsClubsCommandTest.php` with test `test_command_creates_sync_history_with_records_count`: bind a stub `BrusselsCadastreSource` returning 3 clubs, run `prospects:sync-brussels-clubs`, assert `SyncHistory` exists for the command with `records_count = 3`. <!-- sdd-owner: implementation -->
- [x] Write `SyncBrusselsClubsCommandTest.php` with test `test_auxiliary_semantics_no_overwrite_of_federation_address`: pre-create a Prospect from a federation source with a `headquarters` address, run the Brussels sync, assert the federation-sourced address is not overwritten. <!-- sdd-owner: implementation -->

### Implementation tasks

- [x] Create `Modules/Prospects/DataSource/BrusselsCadastreSource.php`: implements `FederationDataSource`. Constructor accepts injectable `$url` (defaults to the production CSV URL). `fetchClubs()`: `Http::timeout(60)->retry(2, 5000)->get()`; non-200 → `DataSourceException`. Parse CSV via `str_getcsv`. Map columns per design §2.3 (`IN_ID` → externalId, `name_fr`/`name_nl`/`name_en` → name/language, `Place_street_fr`/`Plca_street_nl`/`Place_street_en` + `Place_num` + `Place_zipcode` + `Place_city` → single venue location with `address`). `federation = 'BR-CAD'`, `type = 'sports_infrastructure'`. Region via `HandlesClubRegions`. No `headquarters` (venue-only). <!-- sdd-owner: implementation -->
- [x] Create `Console/Commands/SyncBrusselsClubsCommand.php`: `prospects:sync-brussels-clubs {--user=} {--history=}`. Constructor injects `BrusselsCadastreSource`. `handle()`: `guardedSync` → `fetchClubs()` → `ClubPersister::persistAll()` → finish/fail. <!-- sdd-owner: implementation -->
- [x] Update `Providers/ProspectsServiceProvider.php`: add `SyncBrusselsClubsCommand` to `registerCommands()`. Add contextual binding for `BrusselsCadastreSource`. Add to command schedule: `monthlyOn(1, '04:00')`. <!-- sdd-owner: implementation -->
- [x] Update `Jobs/MasterSyncJob.php`: append `new ExecuteSyncJob('prospects:sync-brussels-clubs', ...)` to the chain as the 7th sync job (after RBFA). <!-- sdd-owner: implementation -->
- [x] Update `Filament/Pages/SyncDashboardPage.php`: add `prospects:sync-brussels-clubs` to the federation command list so it gets a card and can be triggered per-federation. Add CC-BY 2.0 attribution footer: *"Source: Infrastructures sportives — Région de Bruxelles-Capitale (CC-BY 2.0, backend.datastore.brussels)"*. <!-- sdd-owner: implementation -->
- [x] Add `@license CC-BY-2.0` and attribution docblock to `BrusselsCadastreSource` class. <!-- sdd-owner: implementation -->
- [x] Run scoped test suite and confirm no baseline regressions. Run full Prospects suite unfiltered. <!-- sdd-owner: implementation -->

**E apply split and evidence:** E1 (`BrusselsCadastreSource` + `BrusselsCadastreSourceTest.php` + a fixture CSV cut from the live download — 3 real rows incl. one with no assemblable address, plus 1 clearly-labeled synthetic `name_fr`-only row since no such row exists in the live ~1,110-row dataset) = 223 lines (8 tests / 13 assertions). E2 (command, provider binding/registration/schedule, `MasterSyncJob` 7th chain job, `SyncDashboardPage` card + CC-BY footer, `SyncBrusselsClubsCommandTest.php`, `MasterSyncJobTest.php` chain-length update) = 260 lines (4 tests / 14 assertions, on top of the existing `MasterSyncJobTest` coverage). Full Prospects regression after E: 118 tests / 329 assertions passed on isolated database `testing_cla535_e`; PHP syntax and `git diff --check` passed.

**Apply-time findings (discovered during TDD):**
- The live CSV (downloaded fresh and verified: 200 OK, 166,029 bytes, matching research.md exactly, including the literal `Plca_street_nl` header typo) starts with a UTF-8 BOM (`EF BB BF`). Without stripping it, the first column's key becomes `"\xEF\xBB\xBFIN_ID"` instead of `"IN_ID"`, silently breaking every row's `external_id`. Added a dedicated regression test reproducing the exact BOM bytes.
- Of the ~1,110 real rows, only 3 lack `name_nl` (and those 3 also lack any address); `language = 'fr'` (only-`name_fr`) is a real-world edge case with no naturally occurring example that also has an address, so one clearly-labeled synthetic fixture row covers it.
- Rows with no assemblable address (no street and no zipcode across all three language columns) are skipped by the adapter rather than emitted as a venue-less `NormalizedClub` (a `sports_infrastructure` record's whole purpose is its venue location). Verified against the full live CSV: 157 of 1,110 rows produce a usable club record.
- `Http::retry(2, 5000)` throws `RequestException` by default when the final attempt still fails; switched to `retry(2, 5000, throw: false)` so the command's own `! $response->successful()` check (matching every other source) converts it to `DataSourceException` instead of an uncaught framework exception.
- **Unrelated regression found and fixed while touching the provider:** Slice C's AFT wiring had silently missing `use` imports for `SyncAftClubsCommand`/`AfttPdfSource`; `::class` on an unqualified name is a compile-time string, not an autoload trigger, so this never surfaced as a fatal error — the contextual binding for AFT's source was pointing at a bogus `Modules\Prospects\Providers\SyncAftClubsCommand` string and never matched. Harmless in practice only because `AfttPdfSource` has a default-valued constructor the container can auto-resolve anyway. Fixed alongside adding the Brussels imports.

---

## Slice F — Ops follow-up / documentation (docs only)

### Tasks

- [x] Update `handoff.md`: document the `FederationDataSource` adapter architecture, per-federation source table (RBFA GraphQL / AFTT PDF / Brussels CSV / Hockey scrape / TPV scrape / VAL scrape / LBFA scrape), slice status for CLA-535, and remaining gaps (AFPadel, Verenigingsregister, Sport Vlaanderen). Each deferred item must have current blocker and next action. <!-- sdd-owner: implementation -->
- [x] Update `docs/ai/context-map.md`: add the new adapter architecture to the module's context map; reference `Contracts/FederationDataSource`, `DataObjects/NormalizedClub`, `DataObjects/ClubLocation`, `Services/ClubPersister`, `Support/ContactType`, and each `DataSource/*` class with one-line purpose. <!-- sdd-owner: implementation -->
- [x] Update `docs/ai/known-risks.md` (if it tracks deferred items): add Verenigingsregister API key, Sport Vlaanderen dataset pinning, and AFPadel HTML scrape as known deferred risks. <!-- sdd-owner: implementation -->
- [x] Run scoped test suite to confirm zero code-change regression. <!-- sdd-owner: implementation -->

**F evidence:** docs-only slice, no application code touched — `git diff --stat` limited to `handoff.md`, `docs/ai/context-map.md`, `docs/ai/known-risks.md`, this `tasks.md`. Scoped Prospects suite re-confirmed green (118 passed / 329 assertions, `Modules/Prospects/tests/Feature`, isolated Sail stack `claesen_api_web_oficial-*`) before writing this note.

---

## Cross-cutting completion tasks (after all slices)

- [x] Run full module suite unfiltered: `DB_HOST=127.0.0.1 DB_PORT=3308 php artisan test --filter=Prospects Modules/Prospects/tests` — verify 0 new failures beyond preexisting baseline. <!-- sdd-owner: implementation -->
- [x] Run full application suite (optional but recommended): `DB_HOST=127.0.0.1 DB_PORT=3308 php artisan test` — verify no cross-module regressions. <!-- sdd-owner: implementation -->

**Cross-cutting evidence (2026-09-15):** Prospects module suite (`Modules/Prospects/tests/Feature`, 22 test files including all slices A–E): **118 passed / 329 assertions**, 0 failures. Full application suite (`./vendor/bin/phpunit`, no filter): **1446 passed / 4628 assertions**, 0 failures, 0 errors, 2 skipped, 7 PHPUnit notices (skips/notices are preexisting, unrelated to this change — matches historical CI baseline documented in `handoff.md`/CLA-532 closure). Both runs executed against an isolated Docker Sail stack for this checkout (`claesen_api_web_oficial-*` containers, alternate host ports) so as not to disturb the unrelated running stack from a different clone of the same repo on the host.

---

## Delivery decision points

| Gate | Trigger | Action |
|------|---------|--------|
| After Slice A GREEN | Normal passage | Proceed to B (ask-on-risk: clean) |
| After Slice B GREEN | Normal passage | Proceed to D1 (ask-on-risk: clean) |
| **Before opening D1 PR** | D1 diff > 400 lines | **STOP** — ask user; propose D1→D1a (contract+DTO) + D1b (RBFA adapter) sub-split |
| **Before opening D2 PR** | D2 diff > 400 lines | **STOP** — ask user; propose D2→D2a (enum+persister+schema) + D2b (convention migration+LeadService) sub-split |
| After D2 GREEN | Normal passage | Proceed to C (ask-on-risk: clean) |
| After C GREEN | Normal passage | Proceed to E (ask-on-risk: clean) |
| After E GREEN | Normal passage | Proceed to F (ask-on-risk: clean) |
| After F complete | All slices done | Archive phase |

> **IMPORTANT**: The apply phase MUST NOT self-select chaining or `size:exception`. Only "Proceed" or "Stop and ask" are valid responses at each gate.

---

## Memory updates

Each slice MUST include an Engram memory save (topic_key `sdd/prospects-federation-refactor/apply`) before progressing to the next slice. Use format:

- **title**: `CLA-535 Slice <letter> applied — <summary>`
- **type**: `decision`
- **scope**: `project`
- **topic_key**: `sdd/prospects-federation-refactor/apply`
- **content**: What was implemented, what was verified, changed files count, tests added, test count delta, risks realised, any deviations from design.