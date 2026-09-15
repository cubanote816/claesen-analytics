# Prospects AFT Source Specification

**Change**: `prospects-federation-refactor` (CLA-535) — Slice C
**Type**: Full spec (no canonical spec exists yet)
**Findings addressed**: proposal finding 1 (AFT fake data)

## Purpose

The AFT command MUST stop shipping fake data. Wallonia table-tennis (AFTT) club coverage MUST come from the real AFTT annuaire PDF via a parseable source class; the two hardcoded sample clubs MUST be removed; AFPadel clubs remain an explicit documented gap (not faked).

## Requirements

### Requirement: AFTT PDF source replaces fake data

A new source class `Modules/Prospects/DataSource/AfttPdfSource.php` (name indicative; final name decided at implementation) MUST download and parse the AFTT annuaire PDF (`https://ep.aftt.be/assets/media/documents/annuaire/annuaire_complet.pdf`) using `smalot/pdfparser`, and MUST return normalized club records (name, address fields, language `fr`) suitable for `Prospect::updateOrCreate` persistence. The source MUST support injecting the PDF body or a PSR-7/stream client for testing.

#### Scenario: Parsing the annuaire PDF

- GIVEN a representative PDF fixture (subset of the 356-page annuaire) is provided
- WHEN the source parses it
- THEN it returns a non-trivial set of club records (orders of magnitude larger than 2)
- AND each record has at least a club name and language `'fr'`

#### Scenario: Unparseable or failed download

- GIVEN the PDF download fails or the body is not a valid PDF
- WHEN the source attempts to parse
- THEN it raises/logs a failure (no silent empty-result success) and no fake clubs are upserted

### Requirement: Fake hardcoded clubs are removed

`SyncAftClubsCommand` MUST NOT contain the hardcoded fallback array (`TC de Wavre`, `Royal Leopold Club`). The "Falling back to local data extract" path MUST be removed. The two existing fake Prospects MUST be soft-retired (never hard-deleted, since mail-log lineage references must survive) via a dedicated migration, per design.md decision D7: no trustworthy identity mapping exists between the fabricated tennis clubs and any real AFTT (table tennis) club number, so re-stamping them as "replaced in-place" real AFTT data would fabricate identity a second time.

#### Scenario: CSRF scrape failure no longer fabricates clubs

- GIVEN the AFT command runs
- WHEN any upstream failure occurs
- THEN zero fake clubs are persisted and the run is logged as failed or degraded, never as successful with sample data

#### Scenario: Fake prospects retired, not orphaned or hard-deleted

- GIVEN the production dataset contains the 2 fake AFT prospects referenced by prior mail logs
- WHEN the soft-retire migration runs
- THEN the 2 fake rows get `unsubscribed_at` set (excluded from `scopeSubscribed` mail targeting) while every historical reference and row remains intact

### Requirement: AFPadel stays a scrape path, documented as a gap

The refactored AFT command MAY retain the existing AFPadel (`afpadel.be/les-clubs/`) scrape path for padel clubs. The AFTT PDF covers table tennis only; the Wallonia padel gap MUST be documented (Slice F) rather than filled with fabricated data.

#### Scenario: Padel clubs unaffected

- GIVEN the refactored command runs
- WHEN AFPadel scrape data is available
- THEN padel prospect handling behaves as before (no regression); if the scrape fails, the failure is logged, never faked

## RED test seams (strict TDD — write before implementation, co-located)

| New test | Pattern reused | Covers |
|---|---|---|
| `Modules/Prospects/tests/Feature/AfttPdfSourceTest.php` (new) | fixture-file based unit test (PDF fixture committed under `Modules/Prospects/tests/fixtures/`) | parse behavior, club count/fields, unparseable-input failure |
| `Modules/Prospects/tests/Feature/SyncAftClubsCommandTest.php` (extend, from Slice B first pass) | Guzzle `MockHandler` constructor injection (as `SyncTpvClubsCommandTest`) — source injected via constructor | adapter integration, fake-array removal, replace-in-place behavior, TLS verify enabled |

Note: no network access in tests — the PDF body is always injected as a fixture/stream; the command suite stays on `QUEUE_CONNECTION=sync` with no Playwright (per proposal non-goals).

## Acceptance criteria (mapped to findings)

- Finding 1 (AFT fake data) → all three requirements above; `AfttPdfSourceTest` + `SyncAftClubsCommandTest` RED→GREEN.
- Risk gate (proposal R3): retention decision is "replace in-place" — no hard-delete of the 2 fake prospects.
- Risk note: `smalot/pdfparser` is a new Composer dependency; adding it is part of this slice.
- Baseline preserved: 32 tests / 88 assertions stay green after this slice.