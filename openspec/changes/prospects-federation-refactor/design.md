# SDD Design — prospects-federation-refactor (CLA-535)

**Status**: `designed`
**Phase**: `sdd-design`
**Ticket**: CLA-535 — "Prospects: federation data-source refactor + audit fixes"
**Branch**: `audit/prospects-module`
**Module**: `Modules/Prospects`
**Inputs**: `proposal.md`, `explore.md`, `research.md`, `openspec/config.yaml` (all under `openspec/changes/prospects-federation-refactor/` unless noted)
**Baseline**: 32 tests / 88 assertions (must stay green after every slice)
**Delivery**: `ask-on-risk`, review budget 400 changed lines per slice

---

## 0. Executive summary

This design converts the approved 6-slice proposal into concrete contracts: a minimal `FederationDataSource` interface plus a `NormalizedClub` DTO that all federation adapters emit, a `ClubPersister` that owns normalization-to-`Prospect`/`ProspectLocation` persistence, a crash-safe error-handling layer built on the existing `LogsSyncEvents` trait, and two data migrations (Slice D2 / Slice C) following the `2026_04_05_163711_update_prospect_federation_prefixes.php` precedent.

Two design-level deviations from the proposal are introduced deliberately:

1. **Slice D is re-ordered and split into D1 / D2 *before* Slice C.** Slice C's `AfttPdfSource` cannot exist without the `FederationDataSource` interface, so the interface must land first. Execution order becomes **A → B → D1 → D2 → C → E → F**.
2. **Slice D is pre-split into D1 (contract + RBFA adapter) and D2 (normalization + migrations + LeadService fix)** — with a further contingency sub-split documented in §9 if the measured diff still exceeds 400 lines.

---

## 1. FederationDataSource interface — exact contract

### 1.1 Location

`Modules/Prospects/Contracts/FederationDataSource.php`

```php
<?php

namespace Modules\Prospects\Contracts;

use Modules\Prospects\DataObjects\NormalizedClub;

interface FederationDataSource
{
    /**
     * Federation code this source feeds, matching Prospect::$fillable 'federation'.
     * Examples: 'VL-VV' / 'FR-ACFF' (RBFA), 'FR-AFTT', 'BR-CAD', 'VL-TPV'.
     */
    public function name(): string;

    /**
     * Fetch and normalize every club from the underlying source.
     *
     * @return NormalizedClub[]
     *
     * @throws \Modules\Prospects\Exceptions\DataSourceException
     *         Thrown only on fatal source failure (unreachable source, empty
     *         discovery, unparsable payload). Partial per-club failures are
     *         skipped and must be surfaced through the concrete adapter's
     *         failures() accessor (see §2.1) — deliberately NOT part of this
     *         interface, so the contract stays minimal.
     */
    public function fetchClubs(): array;
}
```

The interface is intentionally minimal (two methods, per the approved proposal sketch). Failure introspection stays on the concrete adapter class so the contract never grows policy-specific surface.

### 1.2 DTO shape — NormalizedClub

Location: `Modules/Prospects/DataObjects/NormalizedClub.php` (final, readonly, PHP 8.4) and `Modules/Prospects/DataObjects/ClubLocation.php`.

```php
final readonly class ClubLocation
{
    public function __construct(
        public string  $address,               // full street + number + postal + city
        public ?string $contactName = null,
        public ?string $email = null,
        public ?string $phone = null,
        public ?string $sourceLocationId = null, // stable per-source location id (VAL Terrein name, Brussels IN_ID, …)
    ) {}
}

final readonly class NormalizedClub
{
    /**
     * @param ClubLocation[] $venues
     */
    public function __construct(
        public string  $externalId,   // fully prefixed, unique; scheme in §3.2 — e.g. 'VL-TPV-TC001'
        public string  $federation,   // e.g. 'VL-TPV', 'FR-LFH', 'ARBH-KBHB', 'VL-VV', 'BR-CAD'
        public string  $name,
        public string  $type,         // Prospect type: 'football_club', 'hockey_club', 'tennis_padel_club',
                                      // 'table_tennis_club', 'athletics_club', 'sports_infrastructure'
        public string  $language,     // 'nl' | 'fr'
        public ?string $postalCode,   // Belgian 4-digit code; null → persister falls back to 'Overige'
        public ?string $website = null,
        public ?string $logoUrl = null,
        public ?string $vatNumber = null,
        public ?string $contactPerson = null,
        public ?string $channel = null,
        public ?ClubLocation $headquarters = null,
        public array   $venues = [],
    ) {}

    /** Validates required keys; throws \InvalidArgumentException on malformed input. */
    public static function fromArray(array $raw): self { /* … */ }
}
```

`NormalizedClub::fromArray()` is the only sanctioned constructor path for test fixtures and legacy code paths; it enforces non-empty `externalId`/`federation`/`name`/`type`/`language`, and rejects `externalId` values not matching the §3.2 prefix scheme (`/^(VL|FR|ARBH|BR)-/`).

### 1.3 How adapters are registered (service container binding)

Adapters are resolved through the container, mirroring the constructor-injection pattern `SyncTpvClubsCommand` already demonstrates with `?Client $client`. Commands that consume an adapter declare it as a constructor dependency; Laravel resolves console commands through the container, so plain constructor injection works.

`ProspectsServiceProvider::register()` gains contextual bindings:

```php
$this->app->when(SyncRbfaGraphqlCommand::class)
    ->needs(\Modules\Prospects\Contracts\FederationDataSource::class)
    ->give(\Modules\Prospects\DataSource\RbfaGraphqlSource::class);

$this->app->when(SyncAftClubsCommand::class)
    ->needs(\Modules\Prospects\Contracts\FederationDataSource::class)
    ->give(\Modules\Prospects\DataSource\AfttPdfSource::class);

$this->app->when(SyncBrusselsClubsCommand::class)
    ->needs(\Modules\Prospects\Contracts\FederationDataSource::class)
    ->give(\Modules\Prospects\DataSource\BrusselsCadastreSource::class);
```

