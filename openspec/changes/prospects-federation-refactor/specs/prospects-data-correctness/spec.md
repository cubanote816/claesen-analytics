# Prospects Data-Correctness Specification

**Change**: `prospects-federation-refactor` (CLA-535) — Slice B
**Type**: Full spec (no canonical spec exists yet)
**Findings addressed**: proposal findings 2, 3, 4, 5, 12 (VAL Terreinen), 15

## Purpose

Prospect records acquired by the existing scrapers MUST be factually correct: hockey clubs carry the right language and a consistent external_id prefix scheme, RBFA discovery covers all Belgian provinces including Vlaams-Brabant and Brussel and surfaces failures, VAL parses the Terreinen section correctly, and region inference reuses the canonical `HandlesClubRegions` mapping instead of per-command magic.

## Requirements

### Requirement: Hockey language mapping matches actual federation codes

`SyncHockeyClubsCommand` MUST derive `language` from the actual federation values (`FR-LFH`, `VL-VHL`, `ARBH-KBHB`). A francophone club (`FR-LFH`) MUST be stored with `language = 'fr'`; Flemish clubs with `language = 'nl'`. The dead comparison `$federation === 'LFH'` MUST be removed.

#### Scenario: Francophone hockey club

- GIVEN the hockey sync processes a club with federation `FR-LFH`
- WHEN the Prospect is created or updated
- THEN `language` equals `'fr'`

#### Scenario: Flemish hockey club

- GIVEN the hockey sync processes a club with federation `VL-VHL`
- WHEN the Prospect is created or updated
- THEN `language` equals `'nl'`

### Requirement: Hockey external_id prefix scheme is consistent

The hockey command MUST build external_ids with a single, deduplicated prefix helper consistent with the `VL-`/`FR-`/`ARBH-` scheme (e.g. `FR-LFH-<id>`, `VL-VHL-<id>`, `ARBH-KBHB-<id>`), without duplicating prefix logic at multiple sites in the command.

#### Scenario: ARBH club external_id

- GIVEN a hockey club with federation `ARBH-KBHB`
- WHEN the Prospect external_id is computed
- THEN it uses the `ARBH-` prefixed scheme exactly once and matches the documented convention from Slice D (prefix table)

### Requirement: RBFA discovery covers Vlaams-Brabant and Brussel

`config/rbfa.php` MUST include the currently commented-out province series for Vlaams-Brabant and Brussel, and discovery MUST iterate the full configured series list. A test MUST assert that the discovery series set covers these provinces.

#### Scenario: Province catalog includes Brussels

- GIVEN `config/rbfa.php` is loaded
- WHEN the RBFA discovery loop builds its series list
- THEN Vlaams-Brabant and Brussel series are included alongside the existing provinces

#### Scenario: RBFA external_id scheme unaffected for existing provinces

- GIVEN the newly enabled series return clubs
- WHEN clubs are persisted
- THEN they use the existing `VL-`/`FR-` prefix scheme unchanged

### Requirement: RBFA discovery failures are surfaced

The RBFA discovery loop MUST NOT silently skip failed or unparseable series responses. Any non-successful series response MUST be logged via `failSyncLog(...)` (or `logSyncEvent(..., 'error', ...)`) and reflected in the `SyncHistory` error state.

#### Scenario: A province series request fails

- GIVEN discovery issues a series POST that returns a non-2xx or unparseable response
- WHEN the loop handles it
- THEN an error log entry for that series exists in SyncHistory
- AND the sync does not present a province-wide outage as a fully successful run

### Requirement: VAL Terreinen parsing works

`SyncValClubsCommand` MUST NOT use `return false` inside a DomCrawler `each()` to break iteration (which aborts the whole crawl). Filtering/breaking MUST use the appropriate Crawler constructs, and the "Terreinen" (training/venue locations) section MUST parse into `ProspectLocation` rows.

#### Scenario: Club page with Terreinen section

- GIVEN a VAL club detail page containing a Terreinen section
- WHEN the club is synced
- THEN the training/venue locations are persisted as `ProspectLocation` rows
- AND other sections after Terreinen are still parsed

### Requirement: Region inference reuses the canonical mapping

- `SyncAftClubsCommand` MUST use `HandlesClubRegions::getFallbackRegionId()` instead of the hardcoded `region_id ?? 11` magic number.
- `SyncRbfaGraphqlCommand` MUST reuse `HandlesClubRegions` (postal-code mapping / shared helpers) instead of a duplicated hardcoded province-name array for Flanders-vs-Wallonia inference.

#### Scenario: AFT club without region

- GIVEN an AFT-sourced club whose region cannot be inferred
- WHEN the Prospect is persisted
- THEN the fallback region id comes from `getFallbackRegionId()` (not the literal `11`)

#### Scenario: RBFA region inference

- GIVEN an RBFA club postal code
- WHEN region inference runs
- THEN the resulting region matches what `HandlesClubRegions` mapping would produce (no divergent per-command logic)

## RED test seams (strict TDD — write before implementation, co-located)

| New test | Pattern reused | Covers |
|---|---|---|
| `Modules/Prospects/tests/Feature/SyncHockeyClubsCommandTest.php` (new) | Guzzle `MockHandler` constructor injection (as `SyncTpvClubsCommandTest`) | language mapping (`FR-LFH` → `fr`), external_id prefix scheme, per-club error logging |
| `Modules/Prospects/tests/Feature/SyncRbfaGraphqlCommandTest.php` (extend, from Slice A) | `Http::fake()` | province catalog coverage (Vlaams-Brabant + Brussel), discovery failure logging, region inference via `HandlesClubRegions` |
| `Modules/Prospects/tests/Feature/SyncValClubsCommandTest.php` (extend, from Slice A) | `Http::fake()` | Terreinen parsing into ProspectLocation |
| `Modules/Prospects/tests/Feature/SyncAftClubsCommandTest.php` (new, first pass) | Guzzle `MockHandler` injection | `getFallbackRegionId()` usage (full AFTT replacement lands in Slice C) |

## Acceptance criteria (mapped to findings)

- Finding 2 (hockey language dead code) → language requirement + `SyncHockeyClubsCommandTest` scenario "Francophone hockey club".
- Finding 3 (hockey ARBH prefix) → prefix scheme requirement + "ARBH club external_id" scenario.
- Finding 4 (RBFA excluded provinces) → coverage requirement + "Province catalog includes Brussels" scenario.
- Finding 5 (RBFA silent discovery failures) → surfaced-failures requirement + "A province series request fails" scenario.
- Finding 12 (VAL Terreinen `return false`) → parsing requirement + "Club page with Terreinen section" scenario.
- Finding 15 (region duplication / magic number) → canonical mapping requirement + both region scenarios.
- Risk gates: `language` flip changes Mailing segmentation — coordinate merge timing with the Mailing team; `external_id` scheme alignment must not orphan existing hockey prospects (`updateOrCreate` on external_id; migration precedent `2026_04_05_163711`).
- Baseline preserved: 32 tests / 88 assertions stay green after this slice.