# Explore — prospects-federation-refactor (CLA-535)

- **Change**: `prospects-federation-refactor`
- **Ticket**: CLA-535 — "Prospects: federation data-source refactor + audit fixes"
- **Branch**: `audit/prospects-module`
- **Module**: `Modules/Prospects`
- **Provenance**: Findings derive from the parent audit (Engram observation 70, topic_key `prospects-module-audit`, project `claesen-analytics`) and were re-grounded against source in this session (all file:line references verified in the working tree). Note: Engram *read* tools were not injected in this executor session; evidence below was re-anchored directly in the module sources listed under Sources.

---

## 1. Module overview — federation sync architecture

Six sync entry points (artisan commands, one per federation data source), orchestrated by a master chain, surfaced in a Filament backoffice dashboard.

| # | Command | Source | Method | Sport | Scope |
|---|---------|--------|--------|-------|-------|
| 1 | `prospects:sync-rbfa-graphql` | `datalake-prod2018.rbfa.be/graphql` (persisted queries) | GraphQL API | Football | Discovery over province series catalog (`config/rbfa.php`), then per-club enrichment |
| 2 | `prospects:sync-hockey-clubs` | `hockey.be/nl/nl-club/?id=…` | HTML scrape (Guzzle + DomCrawler) | Hockey | Hardcoded list of ~124 clubs in the command itself |
| 3 | `prospects:sync-aft-clubs` | `tennis.tppwb.be/MyAFT/Clubs/Search` | HTTP + CSRF (broken) | Tennis/Padel FR | **Fake data**: 2 hardcoded sample clubs |
| 4 | `prospects:sync-tpv-clubs` | `tennisenpadelvlaanderen.be/zoek-een-club` + `clubdashboard/over-club` | HTML scrape | Tennis/Padel NL | Paginated discovery (offset loop) + per-club detail fetch |
| 5 | `prospects:sync-val-clubs` | `atletiek.be/organisatie/clubs` | HTML scrape (Laravel Http) | Athletics NL | Link discovery + per-club detail fetch |
| 6 | `prospects:sync-lbfa-clubs` | `lbfa.be/fr/liste-des-clubs` | HTML scrape (table) | Athletics FR | Single-page table parse |

**Orchestration & UI**

- `Console/Commands/SyncMasterCommand.php` — dispatches `MasterSyncJob` (fire-and-forget, no history record of its own at the command level).
- `Jobs/MasterSyncJob.php:23-31` — `Bus::chain([ExecuteSyncJob × 6 …, SendMasterSyncFinishedNotificationJob])` in order LBFA → AFT → Hockey → TPV → VAL → RBFA; `WithoutOverlapping('prospects-master-sync')`, releaseAfter 120s.
- `Jobs/ExecuteSyncJob.php:29-31` — `Artisan::call($command, ['--user' => …, '--history' => …])`, then unconditional success DB notification to the triggering user.
- `Filament/Pages/SyncDashboardPage.php` — super_admin-only page; federation cards from `SyncHistory` (last record per command), master-sync status, failed-syncs feed (last 7 days, take 5); `syncFederation()` / `syncAll()` create history rows inside `lockForUpdate` transactions to prevent duplicates/arbitrary artisan execution, then dispatch jobs.
- Shared traits: `Traits/LogsSyncEvents.php` (SyncHistory lifecycle: running → completed/failed, logs flushed every 5 events), `Traits/HandlesClubRegions.php` (postal-code → `Region` mapping, `Overige` fallback via `firstOrCreate`).
- Persistence: `Prospect` (`prospects_prospects`, unique key `external_id`, prefixes `VL-`/`FR-`/`ARBH-`), `ProspectLocation` (`contact_type`: `headquarters` | `venue_name`), `SyncHistory`.

## 2. Audit findings (16) grouped by severity

### Critical — data integrity / trustworthiness of the dataset

1. **AFT sync is fake data.** `SyncAftClubsCommand.php:36-56`: CSRF token parse fails → command logs "Falling back to local data extract" and upserts 2 hardcoded sample clubs (`TC de Wavre`, `Royal Leopold Club`) into production `prospects_prospects`. Wallonia tennis/padel coverage is effectively fictional; it also feeds the mailing pipeline.
2. **Hockey club list hardcoded in code.** `SyncHockeyClubsCommand.php:15-141`: ~124 clubs with `hockey.be` IDs and federations inline in the command class. No discovery; new/dissolved clubs require a code change; list includes test clubs (`Testvereniging 1`/`2`) that reach production data.
3. **TLS verification disabled in 3 scrapers.** `SyncAftClubsCommand.php:29`, `SyncHockeyClubsCommand.php:148`, `SyncTpvClubsCommand.php:26` all construct Guzzle clients with `'verify' => false` — MITM-exposable and inconsistent with the `Http::`-based commands (RBFA, VAL, LBFA) that use default verification.
4. **Hockey language mapping is dead code → wrong language.** `SyncHockeyClubsCommand.php:173`: `$language = ($federation === 'LFH') ? 'fr' : 'nl'` — `$federation` is `FR-LFH`/`VL-VHL`/`ARBH-KBHB`, never `LFH`, so every francophone hockey club is stored with `language = 'nl'`. Directly corrupts language-targeted mail campaigns.
5. **RBFA discovery silently drops failures.** `SyncRbfaGraphqlCommand.php` (~line 100): the discovery loop only handles `$response->successful()`; failed/unparseable series responses are skipped with no log entry and no error state — a province-wide outage yields a "successful" sync with missing clubs.
6. **VAL per-club exceptions swallowed entirely.** `SyncValClubsCommand.php` `syncClub()` (~line 205): `catch (\Exception $e) { // Log or ignore }` — empty catch; club failures are invisible in console output and SyncHistory logs, which keep reporting success.

