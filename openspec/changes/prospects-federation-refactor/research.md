# Research — Prospects Federation Data-Source Alternatives

**Change**: `prospects-federation-refactor` (CLA-535)
**Date**: 2026-09-13
**Method**: Inline web research (SDD runtime grants no evidence to the research subagent; parent performed verification with `web_search` + `fetch_content` + `curl`).
**Status**: Verified — endpoints live, formats confirmed, licenses recorded.

## Verified sources

### 1. AFTT Annuaire (Wallonia table tennis) — REPLACES fake AFT data

| Field | Value |
|-------|-------|
| URL | `https://ep.aftt.be/assets/media/documents/annuaire/annuaire_complet.pdf` |
| HTTP | 200 OK, `application/pdf` |
| Size | 1,482,706 bytes (1.48 MB) |
| Pages | 356 |
| Last-Modified | 2026-09-12 (updated yesterday — actively maintained) |
| Auth | None (public) |
| License | AFTT site terms (verify before bulk redistribution; parsing for internal CRM use is standard) |
| Parseable | Yes — PDF 1.4, extractable via `smalot/pdfparser` (PHP) or `pdftotext` |

**Replaces**: `SyncAftClubsCommand.php` hardcoded 2-club array (`TC de Wavre`, `Royal Leopold Club`) — currently the only "data" for FR tennis/padel.

**Note**: AFTT = table tennis specifically. AFPadel (padel Wallonia) has a separate club list at `afpadel.be/les-clubs/` (HTML, no API/PDF) → still needs headless browser or scraping. The current AFT command conflates tennis+padel; the proposal should split or clarify.

### 2. Brussels Sports Cadastre CSV — NEW (Brussels coverage, currently missing)

| Field | Value |
|-------|-------|
| URL | `https://backend.datastore.brussels/rest/metadata/fed2f7cb-2159-40e0-8cef-c6531901f188/resource/969a8337-68f4-476a-b204-a5b3be86e043/download/infra_export_opendata_1.csv` |
| HTTP | 200 OK, `application/octet-stream` |
| Size | 166,029 bytes (166 KB) |
| License | CC-BY 2.0 (attribution required) |
| Auth | None (public) |

**Schema** (CSV header, verified by curl):
```
IN_ID, IN_AutomatedExternalDefibrillator, name_fr, name_nl, name_en,
Place_fr, Place_isSchool, Place_nl, Place_en, Place_num,
Place_street_fr, Plca_street_nl, Place_street_en, Place_city, Place_zipcode
```

**Caveat**: header has a typo `Plca_street_nl` (should be `Place_street_nl`) — consumer must use the literal header string, not an assumed name.

**Coverage**: sports **infrastructure** (complexes, school gyms) in Brussels-Capital Region, not affiliated clubs per se. Useful as auxiliary enrichment (venue addresses) or for Brussels prospects not covered by federation lists. Not a 1:1 club roster.

**Source page**: `https://data.gov.be/en/datasets/fed2f7cb-2159-40e0-8cef-c6531901f188`

### 3. Verenigingsregister API (Flanders associations) — REINFORCES Flanders

| Field | Value |
|-------|-------|
| Docs | `https://publiek.verenigingen.vlaanderen.be/docs/api-documentation.html` |
| Format | JSON-LD |
| Auth | **API key required** for search/detail endpoints |
| Full access | Via MAGDA connection (government integration) |
| Public subset | Read-only, publicly shareable association data |

**Status**: API key is a blocker for unauthenticated sync. Two paths:
- (a) Request an API key (operational task, outside code scope) → cleanest data for Flanders clubs.
- (b) Skip for now; rely on Sport Vlaanderen open-data + existing scrapers as fallback.

**Recommendation**: document as the target source; do not block the refactor on key acquisition. Flag as an ops follow-up.

### 4. Sport Vlaanderen Open Data — REINFORCES Flanders

| Field | Value |
|-------|-------|
| Portal | `https://www.sport.vlaanderen/kennisplatform/open-data/` |
| Coverage | sports, federations, clubs, athletes (organized sport reporting) |
| Format | Not directly exposed on portal page (datasets hosted on data.vlaanderen.be / Europeana) |
| Auth | Unknown — needs dataset-level check |

**Status**: portal exists but does not expose a direct CSV/JSON download URL on the landing page. The underlying datasets are referenced via `metadata.vlaanderen.be` catalogs. Requires a follow-up to pin the exact sportclubs dataset URL. For now, treat as a **secondary** Flanders source behind the Verenigingsregister (once keyed) or existing scrapers.

### 5. RBFA GraphQL (football) — KEEP (already best)

| Field | Value |
|-------|-------|
| URL | `https://datalake-prod2018.rbfa.be/graphql` |
| Format | GraphQL with persisted queries |
| Auth | None (public datalake) |
| Status | Already in production (`SyncRbfaGraphqlCommand`). No public API docs, but the persisted-query hashes are stable. Harden with timeout/retry (finding #9), do not rewrite.

**No RBFA public clubs API exists** — Project Fenix is modernizing internal systems but exposes no public endpoint. The current GraphQL datalake is the de facto source.

## Sources NOT viable (verified absence)

- **No single nationwide Belgian sports-club API** — confirmed across multiple searches. Federation/region fragmentation is structural.
- **Hockey Belgium (KBHB/ARBH)** — no public API; `kbhb.altiusrt.com` is an admin/membership system. The current hardcoded ~110-club list + `hockey.be` scrape is the only path short of manual federation data request.
- **TPV / VAL / LBFA** — no public API or dataset; club directories are web pages only. Scraping (or headless browser for anti-bot) remains necessary.

## Evidence artifacts

- `/tmp/aftt_annuaire.pdf` — downloaded AFTT PDF (1.48 MB, 356 pp) for schema inspection during proposal/spec.
- Brussels CSV header captured above (curl stdout).
- Web search response IDs retained for citation.

## Conclusion for the proposal

The "better alternative" is a **per-federation adapter architecture** that uses the strongest available source, not a single replacement:

| Federation | Source | Action |
|------------|--------|--------|
| RBFA (football) | GraphQL datalake | **Keep**, harden (timeout/retry) |
| AFT (tennis Wallonia) | AFTT PDF | **Replace** fake data with PDF parser |
| AFPadel (padel Wallonia) | afpadel.be HTML | Headless browser or scrape |
| Hockey (VL + FR) | hardcoded + hockey.be scrape | Keep scrape; fix language/prefix bugs; remove test clubs |
| TPV (tennis/padel FL) | tennisenpadelvlaanderen.be scrape | Keep; fix error handling |
| VAL (athletics FL) | atletiek.be scrape | Keep; fix Terreinen parsing |
| LBFA (athletics FR) | lbfa.be table scrape | Keep; fix error handling |
| Brussels (all sports) | Brussels cadastre CSV | **New** auxiliary adapter |
| Flanders (cross-sport) | Verenigingsregister API | **Target** (needs API key — ops follow-up) |

Incremental migration: each adapter behind a `FederationDataSource` interface; existing commands refactored one at a time with tests green at every step.
