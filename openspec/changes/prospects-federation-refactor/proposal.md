# SDD Proposal — prospects-federation-refactor (CLA-535)

**Status**: `proposed`  
**Phase**: `sdd-proposal`  
**Ticket**: CLA-535 — "Prospects: federation data-source refactor + audit fixes"  
**Branch**: `audit/prospects-module`  
**Module**: `Modules/Prospects`  
**Artifact store**: `openspec`

---

## Executive Summary

The Prospects module acquires Belgian sports-club data from six federation sources via heterogeneous artisan commands — one GraphQL API, four HTML scrapers, and one command that produces two fake hardcoded clubs (no real sync). The audit (explore.md, Engram obs 70) identified 16 findings across data integrity, error handling, and consistency. Research (research.md, Engram obs 73) verified concrete replacement/additional sources with live endpoints, schemas, and licenses.

This proposal defines an **incremental per-federation adapter architecture** (`FederationDataSource` interface) with six reviewable slices in dependency order. Each slice stays ≤400 changed lines; delivery uses `ask-on-risk` with a mandatory stop if any slice exceeds budget. All 16 findings are addressed or explicitly deferred with rationale.

---

## 1. Why — Business Problem

| Problem | Impact |
|---|---|
| **AFT sync ships fake data.** The command falls back to 2 hardcoded sample clubs (`TC de Wavre`, `Royal Leopold Club`) when its CSRF scrape fails, which is always. Wallonia tennis/padel coverage is effectively fictional. | ~45,000 affiliated tennis/padel players in Wallonia are invisible; mailing campaigns miss their primary club context; pipeline leads are fabricated. |
| **No Brussels coverage.** No adapter acquires clubs in the Brussels-Capital Region. Cross-sport dataset exists but is unused. | Brussels clubs (~200+ across sports) are absent from prospects. |
| **Hockey language bug mislabels FR clubs.** `$language = ($federation === 'LFH') ? 'fr' : 'nl'` never matches because $federation values are `FR-LFH`, `VL-VHL`, `ARBH-KBHB`. Every francophone hockey club is stored with `language = 'nl'`. | Mail campaigns targeting FR-speaking hockey players deliver Dutch content. Segmentation is broken. |
| **Inconsistent error handling leaves syncs stuck.** Master chain halts on any thrown exception, remaining federations never run, `SyncHistory` stays `running` forever. Dashboard shows an active master indefinitely; `sync_all` stays disabled. | Operational downtime: manual intervention required to reset stuck history rows. Support burden. |
| **`records_count` reports attempts, not successes.** Loop iterations are counted, not persisted clubs. Partial failures are indistinguishable from full success on the dashboard. | False confidence in sync health; missed failures compound over time. |
| **Several scrapers fragile, silent on failure.** TLS verification disabled in 3 scrapers (MITM risk). VAL and RBFA skip failed clubs silently. | Data quality degrades silently — no alert, no log, no dashboard signal. |

---

## 2. What Changes — Architecture

### FederationDataSource interface

A new `Contracts/FederationDataSource.php` with a single method:

```php
interface FederationDataSource
{
    /**
     * @return array{id: string, name: string, ...normalized fields}
     */
    public function fetchClubs(): array;

    public function name(): string;  // federation code, e.g. 'RBFA', 'AFT'
}
```

Each adapter wraps one data source (API, PDF, CSV, scrape) behind this interface. Existing commands are refactored one at a time to consume the adapter. Adapters are injected via the command constructor (testable pattern, as already demonstrated by `SyncTpvClubsCommand`).

### Per-slice surgical fixes (readable, independent)

Beyond the interface, each slice applies targeted fixes to the **existing code paths** so that scrapers still in use are observable and correct while adapters are introduced.

### Sync ordering after refactor

Existing `MasterSyncJob` chain order (LBFA → AFT → Hockey → TPV → VAL → RBFA) is preserved. No orchestration rewrite.

---

## 3. Scope Slices (6 slices, ≤400 lines each)

### Slice A — Error-handling / Observability fixes (findings 6, 7, 8, 9, 10, 16)

**BLAM** (behavior, risk, scope): Minimal. Touches only existing sync paths. Every change is testable with the existing `LogsSyncEvents` trait pattern.

