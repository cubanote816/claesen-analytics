# Prospects Brussels Cadastre Specification

**Change**: `prospects-federation-refactor` (CLA-535) — Slice E
**Type**: Full spec (no canonical spec exists yet)
**Findings addressed**: proposal Slice E (new coverage — Brussels-Capital Region)

## Purpose

Brussels-Capital Region sports infrastructure becomes available to Prospects via an auxiliary CSV adapter. This is sports **infrastructure** (complexes, school gyms) — auxiliary venue-address enrichment — not a full club roster; federation sources remain primary for club records.

## Requirements

### Requirement: BrusselsCadastreSource parses the official CSV

A new source class `Modules/Prospects/DataSource/BrusselsCadastreSource.php` MUST implement `FederationDataSource` and parse the Brussels sports cadastre CSV (`backend.datastore.brussels/.../infra_export_opendata_1.csv`, CC-BY 2.0, 166 KB, no auth). It MUST map CSV rows to Prospect fields (name_fr/name_nl, street/city/zipcode) and map regions via the canonical `HandlesClubRegions` mapping (no per-source region logic).

#### Scenario: Parsing the cadastre CSV

- GIVEN a representative CSV fixture (copied subset of the verified 166 KB export) is provided
- WHEN the source parses it
- THEN one normalized record per data row is returned with at least a name and an address
- AND region inference goes through `HandlesClubRegions` (Brussels rows map to the Brussels region)

#### Scenario: CSV header typo is consumed literally

- GIVEN the CSV header contains the typo `Plca_street_nl` (should be `Place_street_nl`)
- WHEN the source maps the Dutch street column
- THEN it reads the literal header string `Plca_street_nl` (not an assumed corrected name) and extracts the value correctly

### Requirement: New sync command with history and attribution

A new artisan command `prospects:sync-brussels-clubs` MUST sync the cadastre source into Prospects (or `ProspectLocation` enrichment), participate in `LogsSyncEvents` lifecycle (running → completed/failed, records_count = persisted), and MAY be appended to the `MasterSyncJob` chain. The CC-BY 2.0 attribution MUST be included (attribution line on the sync dashboard and/or module docs).

#### Scenario: First Brussels sync run

- GIVEN the command runs against the injected cadastre source
- WHEN the sync completes
- THEN a `SyncHistory` row exists for `prospects:sync-brussels-clubs` with `records_count` equal to persisted rows and correct lifecycle transitions

#### Scenario: Attribution visible

- GIVEN the sync dashboard renders
- WHEN the Brussels source card/data is displayed
- THEN the CC-BY 2.0 attribution for the Brussels cadastre is present

### Requirement: Auxiliary-only semantics

The cadastre data MUST NOT overwrite a club's federation-sourced primary address. Federated clubs (RBFA/AFT/Hockey/TPV/VAL/LBFA) that happen to be located in Brussels keep their primary address from their federation source; the CSV adds venue/complex addresses for infrastructure not on any federation list.

#### Scenario: Federated club in Brussels

- GIVEN a club already persisted from a federation source with a `headquarters` address
- WHEN the Brussels cadastre sync runs
- THEN the federation-sourced primary address is not overwritten by cadastre data

#### Scenario: Infrastructure-only row

- GIVEN a cadastre row describing a complex not on any federation list
- WHEN the sync persists it
- THEN it is stored as venue/infrastructure data per the auxiliary semantics (not as a fake club roster entry)

## RED test seams (strict TDD — write before implementation, co-located)

| New test | Pattern reused | Covers |
|---|---|---|
| `Modules/Prospects/tests/Feature/BrusselsCadastreSourceTest.php` (new) | fixture-file based test (CSV fixture committed under `Modules/Prospects/tests/fixtures/`) | CSV parsing, header-typo handling, region mapping via `HandlesClubRegions` |
| `Modules/Prospects/tests/Feature/SyncBrusselsClubsCommandTest.php` (new) | constructor-injected stub source (as `SyncTpvClubsCommandTest`) + `LogsSyncEvents` lifecycle assertions | command integration, records_count persisted-based, auxiliary semantics (no overwrite of federation addresses) |

## Acceptance criteria (mapped to findings)

- New coverage gap (proposal §1 "No Brussels coverage") → all three requirements above; both RED tests GREEN.
- Research constraint: literal `Plca_street_nl` header string (research.md verified schema) — covered by "CSV header typo is consumed literally".
- License obligation: CC-BY 2.0 attribution present (dashboard/docs).
- Risk profile: low — new adapter, no regression to existing commands; the six existing command tests stay green.
- Baseline preserved: 32 tests / 88 assertions stay green after this slice.