Test override follows the existing TPV pattern exactly (`Modules/Prospects/tests/Feature/SyncTpvClubsCommandTest.php::runCommand()`):

```php
$this->app->bind(RbfaGraphqlSource::class, fn () => new RbfaGraphqlSource(url: 'https://example.test/graphql'));
// or bind the whole command with a pre-built source instance
```

The RBFA command types the **concrete** `RbfaGraphqlSource` in its constructor (not the interface) so it can call `$source->failures()` after `fetchClubs()`; the container binding above still satisfies resolution. All other consumers type the interface.

---

## 2. Adapter implementations

### 2.1 RbfaGraphqlSource (Slice D1) — wraps existing `SyncRbfaGraphqlCommand` logic

Location: `Modules/Prospects/DataSource/RbfaGraphqlSource.php`

```php
final class RbfaGraphqlSource implements FederationDataSource
{
    public const DEFAULT_URL = 'https://datalake-prod2018.rbfa.be/graphql';

    public function __construct(
        private ?string $apiUrl = null,          // injectable for tests / future endpoints
        private ?string $provincesConfig = null, // defaults to config('rbfa.provinces')
    ) {}

    public function name(): string { return 'RBFA'; }

    /** @return NormalizedClub[] */
    public function fetchClubs(): array;

    /** @return array<int, array{series: string, province: string, status: int|string}> */
    public function failures(): array;   // populated by fetchClubs(); per-series failures (finding 5)
}
```

Behavior (moved from the current command, hardened):

- **Discovery**: iterates `config('rbfa.provinces')` (post-Slice B: all provinces incl. Vlaams-Brabant + Brussel are active). Persists-query payload for `GetSeriesRankings` unchanged (hash `0a53124a…`). HTTP via `Http::` facade with `->timeout(60)->retry(3, 5000)` (unchanged), 1s inter-series sleep preserved.
- **Failed/unparseable series responses are recorded into `failures()`** instead of being silently skipped (fix for finding 5); the loop continues to other provinces.
- **Enrichment**: per-club `getClubInfo` persisted query (hash `7c1bd99f…`) now sent with `->timeout(30)->retry(3, 5000)` (fix for finding 10). Per-club failures are recorded in `failures()` too; the loop continues.
- **Federation/language decision (fix for finding 15)**: derived from the enriched club's `postalCode` through `HandlesClubRegions` (via the persister-side region resolver) — postal in a Flemish province → `VL-VV` / `nl`; otherwise `FR-ACFF` / `fr`. **Brussels RBFA clubs keep `FR-ACFF`** — deliberate decision to stay consistent with the existing dataset, which the `2026_04_05_163711` precedent migration already stamped that way (renaming now would orphan rows).
- **Fatal condition → `DataSourceException`**: zero successful series responses.
- Emits `NormalizedClub` with `headquarters` `ClubLocation` built from `streetName`/`postalCode`/`localityName` + joined contact emails/phones (existing 250-char truncation preserved) and `sourceLocationId = (string) $clubId`.