| Finding | Fix |
|---|---|
| **6** VAL per-club exception swallowed | `catch (\Exception $e)` → `failSyncLog(...)` + re-throw or log to `SyncHistory` |
| **7** `ExecuteSyncJob` exit code ignored | Check `Artisan::call()` exit code; on non-zero mark job failed instead of always "satisfactoriamente" |
| **8** Master chain failure handling | Wrap chain dispatch: if any job fails, mark master `SyncHistory` as failed; ensure `SendMasterSyncFinishedNotificationJob` always fires via `Bus::chain`→`catch` |
| **9** `records_count` = attempts not persisted | Change to count after `updateOrCreate` success; log vs. persisted breakdown |
| **10** RBFA enrichment timeout/retry | Add `->timeout(30)->retry(3, 5000)` to per-club POST |
| **16** Mixed HTTP stacks | RBFA/val/LBFA: add per-club error logging on failure paths; add User-Agent consistency |

**Files touched**: `Jobs/ExecuteSyncJob.php`, `Jobs/MasterSyncJob.php`, `Traits/LogsSyncEvents.php`, `SyncRbfaGraphqlCommand.php`, `SyncValClubsCommand.php`, `SyncHockeyClubsCommand.php`, `SyncTpvClubsCommand.php`, `SyncLbfaClubsCommand.php`, `SyncAftClubsCommand.php`.

**Estimated delta**: ~280–320 lines.  
**Tests**: Extend `LogsSyncEventsTest` for crash-safe flush; new `ExecuteSyncJobTest` (exit-code); new `MasterSyncJobTest` (chain failure).  
**Risk**: Very low — all changes additive (logs, checks) without data transformation.

---

### Slice B — Critical data-correctness fixes (findings 2, 3, 4, 5, 12, 15)

**BLAM**: Medium. Changes existing Prospect records (language, external_id, region). Requires migration precedent from `2026_04_05_163711_update_prospect_federation_prefixes.php`.

| Finding | Fix |
|---|---|
| **2** Hockey language mapping | `($federation === 'FR-LFH') ? 'fr' : 'nl'` — match actual prefix; add failing test first |
| **3** Hockey ARBH prefix | Ensure `ARBH-KBHB` external_id uses consistent prefix scheme; align with `VL-`/`FR-` pattern |
| **4** RBFA uncomment Vlaams-Brabant + Brussel | Uncomment province series in `config/rbfa.php`; add test asserting discovery covers these provinces |
| **5** RBFA discovery silent failures | Log failed series responses via `failSyncLog`; add `SyncHistory` error entry |
| **12** VAL `return false` in DomCrawler `each()` | Replace `return false` with actual filter/break; verify Terreinen parsing works |
| **15** Region magic number / duplicate prefix | AFT: use `getFallbackRegionId()` instead of `region_id ?? 11`; RBFA: reuse `HandlesClubRegions` postal mapping instead of hardcoded province-name array |

**Files touched**: `SyncHockeyClubsCommand.php`, `SyncRbfaGraphqlCommand.php`, `SyncAftClubsCommand.php`, `SyncValClubsCommand.php`, `config/rbfa.php`, `Traits/HandlesClubRegions.php` (minor).

**Estimated delta**: ~250–320 lines.  
**Tests**: New `SyncHockeyClubsCommandTest` (language fix + external_id prefix); new `SyncRbfaGraphqlCommandTest` (province coverage + silent failure); `SyncValClubsCommandTest` (Terreinen parsing).  
**Risk**: **Medium** — `language` flip changes mail segmentation (coordinate with Mailing team before merge). Hockey external_id renames may affect existing mail-log lineage (see migration risk below).

---

### Slice C — AFT fake-data replacement with AFTT PDF parser (finding 1)

**BLAM**: Replaces the only fake-data command with a real source. New `AftPdfSource` adapter class.

| Artifact | Detail |
|---|---|
| **Source** | AFTT annuaire PDF (`https://ep.aftt.be/assets/media/documents/annuaire/annuaire_complet.pdf`) — 1.48 MB, 356 pages, HTTP 200, no auth, updated 2026-09-12 |
| **Parser** | `smalot/pdfparser` (PHP) — PDF 1.4 extractable; already a common Composer dependency candidate |
| **Adapter** | `DataSource/AfttPdfSource.php` implements `FederationDataSource` |
| **Command** | `SyncAftClubsCommand.php` refactored to inject the adapter (fallback to existing scrape path for AFPadel clubs, fake hardcoded array removed) |
| **License** | AFTT site terms — parsing for internal CRM use is standard practice |