### Error-handling — observability and failure semantics

7. **`Artisan::call` exit code ignored.** `ExecuteSyncJob.php:~29-46`: the exit code of `Artisan::call()` is not checked; the DB notification always says the sync "ha terminado satisfactoriamente" even when the command failed (only the per-command `failSyncLog` marks history failed).
8. **Master chain has no failure handling.** `MasterSyncJob.php:23-31`: if any `ExecuteSyncJob` throws, the chain halts, remaining federations never run, `SendMasterSyncFinishedNotificationJob` never fires, and the master `SyncHistory` row stays `pending`/`running` forever (dashboard shows an active master indefinitely; `sync_all` stays disabled).
9. **`records_count` reports attempts, not successes.** `SyncHockeyClubsCommand.php` (final `finishSyncLog($count)`) and `SyncRbfaGraphqlCommand.php` (`finishSyncLog($processed)`) count loop iterations, not persisted clubs; partial failures are indistinguishable from full success on the dashboard. TPV at least appends a quality line to logs (`SyncTpvClubsCommand.php`, end of `handle()`), but the number itself is still attempt-based.
10. **RBFA enrichment call lacks timeout/retry.** `SyncRbfaGraphqlCommand.php` (~line 127): discovery POST uses `->timeout(60)->retry(3, 5000)` but the per-club enrichment POST uses bare `Http::withHeaders($headers)->post(...)` — no timeout, no retry; a hang blocks the whole sync (and, under `QUEUE_CONNECTION=sync`, the request worker).
11. **TPV pagination safety cap silently truncates.** `SyncTpvClubsCommand.php` `do…while ($offset < 1000)`: clubs beyond the 1000-offset cap are never fetched and nothing indicates truncation in logs or history.
12. **Logs can be lost / status stuck on crash.** `LogsSyncEvents.php:~40-50`: `logSyncEvent` flushes to DB only every 5th event; a mid-run crash loses up to 4 buffered events and leaves the `SyncHistory` row in `running` with no failure transition (no `failed()`/`finally` hook in commands).

### Consistency — conventions drift across the six commands

13. **Federation + external_id schemes drift.** RBFA derives `VL-VV`/`FR-ACFF` from a hardcoded province list and prefixes `VL-`/`FR-` (`SyncRbfaGraphqlCommand.php`, enrichment block); Hockey builds `VL-VHL`/`FR-LFH`/`ARBH-KBHB` with duplicated prefix logic at `SyncHockeyClubsCommand.php:~185`; TPV `VL-TPV-<id>`; VAL `VL-<slug>`; LBFA `FR-LBFA-<slug>`; AFT `FR-AFT-<slug>`. Slug-based external IDs (VAL/LBFA/AFT) break if the source name changes, unlike ID-based ones (RBFA/Hockey/TPV).
14. **Location `contact_type` inconsistent.** RBFA + LBFA write `headquarters`; Hockey, TPV, VAL write `venue_name`; VAL additionally keys `ProspectLocation::updateOrCreate` on `['prospect_id', 'address']` (`SyncValClubsCommand.php:~190`), so an address change creates a duplicate location instead of updating.
15. **Region inference duplicated/magic.** RBFA re-implements Flanders-vs-Wallonia from a hardcoded province-name array instead of reusing `HandlesClubRegions` postal mapping; AFT hardcodes `region_id ?? 11` (`SyncAftClubsCommand.php:~90`, magic number for `Overige`) instead of calling `getFallbackRegionId()`.
16. **Mixed HTTP stacks and error granularity.** Commands alternate between Laravel `Http::` facade (RBFA, VAL, LBFA) and raw Guzzle with TLS off (AFT, Hockey, TPV), with different timeout/retry/User-Agent conventions; per-club error logging exists only in TPV (`logSyncEvent(... 'error' ...)`), not in Hockey/RBFA/VAL/LBFA loops.

## 3. Verified data-source alternatives (from audit observation 70)