**Refactored command** (`SyncRbfaGraphqlCommand`): constructor injects `RbfaGraphqlSource`; `handle()` reduces to start log → `fetchClubs()` → persist loop (via `ClubPersister` after D2; inline `updateOrCreate` until then) → after the loop, drain `$source->failures()` into `logSyncEvent(..., 'error')` entries and, **if any failures exist, call `failSyncLog()`** (marks history failed) even though discovered clubs were persisted (per proposal's Slice B finding-5 decision). Province/`--limit` options keep their current semantics and are passed into the source constructor call site.

### 2.2 AfttPdfSource (Slice C) — replaces fake AFT data

Location: `Modules/Prospects/DataSource/AfttPdfSource.php`

```php
final class AfttPdfSource implements FederationDataSource
{
    public const URL = 'https://ep.aftt.be/assets/media/documents/annuaire/annuaire_complet.pdf';

    public function __construct(
        private string $source = self::URL,               // URL or local path (tests pass a fixture path)
        private ?\Smalot\PdfParser\Parser $parser = null,  // injectable parser (tests pass a real Parser over the fixture)
    ) {}

    public function name(): string { return 'FR-AFTT'; }

    public function fetchClubs(): array;
}
```

Behavior:

- If `$source` is an http(s) URL: `Http::timeout(120)->retry(2, 5000)->get($source)`; non-200 or non-PDF body → `DataSourceException`. A local path is parsed directly (this is the test seam).
- Parsing uses `smalot/pdfparser` (`(new Parser())->parseContent($bytes)->getText()`). The PDF is PDF 1.4 / 356 pages — verified extractable in research.md.
- **Parsing is isolated in a private line-oriented parser** (`parseAnnuaireText(): array`) so layout drift touches one method. Because the annuaire's exact layout was not fully enumerated during research, the apply phase must **first commit a fixture** cut from the verified download (`/tmp/aftt_annuaire.pdf`): `Modules/Prospects/tests/fixtures/aftt/annuaire_sample.pdf` (representative pages incl. edge cases: club with/without email, multi-line addresses). The parsing rules (club-entry regex, address/postal/contact extraction) are then derived TDD-first against that fixture — RED test before parser implementation.
- Normalization: `external_id = 'FR-AFTT-' . ($clubNumber ?? Str::slug($clubName))` — prefer the AFTT club number when the layout exposes one; otherwise slug (documented weakness shared with VAL/LBFA today), `federation = 'FR-AFTT'`, `type = 'table_tennis_club'`, `language = 'fr'`, `postalCode` from address regex `\b[1-9][0-9]{3}\b`, `headquarters` location from parsed address/contact.
- Fatal conditions → `DataSourceException`: download failure, empty extracted text, zero clubs parsed.

**Command** (`SyncAftClubsCommand`): constructor injects `AfttPdfSource`; the fake hardcoded 2-club array and the dead CSRF/Guzzle path (`tennis.tppwb.be`) are deleted. The AFPadel gap (padel Wallonia, `afpadel.be`) is documented in the command description and `handoff.md` — out of scope (proposal non-goal).

### 2.3 BrusselsCadastreSource (Slice E) — new coverage

Location: `Modules/Prospects/DataSource/BrusselsCadastreSource.php`

```php
final class BrusselsCadastreSource implements FederationDataSource
{
    public const URL = 'https://backend.datastore.brussels/rest/metadata/fed2f7cb-2159-40e0-8cef-c6531901f188'
        . '/resource/969a8337-68f4-476a-b204-a5b3be86e043/download/infra_export_opendata_1.csv';

    public function __construct(private string $url = self::URL) {}

    public function name(): string { return 'BR-CAD'; }

    public function fetchClubs(): array;
}
```

Behavior:

- Download via `Http::timeout(60)->retry(2, 5000)->get()`; non-200 → `DataSourceException`. CSV parsing uses core PHP (`str_getcsv` per line) — **no new CSV dependency**.
- Column mapping (verified header in research.md; the `Plca_street_nl` typo is used **literally**):

| CSV column | NormalizedClub target |
|---|---|
| `IN_ID` | `externalId = 'BR-CAD-' . IN_ID`, `venues[].sourceLocationId` |
| `name_fr` / `name_nl` / `name_en` | `name` = `name_fr ?: name_nl ?: name_en`; `language` = `'fr'` if only `name_fr`, `'nl'` otherwise |
| `Place_street_fr` / `Plca_street_nl` / `Place_street_en`, `Place_num`, `Place_zipcode`, `Place_city` | single `venues[]` entry, `address = "{street} {num}, {zipcode} {city}"` |
| `Place_zipcode` | `postalCode` (1000–1299 → `Brussel` region via `HandlesClubRegions`) |

- `federation = 'BR-CAD'`, `type = 'sports_infrastructure'` (new Prospect `type` value — column is a free string; no schema change). Rows are **venue-only** (sports complexes are playing locations, not club HQs) — no `headquarters`.
- **Command**: new `Console/Commands/SyncBrusselsClubsCommand.php` (`prospects:sync-brussels-clubs {--user=} {--history=}`), registered in `ProspectsServiceProvider::registerCommands()`, added to the monthly schedule (`monthlyOn(1, '04:00')`) and appended as the 7th `ExecuteSyncJob` in the `MasterSyncJob` chain (after RBFA). `SyncDashboardPage` federation list gains the new command so it gets a card and can be triggered per-federation.
- **CC-BY 2.0 attribution**: one footer line on `SyncDashboardPage` — *"Source: Infrastructures sportives — Région de Bruxelles-Capitale (CC-BY 2.0, backend.datastore.brussels)"* — plus a `DataSource` class-level docblock attribution.

### 2.4 Existing scrapers (Hockey/TPV/VAL/LBFA) — conformance without full rewrite

These four commands **do not** get adapter extractions in this change (that would blow every slice's budget; proposal scope keeps their fixes in Slices A/B). They conform in two ways:

1. **Behavioral conformance now (Slices A/B)**: the per-slice surgical fixes (error logging, language bug, DomCrawler fix, region reuse, persisted counts) are applied to the existing command code paths. TPV's constructor-injected `Client` is the established test seam; the other commands gain per-club error logging via the extended `LogsSyncEvents` trait (§4).
2. **Structural conformance later (documented follow-up, not a slice here)**: each scraper's fetch block moves mechanically into `DataSource/{Hockey,Tpv,Val,Lbfa}Source implements FederationDataSource` — the interface + `ClubPersister` in this design are shaped so that extraction is a pure move-and-wrap: the scraper code already produces the same tuple of fields `NormalizedClub` carries (name, address, email, phone, website, postal). Hockey's inline ~124-club list moves to `config/hockey.php` (same pattern as `config/rbfa.php`) at extraction time. This follow-up is recorded in `handoff.md` under remaining gaps (Slice F documentation).

---

## 3. Normalization layer

### 3.1 Components

| Component | Location | Role |
|---|---|---|
| `ContactType` (backed enum) | `Modules/Prospects/Support/ContactType.php` | Unifies contact_type semantics; DB literals unchanged |
| `ClubPersister` | `Modules/Prospects/Services/ClubPersister.php` | `NormalizedClub` → `Prospect` + `ProspectLocation` rows |
| `HandlesClubRegions` (existing trait) | `Modules/Prospects/Traits/HandlesClubRegions.php` | Reused by `ClubPersister` for postal→region mapping; single source of truth |

### 3.2 external_id scheme (enforced)

```
{REGION_PREFIX}-{FEDERATION_CODE}-{SOURCE_ID}
REGION_PREFIX ∈ { VL, FR, ARBH, BR }
```

| Source | external_id example | Notes |
|---|---|---|
| RBFA | `VL-RBFA-1234` / `FR-RBFA-1234` | Unchanged from current emission (already compliant) |
| Hockey | `VL-HOCKEY-{hockey.be id}` / `FR-HOCKEY-…` / **`ARBH-HOCKEY-…`** | ARBH gains its prefix in Slice D2 (migration re-stamps legacy `HOCKEY-…` rows, §5) |
| TPV | `VL-TPV-{club external id}` | Unchanged |
| VAL | `VL-VAL-{slug}` | Unchanged (slug-based; documented weakness) |
| LBFA | `FR-LBFA-{slug}` | Unchanged |
| AFTT | `FR-AFTT-{club number or slug}` | New (Slice C) |
| Brussels | `BR-CAD-{IN_ID}` | New (Slice E) |

`NormalizedClub::fromArray()` enforces the `^(VL|FR|ARBH|BR)-` prefix so no adapter can emit a non-conforming id.

### 3.3 contact_type normalization

New backed enum — **values intentionally match existing DB literals, so no value-rename migration is needed**:

```php
enum ContactType: string
{
    case Headquarters = 'headquarters'; // club main/administrative address (RBFA, LBFA, TPV 'Adres (hoofdlocatie)', Hockey 'Adres', AFTT annuaire address)
    case Venue        = 'venue_name';   // playing locations (VAL Terreinen, Brussels infrastructure)
    case Primary      = 'primary';       // website-contact leads (LeadService only — not used by adapters)
}
```

Unified rule: **one main address → `Headquarters`; playing/venue locations → `Venue`**. Downstream impact verified: Mailing selects location emails without filtering on `contact_type` (grep confirmed — Mailing tests create their own `'main'` rows and never filter); the Filament `ProspectResource` option list already contains both literals. Existing TPV/Hockey/AFT location rows hold the club's *main* address but are stamped `venue_name` — Slice D2's migration corrects the data (§5.3).

### 3.4 Region mapping

`ClubPersister` uses the `HandlesClubRegions` trait directly (traits work in services). `postalCode → getRegionIdFromPostalCode()`, null → `getFallbackRegionId()` (`Overige`). This kills finding 15's magic `region_id ?? 11` (AFT) and RBFA's hardcoded province-name array (both fixed in Slice B code, consumed uniformly by the persister from D2 onward).

### 3.5 ClubPersister contract (introduced in Slice D2)

```php
class ClubPersister
{
    use HandlesClubRegions;

    public function persist(NormalizedClub $club): Prospect;

    /** @return array{persisted: int, failed: int} per-run tallies for records_count (§4.4) */
    public function persistAll(array $clubs): array;
}
```

- `Prospect::updateOrCreate(['external_id' => $club->externalId], [name, type, federation, language, website, logo_url, vat_number, contact_person, channel, region_id])`.
- Location identity: **before D2** (Slice C transitional): `updateOrCreate(['prospect_id', 'contact_type'])` — current schema. **After D2** (locations gained a nullable `external_id` column): `updateOrCreate(['prospect_id', 'external_id'])` where `external_id` is synthesized as `{clubExternalId}::hq` for `headquarters` and `{clubExternalId}::{sourceLocationId ?? 'v'.$n}` for each venue. This is the fix for finding 14 (address change no longer duplicates VAL rows) and keeps `LeadService` `primary` rows safe (their `external_id` stays `NULL`; MySQL unique allows multiple NULLs).

---

## 4. Error-handling design

### 4.1 LogsSyncEvents trait extension (Slice A — no base-class rewrite)

Extend the existing trait rather than rewriting commands around a new base class (smaller diff, same guarantee):

```php
// New on Traits/LogsSyncEvents.php:
protected int $persistedCount = 0;   // incremented after each successful updateOrCreate
protected int $failedCount = 0;

protected function markPersisted(): void;      // $this->persistedCount++
protected function markFailed(): void;        // $this->failedCount++
public function flushSyncLog(): void;         // immediate buffer→SyncHistory write

/**
 * Crash-safe template method. Every command's handle() becomes:
 *   return $this->guardedSync(fn () => $this->doSync());
 * Guarantees: startSyncLog always runs; ANY uncaught Throwable → failSyncLog (status
 * 'failed' + buffered logs flushed) + non-zero exit code; success path finishes
 * with persisted count, not attempt count.
 */
protected function guardedSync(callable $body): int;
```

Also: `logSyncEvent()` **flushes immediately when `$type === 'error'`** — a mid-run crash can no longer lose the last failure events (finding 12's crash-loss window). `guardedSync()` marks history failed **and re-throws after flushing** when called outside a per-club loop so queue workers see the failure.

`finishSyncLog()` keeps its signature but commands pass `$this->persistedCount`; the final quality log line reports `processed X | persisted Y | failed Z` (finding 9).

### 4.2 ExecuteSyncJob — exit-code check (Slice A, finding 7)

```php
$exit = Artisan::call($this->command, array_filter([...]));

if ($exit !== 0) {
    // failure DB notification to the triggering user ("ha finalizado con errores", danger)
    // then throw — this is what trips the master chain's catch (§4.3) and marks the job failed
    throw new \RuntimeException("Sync command {$this->command} exited with code {$exit}.");
}
// existing success notification ("ha terminado satisfactoriamente")
```

This requires commands to actually return non-zero on failure — `guardedSync()` supplies that (§4.1). The `catch` in `MasterSyncJob` does not catch this; the job fails, and the chain's `->catch(...)` does (§4.3).

### 4.3 MasterSyncJob chain failure handling (Slice A, finding 8)

```php
Bus::chain([
    new ExecuteSyncJob('prospects:sync-lbfa-clubs', ...),
    new ExecuteSyncJob('prospects:sync-aft-clubs', ...),
    new ExecuteSyncJob('prospects:sync-hockey-clubs', ...),
    new ExecuteSyncJob('prospects:sync-tpv-clubs', ...),
    new ExecuteSyncJob('prospects:sync-val-clubs', ...),
    new ExecuteSyncJob('prospects:sync-rbfa-graphql', ...),
    new ExecuteSyncJob('prospects:sync-brussels-clubs', ...),   // added in Slice E
    new SendMasterSyncFinishedNotificationJob($this->userId, $this->historyId),
])
->catch(new MarkMasterSyncFailedJob($this->userId, $this->historyId))
->dispatch();
```

New `Jobs/MarkMasterSyncFailedJob.php` (small, Slice A):

- Loads `SyncHistory` by `$historyId` (the `prospects:sync-master` row `SyncDashboardPage::syncAll()` creates under `lockForUpdate`); if it is still `pending`/`running`, sets `status = 'failed'`, `finished_at = now()`, appends an error log entry (exception message passed through the catch payload).
- Sends a danger DB notification to `$userId` ("La sincronización maestra ha fallado — cadena detenida").

Guarantees: the master history row can never be stuck in `running`/`pending` again; a notification **always** fires (success branch via the final chain job, failure branch via the catch job); the dashboard's stuck-master / disabled-`sync_all` symptom is gone. Chain order preserved (non-goal respected). `WithoutOverlapping('prospects-master-sync')` untouched.

Queue-semantics risk (R6 from proposal): behavior differs under `QUEUE_CONNECTION=sync` vs real queue — covered by `Bus::fake`/`Queue::fake` feature tests (§7) plus a post-deploy observation period noted in rollout.

### 4.4 records_count = persisted count (Slice A, finding 9)

Every command counts **successful persist operations**, not loop iterations: `markPersisted()` after each `updateOrCreate` that returns without exception; `finishSyncLog($this->persistedCount)` at the end. With `ClubPersister` (D2+), `persistAll()` returns the tallies and the command forwards them. The dashboard (`SyncDashboardPage`) needs no change — it already renders `records_count`.

### 4.5 Per-source error mapping (consolidated)

| Failure mode | Where handled | Result |
|---|---|---|
| Fatal source failure (all series dead, PDF unparsable, CSV 404) | Adapter throws `DataSourceException` | `guardedSync` catch → `failSyncLog` → history `failed`, exit 1 → `ExecuteSyncJob` throws → chain catch → master failed |
| Per-club enrichment/scrape failure | Adapter/loop catch → `markFailed()` + `logSyncEvent(..., 'error')` (immediate flush) | sync continues; history ends `completed` with `persisted < processed` breakdown in logs |
| Partial discovery failures (RBFA series) | `failures()` drained by command after persist loop | clubs persisted, then `failSyncLog` → history `failed` with per-series error log (finding 5 decision) |
| TPV pagination cap hit (offset 1000) | Slice A: warn log "pagination safety cap reached — results truncated" (finding 11, mitigated as per proposal) | visible in logs/history |
| Crash mid-run (kill/OOM) | `guardedSync` + error-flush | up to last error event preserved; history `failed` if Throwable reached the guard |

---

## 5. Migration strategy

### 5.1 Incremental adapter rollout (all slices)

For every adapter, the same three-step pattern, TDD-gated:

1. **RED**: co-located adapter test written first (mock HTTP / fixture PDF / fixture CSV), fails against missing class.
2. **GREEN behind the interface**: adapter implements `FederationDataSource`, registered via contextual binding; the consuming command resolves it through the container; old inline fetch logic stays in place (dead code path, not yet deleted) — scoped suite green.
3. **Remove old inline logic only after adapter tests are green**: delete the superseded fetch block from the command; scoped suite + full module suite green; one work-unit commit (work-unit-commits skill).

### 5.2 Slices A and B — no data migrations

Per the approved scope: A/B are behavior/observability/correctness **code** fixes only. No migration files. The data effects of B's fixes (corrected `language` for FR hockey clubs, restored provinces in RBFA discovery) flow in naturally on the next sync via `updateOrCreate`.

### 5.3 Slice D2 — normalization migrations (precedent: `2026_04_05_163711_update_prospect_federation_prefixes.php`)

Two migrations, both guarded, reversible (`down()` restores prior literals), following the precedent's raw-`DB::table` style:

1. **`add_external_id_to_prospects_locations_table.php`**
   - Add nullable `string('external_id')` to `prospects_locations`.
   - Unique index `('prospect_id', 'external_id')` — nullable `external_id` rows (LeadService `primary`, VAL legacy) are exempt from uniqueness by MySQL NULL semantics.
   - Backfill: `headquarters` rows → `{prospect.external_id}::hq`; `venue_name` rows → `{prospect.external_id}::v{n}` using `ROW_NUMBER() OVER (PARTITION BY prospect_id ORDER BY id)` (MySQL 8 window function over a derived table — same engine as production).

2. **`normalize_prospect_location_conventions.php`**
   - **contact_type**: `UPDATE prospects_locations … JOIN prospects_prospects … SET contact_type='headquarters'` where current `venue_name` **and** prospect federation in (`VL-TPV`, `VL-VHL`, `FR-LFH`, `ARBH-KBHB`, `FR-AFT`) — these rows hold the club's single main address. VAL `Terreinen` rows (federation `VL-VAL`) stay `venue_name` (genuine playing locations).
   - **external_id re-stamp**: `UPDATE prospects_prospects SET external_id = CONCAT('ARBH-', external_id) WHERE federation = 'ARBH-KBHB' AND external_id LIKE 'HOCKEY-%'` (finding 3's data half; the code half lands in D2's persister emission). Mail-log lineage survives because Mailing references prospects by id, not external_id.
   - **VAL location dedupe** (finding 14's existing duplicates): delete `prospects_locations` rows that duplicate `(prospect_id, address)` keeping the lowest id.

### 5.4 Slice C — fake-data disposal (finding 1)

Migration `soft_retire_fake_aft_prospects.php`: the two fabricated rows (`FR-AFT-tc-de-wavre`, `FR-AFT-royal-leopold-club`) get `unsubscribed_at = now()`.

**Decision**: soft-retire, not hard-delete, not identity re-stamp. Rationale: proposal risk R3 forbids hard-deleting (mail-log lineage references must survive); re-stamping the fake rows with real AFTT club identities was rejected because no trustworthy identity mapping exists between the fabricated tennis clubs and real table-tennis clubs — re-stamping would fabricate identity a second time. `unsubscribed_at` immediately excludes them from `scopeSubscribed` mail targeting while preserving every historical reference. The migration is idempotent (`whereNull('unsubscribed_at')`) and reversible.

### 5.5 Deployment sequencing

- A → B → D1 → D2 → C → E, one PR per slice (§9). D2's two migrations ship in the same PR as the persister switch so no window exists where the persister writes keys the schema lacks.
- Coordinate B's merge window with the Mailing team (FR hockey clubs flip `language` from `nl`→`fr` on next sync — proposal risk R2).
- After each deploy: run the affected sync manually from the dashboard; verify dashboard history rows (`completed`, `failed`), then observe queue behavior for one scheduled cycle (R6).

---

## 6. Dependency additions

| Dependency | Slice | Justification | Compatibility |
|---|---|---|---|
| `smalot/pdfparser` (`^2.x`) | C only | Only PHP-native, dependency-light PDF text extractor for the AFTT annuaire. `barryvdh/laravel-dompdf` already present is a *generator*, not a parser — not reusable. | 2.x line requires PHP ≥ 8.1; PHP 8.4 host CLI (8.4.25) is supported. Exact constraint confirmed at apply time via `composer require smalot/pdfparser` + `composer install` dry-check in CI (PHP 8.4). |

No other new dependencies. CSV parsing uses core `str_getcsv`; HTTP continues on the existing `Http::` facade / Guzzle stacks (TLS verification stays as-is per command — changing `'verify' => false` is a finding-16 hardening item already covered in Slice A's User-Agent/error-logging scope, not a new dep).

---

## 7. Test strategy

Strict TDD per `openspec/config.yaml` (`testing.strict_tdd: true`): failing test first, RED confirmed, then GREEN. All tests co-located under `Modules/Prospects/tests/`.

### 7.1 Test matrix

| Test | Slice | Technique |
|---|---|---|
| `Tests/Feature/LogsSyncEventsTest` (extend existing) | A | error-flush, `guardedSync` fail path, persisted/failed tallies |
| `Tests/Feature/ExecuteSyncJobTest` (new) | A | `Artisan::shouldReceive('call')` returning 1 → job throws + failure notification; 0 → success notification |
| `Tests/Feature/MasterSyncJobTest` (new) | A | `Bus::fake`/`Queue::fake` chain assertions: catch job dispatched on failure; master history row → `failed`; success path dispatches finish-notification job |
| `Tests/Feature/SyncHockeyClubsCommandTest` (new) | B | Guzzle `MockHandler` (TPV pattern): language fix (`FR-LFH` → `fr`), federation mapping, per-club error logging |
| `Tests/Feature/SyncValClubsCommandTest` (new) | B | `Http::fake` sequence: Terreinen parsing (finding 12), multi-venue locations, swallowed-exception fix |
| `RbfaGraphqlSourceTest` (extend `SyncRbfaGraphqlCommandTest`) | D1 | `Http::fake` sequenced responses: discovery + enrichment payloads; `failures()` populated on 500; `DataSourceException` when all series fail; timeout/retry present via `Http::assertSent` |
| `FederationDataSourceContractTest` (abstract, per adapter) | D1 | Every adapter's `fetchClubs()` output: all `NormalizedClub` instances, prefix scheme regex, non-empty name/federation, language ∈ {nl, fr}, `fromArray` validation rejects bad input |
| `ClubPersisterTest` | D2 | Prospect upsert, region resolution + `Overige` fallback, headquarters/venue location keys incl. address-change idempotency (finding 14 regression test), tallies |
| `LeadServiceTest` (extend/new) | D2 | unique-violation race → no orphan Prospect row (nested savepoint transaction) |
| Migration up/down tests (optional but recommended) | D2 | `migrate:fresh`-based assertions on backfill correctness |
| `AfttPdfSourceTest` | C | Fixture PDF (`tests/fixtures/aftt/annuaire_sample.pdf`, cut from the verified download); parser output club count + fields; `DataSourceException` on garbage bytes; `SyncAftClubsCommandTest` adapter integration + fake-club soft-retire assertion |
| `BrusselsCadastreSourceTest` | E | Fixture CSV with the **literal** `Plca_street_nl` header; address assembly, `BR-CAD-{IN_ID}`, Brussel region; `SyncBrusselsClubsCommandTest` |

### 7.2 Seams

- Guzzle-based commands (TPV, Hockey): constructor-injected `Client` + `MockHandler` — existing pattern (`SyncTpvClubsCommandTest::makeClient()`).
- `Http::`-facade sources (RBFA, VAL, LBFA, AFTT download, Brussels): `Http::fake()` / `Http::fakeSequence()`; no network in tests ever.
- AFTT parsing: local fixture path instead of URL; real `Parser` over the fixture (no mock of the dep — it's the unit under test).
- Queue/chain: `Bus::fake` / `Queue::fake`; `QUEUE_CONNECTION=sync` in `phpunit.xml` keeps chains inline and assertable.

### 7.3 Runner contract

Scoped (before every progress claim):
```
DB_HOST=127.0.0.1 DB_PORT=3308 php artisan test --filter=Prospects Modules/Prospects/tests
```
Baseline 32 tests / 88 assertions must remain green at every slice boundary; new failure outside known preexisting ones is blocking. Full module suite unfiltered before phase closure; `handoff.md` + memory updated in the same work unit (archive gate).

---

## 8. Sequence diagrams

### 8.1 Master sync with failure handling

```
 Admin            SyncDashboardPage      MasterSyncJob         Bus::chain                ExecuteSyncJob(i)          Artisan command(i)         SyncHistory
  |  syncAll() ----->|                       |                     |                           |                          |                      |
  |                 |-- lockForUpdate check ->|                     |                           |                          |                      |
  |                 |-- create row (pending) ----------------------->------------------------------------------------------------->   |
  |                 |-- dispatch ------------>|                     |                           |                          |                      |
  |                 |                        |-- chain([LBFA..RBFA, Notify], catch: MarkFailed) -->|                       |                      |
  |                 |                        |                     |                           |                          |                      |
  |                 |                        |                     |-- ExecuteSyncJob(1) ------>|                          |                      |
  |                 |                        |                     |                           |-- Artisan::call -------->|                      |
  |                 |                        |                     |                           |                          |-- startSyncLog ------>| (running)
  |                 |                        |                     |                           |                          |   ...sync clubs...    |
  |                 |                        |                     |                           |                          |-- finish/fail ------>| (completed/failed)
  |                 |                        |                     |                           |<-- exit code N ----------|                      |
  |                 |                        |                     |                           |                          |                      |
  |                 |                        |                     |             [if N == 0] success notification; chain continues at (i+1)          |
  |                 |                        |                     |                           |                          |                      |
  |                 |                        |                     |             [if N != 0] ExecuteSyncJob THROWS ------------------>|              |
  |                 |                        |                     |                           |                          |                      |
  |                 |                        |                     |-- chain catch fires ------>| (MarkMasterSyncFailedJob) |                      |
  |                 |                        |                     |                           |-- mark master row failed + error notification --->| (failed)
  |                 |                        |                     |                           |   ...remaining chain jobs skipped...             |
  |                 |                        |                     |                           |                          |                      |
  |                 |                        |                     |   [all jobs ok] SendMasterSyncFinishedNotificationJob:                    |
  |                 |                        |                     |                           |-- master row completed + success notification --->|
  |<---------------- notification (either branch) ------------------|                          |                          |                      |
```

### 8.2 Single adapter: fetch → normalize → persist

```
 SyncCommand            FederationDataSource        Source (HTTP/PDF/CSV)      NormalizedClub[]        ClubPersister            DB
  |                          |                            |                         |                      |                    |
  |-- guardedSync(start) --->|                            |                         |                      |                    |
  |     (SyncHistory=running)------------------------------------------------------------------------------------------->|
  |                          |                            |                         |                      |                    |
  |-- fetchClubs() --------->|                            |                         |                      |                    |
  |                          |-- HTTP GET / parse -------->|                         |                      |                    |
  |                          |<-- raw payload -------------|                         |                      |                    |
  |                          |                            |                         |                      |                    |
  |                          |   [fatal failure? --> throw DataSourceException --> guardedSync catch --> failSyncLog --> history failed, exit 1]
  |                          |                            |                         |                      |                    |
  |                          |   raw row --> normalize (external_id scheme, ContactType,  postal, language)      |                    |
  |                          |-- NormalizedClub[] -------->|                         |                      |                    |
  |<-------------------------|                            |                         |                      |                    |
  |                          |                            |                         |                      |                    |
  |-- for each club: persistClub(club) ------------------->|                         |                      |                    |
  |                          |                            |                         |-- persist() -------->|                    |
  |                          |                            |                         |                      |-- region: postal -> HandlesClubRegions
  |                          |                            |                         |                      |-- Prospect::updateOrCreate(external_id) -->|
  |                          |                            |                         |                      |-- Location::updateOrCreate(prospect_id, external_id) ->|
  |                          |                            |                         |                      |<-- ok: markPersisted()                |
  |                          |                            |                         |      [per-club error: markFailed() + error log, immediate flush, continue]  |
  |                          |                            |                         |                      |                    |
  |-- finishSyncLog(persistedCount) -------------------------------------------------------------------------------->|
  |     (SyncHistory=completed, records_count = persisted, logs incl. "processed X | persisted Y | failed Z")
```

---

## 9. Slice D review-budget risk — D1/D2 split

Proposal Slice D ("interface + RBFA adapter + normalization + LeadService fix", est. 350–450 lines) exceeds the 400-line review budget when extraction diffs are counted honestly (moving ~150 lines from command to adapter costs −150/+150 in changed lines even at net-zero). **Pre-emptive split:**

| Sub-slice | Contents | Estimated diff | Budget |
|---|---|---|---|
| **D1 — Contract + RBFA adapter** | `FederationDataSource`, `NormalizedClub`/`ClubLocation`, contract test, `RbfaGraphqlSource`, `SyncRbfaGraphqlCommand` refactor, `RbfaGraphqlSourceTest` | ~350–480 | ⚠️ borderline |
| **D2 — Normalization + migrations** | `ContactType` enum, `ClubPersister` (+ key switch), both §5.3 migrations, ARBH external_id re-stamp, `LeadService` orphan fix, tests | ~350–420 | ⚠️ borderline |

**Contingency (both D1 and D2)**: if the measured diff at apply time exceeds 400 lines, split further before opening the PR —
- D1 → **D1a** contract + DTO + contract test (~200) and **D1b** RBFA adapter + command refactor + tests (~280);
- D2 → **D2a** enum + persister + location `external_id` schema migration (~250) and **D2b** convention migrations + LeadService fix (~200).

Because delivery is `ask-on-risk`, the apply phase **stops and asks the user** at either gate; it never self-selects chaining or `size:exception`.

**Execution order becomes: A → B → D1 → D2 → C → E → F** — C is re-sequenced after D1/D2 because `AfttPdfSource` implements the interface and reuses the persister (and its location keys depend on D2's schema). Slices A, B, E, F are unaffected.

---

## 10. File-change map

| Slice | New files | Modified files |
|---|---|---|
| A | `Jobs/MarkMasterSyncFailedJob.php`, tests | `Traits/LogsSyncEvents.php`, `Jobs/ExecuteSyncJob.php`, `Jobs/MasterSyncJob.php`, all 6 sync commands (guardedSync/persisted-count/error-logging), tests |
| B | tests | `SyncHockeyClubsCommand.php`, `SyncRbfaGraphqlCommand.php`, `SyncAftClubsCommand.php`, `SyncValClubsCommand.php`, `config/rbfa.php` (uncomment series) |
| D1 | `Contracts/FederationDataSource.php`, `DataObjects/NormalizedClub.php`, `DataObjects/ClubLocation.php`, `DataSource/RbfaGraphqlSource.php`, contract test, `RbfaGraphqlSourceTest` | `Providers/ProspectsServiceProvider.php` (binding), `SyncRbfaGraphqlCommand.php` |
| D2 | `Support/ContactType.php`, `Services/ClubPersister.php`, 2 migrations, tests | `SyncRbfaGraphqlCommand.php` (persister), `Services/LeadService.php`, tests |
| C | `DataSource/AfttPdfSource.php`, migration, fixture PDF, tests | `SyncAftClubsCommand.php`, `Providers/ProspectsServiceProvider.php` |
| E | `DataSource/BrusselsCadastreSource.php`, `Console/Commands/SyncBrusselsClubsCommand.php`, tests, fixture CSV | `Providers/ProspectsServiceProvider.php`, `Jobs/MasterSyncJob.php` (7th job), `Filament/Pages/SyncDashboardPage.php` (command list + attribution) |
| F | — | `handoff.md`, `docs/ai/context-map.md` |

No `.env`, no `.agent/skills`, no secrets; UTF-8 clean; application-code changes confined to `Modules/Prospects` (+ root `composer.json` in Slice C only).

---

## 11. Design decisions log

| # | Decision | Rationale |
|---|---|---|
| D1 | Interface minimal (`name()` + `fetchClubs()`); failure introspection via concrete `failures()` | Matches approved proposal sketch; keeps contract stable across 7 heterogeneous sources |
| D2 | DTO `NormalizedClub` (readonly) with validated `fromArray` | Typed normalization boundary; array-shape per proposal but enforced |
| D3 | `ContactType` enum reuses existing DB literals (`headquarters`/`venue_name`/`primary`) | Avoids a third value-rename migration; semantics unified instead; downstream (Mailing) verified insensitive to literal values |
| D4 | Location identity via new nullable `prospects_locations.external_id` + unique `(prospect_id, external_id)` | Fixes finding 14 (address-change duplicates); NULL semantics keep LeadService rows safe |
| D5 | Hockey ARBH prefix alignment lands in D2 migration + persister, not Slice B | Slice B carries no data migration (approved scope); re-stamping without migration would orphan ARBH rows via `updateOrCreate` |
| D6 | RBFA Brussels clubs keep `FR-ACFF` | Consistency with existing dataset and the `2026_04_05_163711` precedent; renaming orphans rows |
| D7 | Fake AFT rows soft-retired (`unsubscribed_at`), not re-stamped | No trustworthy identity mapping exists; hard-delete forbidden by R3; fabricating identity twice is worse |
| D8 | Trait extension (`guardedSync`) over command base class | Smaller diff across 6 commands; same crash-safety guarantee |
| D9 | `ExecuteSyncJob` throws on non-zero exit | Single mechanism that trips both job-failed state and the chain catch |
| D10 | Reorder: D1/D2 before C | C's adapter implements the interface; extraction order resolves the proposal's C/D dependency inversion |
| D11 | `smalot/pdfparser` only new dep (Slice C) | Core-PHP for CSV; existing stacks otherwise |

---

## 12. Risks (design-level additions to proposal R1–R7)

| # | Risk | Mitigation |
|---|---|---|
| R8 | AFTT annuaire layout differs from assumed parse model | Fixture cut from verified PDF **before** parser TDD; parsing isolated in one method; `DataSourceException` on zero clubs; manual dashboard verification after first run |
| R9 | D2 backfill window-function migration slow on large locations table | Table is small (prospects in the thousands); guarded UPDATE + idempotent; run in maintenance window alongside PR deploy |
| R10 | Adding 7th chain job (Brussels) lengthens master run under `QUEUE_CONNECTION=sync` | Brussels CSV is 166 KB / ~600 rows; HTTP with 60s timeout; chain is queue-based in prod — observation window after deploy (R6) |
| R11 | `Artisan::shouldReceive` in `ExecuteSyncJobTest` may mask real exit-code regressions | Also assert a real failing command via a test-only command stub registered in the test |
| R12 | RBFA persisted-query hash rotation breaks adapter silently | Existing R5 + `failures()` surface → history `failed` on total outage; hash extracted to class constants for one-line hotfix |

---

## 13. Acceptance criteria for this phase

1. Every adapter emits `NormalizedClub` through the same contract; contract test green for each.
2. `external_id` emission matches §3.2 everywhere; `fromArray` rejects non-conforming ids.
3. No sync command can end a run leaving `SyncHistory` in `running` (guard + flush + chain catch).
4. `records_count` on every history row equals persisted rows after Slice A.
5. 32/88 baseline green at every slice boundary; new tests co-located per module contract.
6. Both D2 migrations reversible; fake-AFT rows retained but unsubscribed.
7. Review budget respected per §9 with `ask-on-risk` gates preserved.

---

## 14. Open items carried to apply

- Exact AFTT annuaire parse rules — derived from committed fixture at start of Slice C (RED first).
- `smalot/pdfparser` exact version constraint — confirm against lockfile + PHP 8.4 at `composer require`.
- Confirm dashboard federation-command list location in `SyncDashboardPage` when adding Brussels card (property scan at apply time).
- Coordinate Slice B merge window with Mailing team (language flip) — human gate, not self-scheduled.