**Note on AFPadel**: The current AFT command conflates tennis+padel Wallonia. The PDF covers only AFTT (table tennis). AFPadel clubs (`afpadel.be/les-clubs/`, HTML) remain a scrape target. This slice replaces only the fake tennis data; padel is documented as a remaining gap.

**Estimated delta**: ~300–350 lines.  
**Tests**: `AfttPdfSourceTest` (parse PDF fixture, verify club count + fields); `SyncAftClubsCommandTest` (adapter integration).  
**Risk**: **Medium** — deleting 2 fake clubs removes 2 existing prospects (retention decision: delete or re-stamp as real AFTT data? Recommended: replace in-place so existing mail references survive).

---

### Slice D — FederationDataSource interface + RBFA adapter (findings 11, 13)

**BLAM**: Introduces the interface and the first adapter. Largest slice — may exceed 400 lines (ask-on-risk gate).

| Artifact | Detail |
|---|---|
| **Interface** | `Contracts/FederationDataSource.php` |
| **RBFA adapter** | `DataSource/RbfaGraphQLSource.php` wraps existing GraphQL logic with timeout/retry hardening (fix finding 10, already partially addressed in Slice A) |
| **RBFA command refactored** | `SyncRbfaGraphqlCommand.php` injects adapter; preserves province-series discovery logic |
| **contact_type normalization** | Align `contact_type` across all adapters to a single convention (`headquarters` for main address, `venue_name` for playing locations) |
| **external_id prefix convention** | Document and enforce: `VL-<federation>-<id>`, `FR-<federation>-<id>`, `ARBH-<federation>-<id>` |
| **LeadService orphan fix** | Fix transaction boundary so a unique-violation catch does not leave orphan `Prospect` records |

**Estimated delta**: ~350–450 lines. **Potential budget breaker**.  
**Tests**: `FederationDataSourceTest` (contract test); `RbfaGraphQLSourceTest` (mocked responses); `SyncRbfaGraphqlCommandTest` (refactored command).  
**Risk**: **Medium** — interface + contact_type renames can orphan prospects + mail-log lineage. Migration precedent exists (`2026_04_05_163711`). Must run a data migration or handle via `updateOrCreate` external_id fallback logic.

---

### Slice E — Brussels cadastre CSV adapter (new coverage)

**BLAM**: New adapter for Brussels-Capital Region, currently uncovered. CC-BY 2.0 license, 166 KB, no auth.

| Artifact | Detail |
|---|---|
| **Source** | Brussels sports cadastre CSV (`backend.datastore.brussels/…/infra_export_opendata_1.csv`) |
| **Adapter** | `DataSource/BrusselsCadastreSource.php` implements `FederationDataSource` — maps CSV rows to Prospect fields |
| **New command** | `prospects:sync-brussels-clubs` (or extend master) |
| **License** | CC-BY 2.0 — requires attribution; add attribution line to dashboard |
| **Caveat** | CSV header has typo `Plca_street_nl` — consumer uses literal header string |
| **Coverage gap** | This is sports **infrastructure** (complexes, gyms), not a full club roster. Auxiliary enrichment — federated clubs from RBFA/AFT/Hockey/etc. that happen in Brussels get their primary address from their federation source; this CSV adds venue addresses for complexes not on any federation list. |

**Estimated delta**: ~250–300 lines.  
**Tests**: `BrusselsCadastreSourceTest` (parse fixture CSV, verify address mapping).  
**Risk**: Low — new adapter, no regression risk to existing commands.

---

### Slice F — Ops follow-up (not code)

**BLAM**: No code changes. Documented as a non-goal for this change.

| Item | Action |
|---|---|
| **Verenigingsregister API key** | Request API key from Vlaamse overheid (`publiek.verenigingen.vlaanderen.be/docs/api-documentation.html`) |
| **Sport Vlaanderen dataset pinning** | Follow up on data.vlaanderen.be to pin exact sportclubs dataset URL |
| **AFPadel HTML scrape** | Remaining gap: Wallonia padel clubs still need headless browser or scrape |
| **Documentation** | Update `docs/ai/context-map.md` and `handoff.md` with new adapter architecture |

