# Prospects Federation Contracts Specification

**Change**: `prospects-federation-refactor` (CLA-535) — Slice D
**Type**: Full spec (no canonical spec exists yet)
**Findings addressed**: proposal findings 11→D (pagination log lives in Slice A), 13 (external_id/contact_type drift), 14 (location duplicate on address change); LeadService orphan fix

## Purpose

All federation data sources sit behind one `FederationDataSource` contract with normalized `contact_type` and `external_id` conventions, so heterogeneous sources (API, PDF, CSV, scrape) become interchangeable and consistent. The `ProspectLocation` upsert key and the `LeadService` transaction boundary MUST stop producing duplicates and orphans.

## Requirements

### Requirement: FederationDataSource contract

A new interface `Modules/Prospects/Contracts/FederationDataSource.php` MUST define:

```php
public function fetchClubs(): array;  // @return array<int, array<string, mixed>> normalized club records
public function name(): string;       // federation code, e.g. 'RBFA', 'AFTT'
```

Adapters MUST be injectable via command constructors (the testable pattern already used by `SyncTpvClubsCommand`). Each command consumed by an adapter MUST NOT contain its own fetch logic inline.

#### Scenario: Contract conformance

- GIVEN every concrete source class in `Modules/Prospects/DataSource/`
- WHEN each is checked against `FederationDataSource`
- THEN it implements both `fetchClubs()` and `name()` and returns club arrays with the normalized field set (name, external_id, language, contact/location fields with normalized `contact_type`)

#### Scenario: Command consumes an injected adapter

- GIVEN a refactored command (RBFA is the first consumer)
- WHEN a test injects a stub/fake source
- THEN the command persists prospects from the stub without any real HTTP call

### Requirement: RBFA adapter wraps GraphQL logic

`Modules/Prospects/DataSource/RbfaGraphQLSource.php` MUST wrap the existing RBFA GraphQL discovery + enrichment logic behind the contract, preserving the province-series discovery, independence from the internal CAFCA ERP flow, and the Slice A hardening (timeout/retry, failure logging). The persisted-query hashes and existing source endpoint are unchanged. ("No CAFCA" is the ERP sync boundary the RBFA command already respects; there is no club-level CAFCA affiliation field to filter on.)

#### Scenario: RBFA adapter fetches clubs

- GIVEN a mocked GraphQL response sequence for discovery series and club enrichment
- WHEN `fetchClubs()` is called
- THEN normalized club records are returned using the existing `VL-`/`FR-` external_id scheme, sourced only from the RBFA GraphQL endpoint and never from the internal CAFCA ERP flow

#### Scenario: RBFA command refactor is behavior-preserving

- GIVEN `SyncRbfaGraphqlCommand` is refactored to consume `RbfaGraphQLSource`
- WHEN the scoped Prospects suite runs
- THEN all existing RBFA command tests (from Slices A/B) stay green with no data-output regression

### Requirement: contact_type normalization

All adapters and commands MUST use a single `contact_type` convention: `headquarters` for the club's main address, `venue_name` for playing/training locations. Existing divergent writes (RBFA/LBFA `headquarters` vs Hockey/TPV/VAL `venue_name` for equivalent data) MUST be aligned. Existing location rows MUST be migrated to the convention (migration precedent: `2026_04_05_163711_update_prospect_federation_prefixes.php`).

#### Scenario: Same kind of location, same contact_type

- GIVEN a club's main address is persisted by any source
- WHEN the `ProspectLocation` row is written
- THEN `contact_type` is `headquarters`; training/venue locations are `venue_name`

#### Scenario: Existing rows normalized

- GIVEN locations persisted before this change with divergent contact_type values
- WHEN the data migration runs
- THEN rows match the new convention without orphaning prospects or breaking mail-log lineage

### Requirement: external_id prefix convention documented and enforced

The external_id scheme MUST be documented and enforced consistently: `VL-<federation>-<id>`, `FR-<federation>-<id>`, `ARBH-<federation>-<id>`. ID-based sources (RBFA/Hockey/TPV) MUST keep stable source IDs; slug-based commands (VAL/LBFA/AFT) MUST document their slug scheme explicitly (or move toward ID-based identifiers where the source exposes one). New adapters MUST derive external_ids through a shared prefix helper, not per-command duplication.

#### Scenario: VAL slug external_id

- GIVEN a VAL club with slug `<slug>`
- WHEN the external_id is computed
- THEN it is `VL-<slug>` via the shared helper and matches the documented convention

#### Scenario: Convention is testable

- GIVEN a contract test over all adapters
- WHEN each returned club external_id is checked
- THEN it matches one of the documented prefixes exactly once

### Requirement: ProspectLocation keyed on prospect_id + external_id

`ProspectLocation::updateOrCreate` MUST be keyed on `['prospect_id', 'external_id']` (VAL currently keys on `['prospect_id', 'address']`). An address change MUST update the existing location row instead of creating a duplicate.

#### Scenario: Address change on an existing venue

- GIVEN a prospect has a venue location with external_id `X` and address changes
- WHEN the sync persists the location again
- THEN the existing row (prospect_id + external_id `X`) is updated
- AND no duplicate location row is created

### Requirement: LeadService orphan fix

`LeadService` MUST close the transaction boundary such that a unique-violation catch on Prospect creation does not leave an orphan `Prospect` record (or other partially written rows). The `LeadService` public API MUST remain stable (consumed by the Mailing module).

#### Scenario: Unique violation during lead handling

- GIVEN a lead flow where a Prospect insert hits a unique external_id violation
- WHEN the exception is caught
- THEN no orphan/partial Prospect row remains in the database
- AND the LeadService public API signature and behavior for Mailing callers are unchanged

## RED test seams (strict TDD — write before implementation, co-located)

| New test | Pattern reused | Covers |
|---|---|---|
| `Modules/Prospects/tests/Feature/FederationDataSourceContractTest.php` (new) | contract test iterating all `DataSource/*` classes | interface conformance, normalized field set, external_id prefix convention |
| `Modules/Prospects/tests/Feature/RbfaGraphQLSourceTest.php` (new) | `Http::fake()` mocked GraphQL sequence | adapter discovery/enrichment, independence from CAFCA ERP flow, timeout/retry preserved |
| `Modules/Prospects/tests/Feature/SyncRbfaGraphqlCommandTest.php` (extend) | `Http::fake()` + constructor-injected stub source | refactored command consumes adapter; behavior-preserving |
| `Modules/Prospects/tests/Feature/ProspectLocationKeyTest.php` (new) | `RefreshDatabase` model test | `['prospect_id','external_id']` key semantics; address-change update vs duplicate |
| `Modules/Prospects/tests/Feature/LeadServiceOrphanTest.php` (new) | `RefreshDatabase` + unique-violation forcing | no orphan Prospect on unique-violation catch |

## Acceptance criteria (mapped to findings)

- Finding 13 (scheme/contact_type drift) → contact_type normalization + external_id convention requirements + contract test.
- Finding 14 (location duplicate on address change) → ProspectLocation key requirement + address-change scenario.
- Proposal Slice D also fixes the LeadService transaction boundary → orphan-fix requirement + `LeadServiceOrphanTest`.
- Budget gate (proposal R7): if this slice exceeds the 400-line review budget, STOP and ask (ask-on-risk); pre-emptive split D1 (interface + RBFA adapter) / D2 (normalization + LeadService fix) is the sanctioned fallback — do not self-select chaining or `size:exception`.
- Baseline preserved: 32 tests / 88 assertions stay green after this slice.