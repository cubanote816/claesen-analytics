# Prospects Ops Follow-up Specification

**Change**: `prospects-federation-refactor` (CLA-535) — Slice F
**Type**: Full spec (docs-only; zero application code)

## Purpose

Operational blockers and remaining data-source gaps discovered by this change are documented as follow-ups, and the new adapter architecture is recorded in the project's AI/handoff documentation — so future sessions and ops work can resume without re-deriving context.

## Requirements

### Requirement: Adapter architecture documented

`handoff.md` and `docs/ai/context-map.md` MUST be updated with: the `FederationDataSource` adapter architecture, the per-federation source table (RBFA GraphQL / AFTT PDF / Brussels CSV / scrapers), slice status for CLA-535, and the remaining gaps (AFPadel, Verenigingsregister, Sport Vlaanderen). No application code MAY change in this slice.

#### Scenario: Documentation reflects the refactor

- GIVEN the six slices of CLA-535 are implemented
- WHEN a reader opens `handoff.md` or `docs/ai/context-map.md`
- THEN the adapter architecture, slice outcomes, and known remaining gaps are described accurately

### Requirement: Ops follow-ups recorded with rationale

The following deferred items MUST be documented (in `handoff.md` and/or `docs/ai/known-risks.md`) with current blocker and next action:

1. **Verenigingsregister API key** — blocker: API key required (`publiek.verenigingen.vlaanderen.be`); next action: request key from Vlaamse overheid.
2. **Sport Vlaanderen dataset pinning** — blocker: exact sportclubs dataset URL not exposed on the portal; next action: pin the dataset URL on data.vlaanderen.be.
3. **AFPadel HTML scrape** — gap: Wallonia padel clubs uncovered (AFTT PDF covers table tennis only); next action: headless browser or scrape decision.

#### Scenario: Deferred items are discoverable

- GIVEN the change is closed
- WHEN ops or a future session looks up remaining Prospects data-source gaps
- THEN each deferred item has a current blocker and a next action recorded

## RED test seams

None — this slice changes documentation only (0 code lines, no test delta). Verification is a doc review: files exist, content matches the implemented architecture, and `git diff` for this slice contains no application-code changes. The scoped Prospects suite stays green (no code touched).

## Acceptance criteria

- `handoff.md` + `docs/ai/context-map.md` updated in the same work unit or a dedicated doc commit (per `openspec/config.yaml` phase rules).
- Three deferred items (Verenigingsregister, Sport Vlaanderen, AFPadel) recorded with blocker + next action.
- Zero application-code diff in this slice.
- Baseline preserved: 32 tests / 88 assertions stay green after this slice.