---

## 4. Findings addressed / deferred matrix

| # | Finding | Addressed in | Status |
|---|---|---|---|
| 1 | AFT fake data | Slice C | ✅ Fixed |
| 2 | Hockey language bug | Slice B | ✅ Fixed |
| 3 | Hockey ARBH prefix | Slice B | ✅ Fixed |
| 4 | RBFA Brussel/Vlaams-Brabant excluded | Slice B | ✅ Fixed |
| 5 | RBFA discovery silent failures | Slice B | ✅ Fixed |
| 6 | VAL per-club exception swallowed | Slice A | ✅ Fixed |
| 7 | Artisan::call exit code ignored | Slice A | ✅ Fixed |
| 8 | Master chain failure handling | Slice A | ✅ Fixed |
| 9 | records_count = attempts not persisted | Slice A | ✅ Fixed |
| 10 | RBFA enrichment no timeout/retry | Slice A (+ D) | ✅ Fixed |
| 11 | TPV pagination cap silent truncation | Slice A (add log) | ⚠️ Mitigated (log truncation, no structural fix) |
| 12 | VAL Terreinen parse broken | Slice B | ✅ Fixed |
| 13 | contact_type/external_id drift | Slice D | ✅ Fixed (normalized) |
| 14 | Location duplicate on address change | Slice D | ✅ Fixed (use `['prospect_id', 'external_id']` key) |
| 15 | Region inference duplicated | Slice B | ✅ Fixed |
| 16 | Inconsistent HTTP stacks + error granularity | Slice A | ✅ Fixed |
| — | Verenigingsregister integration | Slice F (ops) | ⏳ Deferred — API key blocker |
| — | Sport Vlaanderen dataset | Slice F (ops) | ⏳ Deferred — URL not yet pinned |
| — | AFPadel headless browser | Slice F (ops) | ⏳ Deferred — separate concern |

---

## 5. Non-goals

| Non-goal | Rationale |
|---|---|
| **No MasterSyncJob orchestration rewrite** | Chain order preserved; only failure-handling fixed (Slice A). |
| **No Filament UI changes beyond SyncDashboardPage** | Dashboard data model is correct; only correctness of displayed numbers is in scope. |
| **No LeadService public API change** | `LeadService` is consumed by Mailing module; interface stays stable. Orphan fix is internal. |
| **No Verenigingsregister / MAGDA integration** | API key acquisition is an operational dependency; documented as ops follow-up (Slice F). |
| **No Sport Vlaanderen dataset pinning** | URL not yet exposed by portal; secondary source behind Verenigingsregister. |
| **No CAFCA flow changes** | The RBFA command is independent of the internal CAFCA ERP sync ("No CAFCA" is that boundary, not a club filter); preserve. |
| **No Playwright in default test path** | Headless browser is runtime-only for AFT enrichment; test suite stays `QUEUE_CONNECTION=sync`. |
| **No infrastructure/CI changes** | Docker, queues, CI pipeline untouched. |
| **No single nationwide API pursuit** | Confirmed absent; federation/region fragmentation is structural. |

---

## 6. Risks

| # | Risk | Impact | Mitigation |
|---|---|---|---|
| R1 | `external_id`/`contact_type` renames orphan prospects + mail-log lineage | Lost commercial leads; broken mail campaign references | Use `updateOrCreate` on external_id; migration precedent `2026_04_05_163711`; run data migration alongside Slice D |
| R2 | Hockey language flip changes mail segmentation | FR hockey clubs start receiving NL content (or vice versa after fix) | Coordinate merge timing with Mailing team; deploy Slice B during low-campaign period |
| R3 | Removing 2 fake AFT prospects | 2 prospects deleted (or re-stamped) — retention concern | Decision: replace in-place so existing mail references survive; do not hard-delete |
| R4 | 5 of 6 commands lack co-located tests | Slice A test load inflates because error-handling tests require command test setup | Add constructor injection first (as TPV already does); test infrastructure in Slice D |
| R5 | RBFA persisted-query hashes rotate | GraphQL adapter breaks silently | Add HTTP 400/500 logging to adapter; dashboard shows failed sync; documented risk for `SyncRbfaGraphqlCommandTest` |
| R6 | Queue semantics: sync vs real queue | Chain failure handling differs under real queue (jobs run asynchronously) | `Bus::fake`/`Queue::fake` feature tests Slice A; production observation period after deploy |
| R7 | Slice D may exceed 400-line review budget | Delivery gate triggers `ask-on-risk` | Pre-emptively split: interface + RBFA adapter as D1, contact_type/external_id normalization + LeadService fix as D2 |