| Federation / region | Current | Verified alternative | Verdict |
|---|---|---|---|
| RBFA (football, national) | GraphQL persisted queries `datalake-prod2018.rbfa.be` | — | **Keep.** Works, structured, rate-limit friendly (1s sleeps); harden enrichment call (timeout/retry, error logging). |
| Flanders sports clubs (TPV tennis/padel; hockey VL side) | HTML scraping of federation sites | **Sport Vlaanderen open data** + **Verenigingsregister** (Vlaamse overheid open datasets) | Adopt as primary structured source for Flemish clubs; scraping remains fallback. Stable IDs, addresses, contact emails; removes scraper fragility. |
| Wallonia tennis/padel (AFT) | CSRF-blocked scrape → fake data | AFT/TPPWB published club **PDF lists** + **Playwright** headless session for the CSRF-protected portal where PDF lacks fields | Replaces fake data with real coverage; Playwright is heavier — keep as targeted enrichment, PDF as bulk. |
| Brussels clubs | Mixed scrapers, region mapping only | Brussels region **cadastre CSV** / open-data exports as auxiliary address/geo source | Auxiliary only; primary records still come from federation sources. |

Non-source refs: Belgian postal-code → province mapping in `HandlesClubRegions.php` stays the canonical region inference; new sources must map to it rather than introducing per-source region logic.

## 4. Testing baseline and feedback loop

- Runner: `php artisan test` (PHPUnit 12, root `phpunit.xml`; `APP_ENV=testing`, `QUEUE_CONNECTION=sync`, `DB_DATABASE=claesen_analytics_web_testing`).
- **Scoped Prospects command** (host cannot resolve `DB_HOST=mysql`; use the Docker MySQL exposed on host port 3308):
  ```
  DB_HOST=127.0.0.1 DB_PORT=3308 php artisan test --filter=Prospects Modules/Prospects/tests
  ```
- **Baseline: 32 tests / 88 assertions passing** (verified 2026-09-13, ~70s) — `openspec/config.yaml: prospects_baseline`.
- Existing co-located coverage: `Modules/Prospects/tests/Feature/{LogsSyncEventsTest, ProspectTabSelectionTest, SyncDashboardGuardTest, SyncTpvClubsCommandTest}.php` — dashboard guard, TPV command (client injected via constructor, the one command built for testability), logging trait. No tests exist for RBFA/Hockey/AFT/VAL/LBFA commands.
- TDD contract: strict TDD (`openspec/config.yaml testing.strict_tdd: true`) — failing test first (RED) per behavior change; every behavior change in `Modules/**` needs a co-located test; scoped suite green before any progress claim; full module suite unfiltered before phase closure.
- Full-suite context: ~1322 passing since CLA-529; any new failure outside preexisting ones is blocking.

## 5. Risks and non-goals

### Risks

1. **Data-migration risk**: changing federation codes / external_id schemes (finding 14) can orphan existing prospects and their mail-log links; needs an `external_id` mapping migration (`2026_04_05_163711_update_prospect_federation_prefixes.php` is precedent).
2. **Removing fake AFT data** deletes 2 real-looking prospects; decide retention/re-stamp strategy before the refactor run.
3. **Scraper fragility**: hockey.be/TPV/VAL/LBFA markup selectors (`table.sl-table`, `li.clearfix`, table cells) break silently; any refactor should add per-club error surfacing first (findings 5, 6, 16) so failures are visible before sources are swapped.
4. **RBFA GraphQL is undocumented/private API**: persisted-query hashes can rotate; keep current client but add failure telemetry, not a rewrite.
5. **Review budget**: 400 changed lines per slice — the refactor spans 6 commands + traits + dashboard; expect a chained-PR decision (delivery strategy `ask-on-risk` pauses for the user at that point; do not self-select chaining or `size:exception`).
6. **Queue semantics**: `QUEUE_CONNECTION=sync` in tests vs real queue in prod; `WithoutOverlapping` + chain behavior needs feature tests with `Bus::fake`/`Queue::fake`.
7. **Language bug blast radius**: fixing finding 4 flips `language` for ~all FR hockey clubs; mail campaign segmentation changes immediately after sync — coordinate with Mailing-side expectations.

### Non-goals

- No changes to the CAFCA flow (the RBFA command is independent of the internal CAFCA ERP sync today — "No CAFCA" in its description is that boundary, not a club-level filter; keep it that way).
- No changes to Mailing module (campaigns, mail logs) beyond Prospects-side data correctness.
- No new Filament features beyond what dashboard correctness requires.
- No infrastructure changes (Docker, queues, CI) — host commands already documented.
- No introduction of Playwright/browser automation into the default test path (keeps suite fast; Playwright usage is runtime-only for AFT enrichment).
- No reading/copying of secrets; no `.env` or `.agent/skills` changes in any slice.

## Sources

- `Modules/Prospects/Console/Commands/{SyncRbfaGraphqlCommand,SyncHockeyClubsCommand,SyncAftClubsCommand,SyncTpvClubsCommand,SyncValClubsCommand,SyncLbfaClubsCommand,SyncMasterCommand}.php`
- `Modules/Prospects/Jobs/{MasterSyncJob,ExecuteSyncJob}.php`
- `Modules/Prospects/Filament/Pages/SyncDashboardPage.php`
- `Modules/Prospects/Traits/{LogsSyncEvents,HandlesClubRegions}.php`
- `Modules/Prospects/Models/{Prospect,…}.php`, `Modules/Prospects/config/config.php`, root `config/rbfa.php`
- `openspec/config.yaml` (testing baseline, phase rules, active scope CLA-535)
- Parent audit: Engram observation 70 (`prospects-module-audit`, project `claesen-analytics`)