---

## 7. Acceptance Criteria

1. **All 16 findings explicitly addressed or deferred** with rationale in the proposal matrix.
2. **32 existing tests / 88 assertions stay green** after every slice.
3. **New tests co-located per adapter** under `Modules/Prospects/tests/` confirming RED → GREEN TDD cycle.
4. **Full Prospects suite green** (`DB_HOST=127.0.0.1 DB_PORT=3308 php artisan test --filter=Prospects Modules/Prospects/tests`) before declaring any slice complete.
5. **Full module suite unfiltered** green before phase closure.
6. **`handoff.md` updated** with new adapter architecture, slice status, and remaining gaps.
7. **Engram memory updated** per session-close protocol (topic_key `sdd/prospects-federation-refactor/proposal` → `apply` and later slices).

---

## 8. Delivery — Review Workload Forecast

| Slice | Estimated delta | Review budget | Gate |
|---|---|---|---|
| A — Error-handling | ~280–320 lines | ✅ ≤400 | ask-on-risk (clean) |
| B — Data-correctness fixes | ~250–320 lines | ✅ ≤400 | ask-on-risk (clean) |
| C — AFT PDF adapter | ~300–350 lines | ✅ ≤400 | ask-on-risk (clean) |
| D — Interface + RBFA adapter | ~350–450 lines | ⚠️ **May exceed** | ask-on-risk → **stop**; propose split D1/D2 |
| E — Brussels CSV adapter | ~250–300 lines | ✅ ≤400 | ask-on-risk (clean) |
| F — Ops follow-up | 0 lines (docs) | ✅ N/A | N/A |

**Strategy**: `ask-on-risk`. If any slice exceeds 400 lines, pause and ask the user before proceeding. Do not self-select chaining or `size:exception`.

---

## 9. Artifacts

| Artifact | Path |
|---|---|
| **This proposal** | `openspec/changes/prospects-federation-refactor/proposal.md` |
| **Explore (audit findings)** | `openspec/changes/prospects-federation-refactor/explore.md` |
| **Research (data sources)** | `openspec/changes/prospects-federation-refactor/research.md` |
| **Config** | `openspec/config.yaml` |
| **Engram (audit)** | Observation 70 (`prospects-module-audit`) |
| **Engram (research)** | Observation 73 (`prospects-data-sources-research`) |

---

## 10. Skill Resolution

- **Paths injected**: `gentle-ai` (parent-injected harness discipline), plus standard Gentle AI skill set from `.pi/agent/skills/`
- **Resolution**: `paths-injected`
- **Loaded**: `gentle-ai` (for harness protocol, OpenSpec discipline, TDD, review budget)

---

## Key Learnings

1. The 16 audit findings for the Prospects module split cleanly into three categories (error-handling, data-correctness, consistency) that map to independent review slices with no cross-slice merge conflicts.
2. The AFTT annuaire PDF at 1.48 MB / 356 pages replaces fake hardcoded tennis data and is parseable via `smalot/pdfparser` with no auth required, but does not cover Wallonia padel (AFPadel), which remains an open gap.
3. The five commands lacking co-located tests (RBFA, Hockey, AFT, VAL, LBFA) inflate test-load for the first error-handling slice because testing error paths requires constructor-injection refactoring first.
4. Hockey language bug (`$federation === 'LFH'` never matching `FR-LFH`) is a one-character data corruption that silently affects all francophone hockey prospects and requires coordinated deployment with the Mailing team.
5. Slice D (interface + RBFA adapter at ~350–450 lines) is the only budget risk under a 400-line review cap and can be pre-split into interface definition and adapter implementation as independent sub-slices.