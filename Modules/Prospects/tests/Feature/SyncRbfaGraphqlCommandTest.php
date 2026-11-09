<?php

namespace Modules\Prospects\Tests\Feature;

use GuzzleHttp\Psr7\Response as PsrResponse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Mockery;
use Modules\Prospects\DataObjects\NormalizedClub;
use Modules\Prospects\DataSource\RbfaGraphqlSource;
use Modules\Prospects\Models\Prospect;
use Modules\Prospects\Models\ProspectLocation;
use Modules\Prospects\Models\Region;
use Modules\Prospects\Models\SyncHistory;
use Tests\TestCase;

/**
 * Slice A3 (CLA-535, finding 10) — RBFA GraphQL enrichment resilience.
 *
 * The per-club enrichment request must use a finite timeout with retries,
 * a failure must be persisted as a per-club error SyncHistory entry, and a
 * failed club must never prevent later clubs from being processed.
 *
 * No real HTTP services are called: all requests go through Http::fake().
 */
class SyncRbfaGraphqlCommandTest extends TestCase
{
    use RefreshDatabase;

    private const CLUB_FAIL = 'AAA111';

    private const CLUB_OK = 'BBB222';

    /** @var array<int, array{operationName: ?string, clubId: ?string, timeout: ?int}> */
    private array $capturedRequests = [];

    protected function setUp(): void
    {
        parent::setUp();

        // Narrow the province catalog so the discovery phase performs exactly
        // one (faked) request and the run stays fast.
        config(['rbfa.provinces' => ['Antwerpen' => ['CHP_TEST1']]]);

        // Guzzle middleware runs before Laravel's fake stub handler, so it can
        // observe the merged request options (e.g. the finite timeout) and
        // count attempts even when the fake handler throws.
        $this->capturedRequests = [];
        Http::globalMiddleware(function (callable $handler) {
            return function ($request, array $options) use ($handler) {
                $body = json_decode((string) $request->getBody(), true) ?: [];

                $this->capturedRequests[] = [
                    'operationName' => $body['operationName'] ?? null,
                    'clubId' => $body['variables']['clubId'] ?? null,
                    'timeout' => $options['timeout'] ?? null,
                ];

                return $handler($request, $options);
            };
        });
    }

    // -------------------------------------------------------------------------
    // Fixtures
    // -------------------------------------------------------------------------

    private function seriesRankingsPayload(array $clubIds): array
    {
        return [
            'data' => [
                'seriesRankings' => [
                    'channel' => 'CHP_TEST1',
                    'rankings' => [
                        [
                            'teams' => array_map(fn ($clubId) => [
                                'clubId' => $clubId,
                                'logo' => null,
                            ], $clubIds),
                        ],
                    ],
                ],
            ],
        ];
    }

    private function clubInfoPayload(array $info = []): array
    {
        return [
            'data' => [
                'clubInfo' => array_merge([
                    'name' => 'Test Club',
                    'website' => null,
                    'vatNumber' => null,
                    'postalCode' => null,
                    'contacts' => [],
                ], $info),
            ],
        ];
    }

    /**
     * Fake the RBFA GraphQL endpoint. The failing club's enrichment goes
     * through the given handler (e.g. always 500, or a thrown exception);
     * everything else succeeds with a minimal clubInfo payload.
     */
    private function fakeRbfa(callable $failingClubHandler): void
    {
        Http::fake([
            'datalake-prod2018.rbfa.be/*' => function (Request $request) use ($failingClubHandler) {
                $body = json_decode($request->body(), true) ?: [];

                if (($body['operationName'] ?? null) === 'GetSeriesRankings') {
                    return Http::response(
                        $this->seriesRankingsPayload([self::CLUB_FAIL, self::CLUB_OK])
                    );
                }

                $clubId = $body['variables']['clubId'] ?? null;

                if ($clubId === self::CLUB_FAIL) {
                    return $failingClubHandler();
                }

                return Http::response(
                    $this->clubInfoPayload(['name' => 'Surviving Club '.self::CLUB_OK])
                );
            },
        ]);
    }

    private function runCommand(): void
    {
        $this->artisan('prospects:sync-rbfa-graphql', ['--province' => 'all']);
    }

    private function history(): SyncHistory
    {
        return SyncHistory::query()
            ->where('command', 'prospects:sync-rbfa-graphql')
            ->firstOrFail();
    }

    /** @return array<int, array{time: string, message: string, type: string, icon: string}> */
    private function errorLogs(SyncHistory $history): array
    {
        return array_values(array_filter(
            $history->logs ?? [],
            fn (array $entry) => $entry['type'] === 'error'
        ));
    }

    private function enrichmentRequests(string $clubId): array
    {
        return array_values(array_filter(
            $this->capturedRequests,
            fn (array $r) => $r['operationName'] === 'getClubInfo' && $r['clubId'] === $clubId
        ));
    }

    // -------------------------------------------------------------------------
    // Slice B data correctness
    // -------------------------------------------------------------------------

    public function test_rbfa_discovery_covers_vlaams_brabant_and_brussel(): void
    {
        $rbfaConfig = require config_path('rbfa.php');

        $this->assertNotEmpty($rbfaConfig['provinces']['Vlaams-Brabant']);
        $this->assertNotEmpty($rbfaConfig['provinces']['Brussel']);
        $this->assertContains('CHP_123323', $rbfaConfig['provinces']['Vlaams-Brabant']);
        $this->assertContains('CHP_123321', $rbfaConfig['provinces']['Brussel']);
    }

    public function test_rbfa_series_failure_is_logged_as_error(): void
    {
        config(['rbfa.provinces' => [
            'Antwerpen' => ['CHP_FAIL', 'CHP_OK'],
        ]]);

        Http::fake([
            'datalake-prod2018.rbfa.be/*' => function (Request $request) {
                $body = json_decode($request->body(), true) ?: [];

                if (($body['operationName'] ?? null) === 'GetSeriesRankings') {
                    return ($body['variables']['seriesId'] ?? null) === 'CHP_FAIL'
                        ? Http::response(['error' => 'boom'], 500)
                        : Http::response($this->seriesRankingsPayload([]));
                }

                return Http::response($this->clubInfoPayload());
            },
        ]);

        $this->runCommand();

        $messages = implode("\n", array_column($this->errorLogs($this->history()), 'message'));
        $this->assertStringContainsString('CHP_FAIL', $messages);
        $this->assertStringContainsString('500', $messages);
    }

    public function test_rbfa_region_inference_uses_postal_mapping(): void
    {
        config(['rbfa.provinces' => ['Hainaut' => ['CHP_TEST1']]]);

        Http::fake([
            'datalake-prod2018.rbfa.be/*' => function (Request $request) {
                $body = json_decode($request->body(), true) ?: [];

                if (($body['operationName'] ?? null) === 'GetSeriesRankings') {
                    return Http::response($this->seriesRankingsPayload([self::CLUB_OK]));
                }

                return Http::response($this->clubInfoPayload([
                    'name' => 'Postal Mapping Club',
                    'postalCode' => '2000',
                ]));
            },
        ]);

        $this->runCommand();

        $prospect = Prospect::query()->where('name', 'Postal Mapping Club')->firstOrFail();
        $this->assertSame('Antwerpen', $prospect->region->name);
        $this->assertSame('VL-VV', $prospect->federation);
        $this->assertSame('VL-RBFA-'.self::CLUB_OK, $prospect->external_id);
    }

    public function test_refactored_command_consumes_injected_adapter(): void
    {
        Http::preventStrayRequests();
        $source = Mockery::mock(RbfaGraphqlSource::class);
        $source->shouldReceive('selected')->once()->with('all', null)->andReturnSelf();
        $source->shouldReceive('fetchClubs')->once()->andReturn([
            $this->normalizedClub('ONE'),
            $this->normalizedClub('TWO'),
        ]);
        $source->shouldReceive('failures')->once()->andReturn([]);

        $this->app->instance(
            \Modules\Prospects\Console\Commands\SyncRbfaGraphqlCommand::class,
            new \Modules\Prospects\Console\Commands\SyncRbfaGraphqlCommand($source, new \Modules\Prospects\Services\ClubPersister)
        );

        $this->artisan('prospects:sync-rbfa-graphql')->assertExitCode(0);
        $this->assertDatabaseHas('prospects_prospects', ['external_id' => 'VL-RBFA-ONE']);
        $this->assertDatabaseHas('prospects_prospects', ['external_id' => 'VL-RBFA-TWO']);
    }

    private function normalizedClub(string $id): NormalizedClub
    {
        return NormalizedClub::fromArray([
            'externalId' => "VL-RBFA-{$id}",
            'federation' => 'VL-VV',
            'name' => "Injected {$id}",
            'type' => 'football_club',
            'language' => 'nl',
            'postalCode' => '2000',
        ]);
    }

    // -------------------------------------------------------------------------
    // Timeout / retry request configuration (observed via Guzzle middleware)
    // -------------------------------------------------------------------------

    public function test_enrichment_request_uses_finite_30_second_timeout(): void
    {
        $this->fakeRbfa(fn () => Http::response($this->clubInfoPayload()));
        $this->runCommand();

        $enrichmentRequests = $this->enrichmentRequests(self::CLUB_OK);

        $this->assertNotEmpty($enrichmentRequests, 'Enrichment request was never captured.');
        foreach ($enrichmentRequests as $request) {
            $this->assertSame(30, $request['timeout'], 'Enrichment request must use a finite 30s timeout.');
        }
    }

    public function test_enrichment_request_retries_three_times_on_server_error(): void
    {
        $this->fakeRbfa(fn () => Http::response(['error' => 'boom'], 500));
        $this->runCommand();

        $this->assertCount(
            3,
            $this->enrichmentRequests(self::CLUB_FAIL),
            'A failing enrichment must be attempted exactly 3 times (retry 3, 5000ms delay).'
        );
    }

    public function test_enrichment_request_configures_three_retries_with_five_second_delay(): void
    {
        $discovery = Mockery::mock(PendingRequest::class);
        $discovery->shouldReceive('timeout')->once()->with(60)->andReturnSelf();
        $discovery->shouldReceive('retry')->once()->with(3, 5000)->andReturnSelf();
        $discovery->shouldReceive('post')->once()->andReturn(
            new Response(new PsrResponse(200, [], json_encode($this->seriesRankingsPayload([self::CLUB_OK]))))
        );

        $enrichment = Mockery::mock(PendingRequest::class);
        $enrichment->shouldReceive('timeout')->once()->with(30)->andReturnSelf();
        $enrichment->shouldReceive('retry')->once()->with(3, 5000)->andReturnSelf();
        $enrichment->shouldReceive('post')->once()->andReturn(
            new Response(new PsrResponse(200, [], json_encode($this->clubInfoPayload())))
        );

        Http::shouldReceive('withHeaders')->twice()->andReturn($discovery, $enrichment);

        $this->runCommand();
    }

    // -------------------------------------------------------------------------
    // Failure persistence — per-club error SyncHistory entries
    // -------------------------------------------------------------------------

    public function test_non_success_enrichment_logs_per_club_error_entry(): void
    {
        $this->fakeRbfa(fn () => Http::response(['error' => 'boom'], 500));
        $this->runCommand();

        $errorLogs = $this->errorLogs($this->history());

        $this->assertNotEmpty($errorLogs, 'A non-success enrichment response must be persisted as an error log.');
        $messages = implode("\n", array_column($errorLogs, 'message'));
        $this->assertStringContainsString(self::CLUB_FAIL, $messages, 'Error entry must identify the failing club.');
    }

    public function test_network_exception_logs_per_club_error_entry(): void
    {
        $this->fakeRbfa(fn () => throw new ConnectionException('cURL error 28: Connection timed out'));
        $this->runCommand();

        $errorLogs = $this->errorLogs($this->history());

        $this->assertNotEmpty($errorLogs, 'A network exception must be persisted as an error log.');
        $messages = implode("\n", array_column($errorLogs, 'message'));
        $this->assertStringContainsString(self::CLUB_FAIL, $messages, 'Error entry must identify the failing club.');
    }

    // -------------------------------------------------------------------------
    // Resilience — a failed club must not stop later clubs
    // -------------------------------------------------------------------------

    public function test_later_club_still_processed_after_failed_enrichment(): void
    {
        $this->fakeRbfa(fn () => Http::response(['error' => 'boom'], 500));
        $this->runCommand();

        $this->assertDatabaseHas('prospects_prospects', [
            'external_id' => 'VL-RBFA-'.self::CLUB_OK,
        ]);

        $history = $this->history();
        $this->assertSame('failed', $history->status, 'Source failures must fail the run after survivors persist.');
        $this->assertSame(1, $history->records_count);
        $this->assertDatabaseMissing('prospects_prospects', [
            'external_id' => 'VL-RBFA-'.self::CLUB_FAIL,
        ]);
    }

    public function test_later_club_still_processed_after_network_exception(): void
    {
        $this->fakeRbfa(fn () => throw new ConnectionException('cURL error 28: Connection timed out'));
        $this->runCommand();

        $this->assertDatabaseHas('prospects_prospects', [
            'external_id' => 'VL-RBFA-'.self::CLUB_OK,
        ]);

        $survivor = Prospect::query()->where('external_id', 'VL-RBFA-'.self::CLUB_OK)->first();
        $this->assertSame('Surviving Club '.self::CLUB_OK, $survivor->name);

        $this->assertSame('failed', $this->history()->status);
    }

    // -------------------------------------------------------------------------
    // CLA-655 — reconcile with existing clubs, fill-only
    // -------------------------------------------------------------------------

    /** @param array<string, array<string, mixed>> $infoByClub */
    private function fakeClubs(array $infoByClub): void
    {
        Http::fake([
            'datalake-prod2018.rbfa.be/*' => function (Request $request) use ($infoByClub) {
                $body = json_decode($request->body(), true) ?: [];

                if (($body['operationName'] ?? null) === 'GetSeriesRankings') {
                    return Http::response($this->seriesRankingsPayload(array_keys($infoByClub)));
                }

                return Http::response($this->clubInfoPayload($infoByClub[$body['variables']['clubId']]));
            },
        ]);
    }

    private function legacyProspect(array $attributes): Prospect
    {
        return Prospect::create(array_replace([
            'type' => 'football_club', 'federation' => 'VL-VV', 'language' => 'nl', 'external_id' => null,
            'region_id' => Region::firstOrCreate(['name' => 'Overige'], ['slug' => 'overige'])->id,
        ], $attributes));
    }

    private function snapshot(): array
    {
        return [
            Prospect::query()->orderBy('id')->get()->toArray(),
            ProspectLocation::query()->orderBy('id')->get()->toArray(),
        ];
    }

    private function warningMessages(): string
    {
        $warnings = array_filter($this->history()->logs ?? [], fn (array $e) => $e['type'] === 'warning');

        return implode("\n", array_column($warnings, 'message'));
    }

    public function test_s4_province_hint_sets_region_when_postal_code_is_missing(): void
    {
        config(['rbfa.provinces' => ['Limburg' => ['CHP_TEST1']]]);
        $this->fakeClubs([self::CLUB_OK => ['name' => 'No Postal Club']]);

        $this->runCommand();

        $this->assertSame('Limburg', Prospect::query()->where('name', 'No Postal Club')->firstOrFail()->region->name);
    }

    public function test_s1_legacy_club_is_reused_not_duplicated_and_keeps_null_external_id(): void
    {
        $legacy = $this->legacyProspect(['name' => 'Legacy Club', 'vat_number' => '0123.456.789']);
        $this->fakeClubs([self::CLUB_OK => [
            'name' => 'Renamed Upstream', 'vatNumber' => 'BE0123456789', 'postalCode' => '2000',
            'contacts' => [['mail' => 'club@legacy.test']],
        ]]);

        $this->runCommand();

        $this->assertDatabaseCount('prospects_prospects', 1);
        $fresh = $legacy->fresh();
        $this->assertNull($fresh->external_id);
        $this->assertSame('Legacy Club', $fresh->name);
        $this->assertSame('Antwerpen', $fresh->region->name);
        $this->assertDatabaseHas('prospects_locations', ['prospect_id' => $legacy->id, 'email' => 'club@legacy.test']);
    }

    public function test_s6_ambiguous_legacy_and_skipped_location_are_recorded_as_sync_events(): void
    {
        $this->legacyProspect(['name' => 'Twin Club']);
        $this->legacyProspect(['name' => 'Twin Club']);
        $holder = $this->legacyProspect(['name' => 'Holder']);
        ProspectLocation::create(['prospect_id' => $holder->id, 'contact_type' => 'headquarters', 'address' => 'x', 'email' => 'taken@club.test']);
        $this->fakeClubs([
            self::CLUB_FAIL => ['name' => 'Twin Club'],
            self::CLUB_OK => ['name' => 'Colliding Club', 'contacts' => [['mail' => 'taken@club.test']]],
        ]);

        $this->runCommand();

        $warnings = $this->warningMessages();
        $this->assertStringContainsString('VL-RBFA-'.self::CLUB_FAIL, $warnings);
        $this->assertStringContainsString('VL-RBFA-'.self::CLUB_OK.'::hq', $warnings);
        $this->assertStringContainsString('taken@club.test', $warnings);
        $this->assertDatabaseHas('prospects_prospects', ['external_id' => 'VL-RBFA-'.self::CLUB_OK]);
        $this->assertDatabaseMissing('prospects_prospects', ['external_id' => 'VL-RBFA-'.self::CLUB_FAIL]);
        $this->assertDatabaseCount('prospects_prospects', 4);

        // An ambiguous club is neither persisted nor failed.
        $messages = array_column($this->history()->logs ?? [], 'message');
        $this->assertStringContainsString('processed 1 | persisted 1 | failed 0', implode("\n", $messages));
    }

    public function test_s5_existing_location_email_collision_is_recorded_as_email_skipped(): void
    {
        $holder = $this->legacyProspect(['name' => 'Holder']);
        ProspectLocation::create(['prospect_id' => $holder->id, 'contact_type' => 'headquarters', 'address' => 'x', 'email' => 'taken@club.test']);
        $existing = $this->legacyProspect(['name' => 'Known Club', 'external_id' => 'VL-RBFA-'.self::CLUB_OK]);
        ProspectLocation::create([
            'prospect_id' => $existing->id, 'external_id' => 'VL-RBFA-'.self::CLUB_OK.'::hq',
            'contact_type' => 'headquarters', 'address' => 'Kept 1',
        ]);
        $this->fakeClubs([self::CLUB_OK => ['name' => 'Known Club', 'contacts' => [['mail' => 'taken@club.test', 'phone' => '012']]]]);

        $this->runCommand();

        $warnings = $this->warningMessages();
        $this->assertStringContainsString('Email taken@club.test skipped', $warnings);
        $this->assertStringContainsString('VL-RBFA-'.self::CLUB_OK.'::hq', $warnings);
        $this->assertStringNotContainsString('Location VL-RBFA', $warnings);
        $this->assertDatabaseHas('prospects_locations', ['prospect_id' => $existing->id, 'email' => null, 'phone' => '012']);
    }

    public function test_s8_running_the_same_sync_twice_creates_and_changes_nothing(): void
    {
        $this->legacyProspect(['name' => 'Legacy Club', 'vat_number' => '0123456789']);
        $this->fakeClubs([
            self::CLUB_FAIL => ['name' => 'Legacy Club', 'vatNumber' => '0123456789', 'postalCode' => '2000', 'contacts' => [['mail' => 'a@club.test']]],
            self::CLUB_OK => ['name' => 'Fresh Club', 'contacts' => [['mail' => 'b@club.test', 'phone' => '012']]],
        ]);

        $this->runCommand();
        $first = $this->snapshot();
        $this->assertCount(2, $first[0]);

        $this->runCommand();

        $this->assertSame($first, $this->snapshot());
    }

    // -------------------------------------------------------------------------
    // CLA-663 — the stored sync history says why each series failed
    // -------------------------------------------------------------------------

    private const ACCESS_DENIED_HTML = '<HTML><HEAD> <TITLE>Access Denied</TITLE> </HEAD><BODY> <H1>Access Denied</H1> '
        .'You don\'t have permission to access "http&#58;&#47;&#47;datalake&#45;prod2018.rbfa.be&#47;graphql" on this server.<P> '
        .'Reference&#32;&#35;18&#46;abc123 </BODY></HTML>';

    private const ACCESS_DENIED_REASON = 'HTTP 200 text/html: Access Denied Access Denied You don\'t have permission to access '
        .'"http://datalake-prod2018.rbfa.be/graphql" on this server. Reference #…';

    private function fakeAccessDenied(): void
    {
        Http::fake([
            'datalake-prod2018.rbfa.be/*' => Http::response(self::ACCESS_DENIED_HTML, 200, ['Content-Type' => 'text/html']),
        ]);
    }

    public function test_s3_all_series_failing_stores_grouped_reasons_before_the_final_error(): void
    {
        config(['rbfa.provinces' => ['Antwerpen' => ['CHP_A', 'CHP_B'], 'Limburg' => ['CHP_C']]]);
        $this->fakeAccessDenied();

        $this->artisan('prospects:sync-rbfa-graphql')->assertExitCode(1);

        $history = $this->history();
        $this->assertSame('failed', $history->status);
        $messages = array_column($history->logs, 'message');
        $reasonIndex = array_search('3 series failed: '.self::ACCESS_DENIED_REASON, $messages, true);
        $finalIndex = array_search('Error: All RBFA discovery series failed.', $messages, true);
        $this->assertNotFalse($reasonIndex, 'Grouped reason line missing: '.implode(' | ', $messages));
        $this->assertNotFalse($finalIndex);
        $this->assertLessThan($finalIndex, $reasonIndex);
        $this->assertSame(['error', '❌'], [$history->logs[$reasonIndex]['type'], $history->logs[$reasonIndex]['icon']]);
    }

    public function test_s3_distinct_reasons_are_grouped_with_their_own_counts(): void
    {
        config(['rbfa.provinces' => ['Antwerpen' => ['CHP_A', 'CHP_B', 'CHP_C']]]);
        Http::fake([
            'datalake-prod2018.rbfa.be/*' => function (Request $request) {
                $series = json_decode($request->body(), true)['variables']['seriesId'];

                return $series === 'CHP_C'
                    ? Http::response(['errors' => [['message' => 'PersistedQueryNotFound']]])
                    : Http::response(self::ACCESS_DENIED_HTML, 200, ['Content-Type' => 'text/html']);
            },
        ]);

        $this->artisan('prospects:sync-rbfa-graphql')->assertExitCode(1);

        $messages = array_column($this->history()->logs, 'message');
        $this->assertContains('2 series failed: '.self::ACCESS_DENIED_REASON, $messages);
        $this->assertContains('1 series failed: HTTP 200 application/json: PersistedQueryNotFound', $messages);
    }

    public function test_s3_akamai_reference_ids_do_not_split_the_grouped_reason(): void
    {
        config(['rbfa.provinces' => ['Antwerpen' => ['CHP_A', 'CHP_B']]]);
        $page = fn (string $ref) => '<HTML><HEAD> <TITLE>Access Denied</TITLE> </HEAD><BODY> <H1>Access Denied</H1> '
            .'You don\'t have permission to access "http&#58;&#47;&#47;datalake&#45;prod2018&#46;rbfa&#46;be&#47;graphql" on this server.<P> '
            .'Reference&#32;&#35;'.str_replace('.', '&#46;', $ref).' <P>https&#58;&#47;&#47;errors&#46;edgesuite&#46;net&#47;'
            .str_replace('.', '&#46;', $ref).'</BODY></HTML>';
        Http::fake([
            'datalake-prod2018.rbfa.be/*' => function (Request $request) use ($page) {
                $series = json_decode($request->body(), true)['variables']['seriesId'];
                $ref = $series === 'CHP_A' ? '18.458a1402.1794173441.251facdb' : '18.512b3302.1794173450.9c0fdbad';

                return Http::response($page($ref), 200, ['Content-Type' => 'text/html']);
            },
        ]);

        $this->artisan('prospects:sync-rbfa-graphql')->assertExitCode(1);

        $grouped = array_values(array_filter(
            array_column($this->history()->logs, 'message'),
            fn (string $message) => str_contains($message, 'series failed:'),
        ));
        $this->assertCount(1, $grouped, implode(' | ', $grouped));
        $this->assertStringStartsWith('2 series failed: HTTP 200 text/html: Access Denied', $grouped[0]);
        $this->assertStringNotContainsString('458a1402', $grouped[0]);
    }

    public function test_s4_all_series_failing_writes_exactly_one_warning_with_reasons_and_counts(): void
    {
        config(['rbfa.provinces' => ['Antwerpen' => ['CHP_A', 'CHP_B'], 'Limburg' => ['CHP_C']]]);
        $this->fakeAccessDenied();
        Log::spy();

        $this->artisan('prospects:sync-rbfa-graphql')->assertExitCode(1);

        Log::shouldHaveReceived('warning')->once()->withArgs(
            fn (string $message, array $context = []) => str_contains($message, 'All RBFA discovery series failed')
                && ($context['reasons'] ?? null) === [self::ACCESS_DENIED_REASON => 3]
        );
    }

    public function test_s5_partial_failure_uses_the_new_reason_and_still_persists_survivors(): void
    {
        config(['rbfa.provinces' => ['Antwerpen' => ['CHP_BAD', 'CHP_OK']]]);
        Log::spy();
        Http::fake([
            'datalake-prod2018.rbfa.be/*' => function (Request $request) {
                $body = json_decode($request->body(), true);

                if (($body['variables']['seriesId'] ?? null) === 'CHP_BAD') {
                    return Http::response(self::ACCESS_DENIED_HTML, 200, ['Content-Type' => 'text/html']);
                }

                return ($body['operationName'] ?? null) === 'GetSeriesRankings'
                    ? Http::response($this->seriesRankingsPayload([self::CLUB_OK]))
                    : Http::response($this->clubInfoPayload(['name' => 'Survivor']));
            },
        ]);

        $this->artisan('prospects:sync-rbfa-graphql')->assertExitCode(1);

        $history = $this->history();
        $this->assertSame('failed', $history->status);
        $this->assertSame(1, $history->records_count);
        $this->assertContains(
            'Series CHP_BAD (Antwerpen) failed: '.self::ACCESS_DENIED_REASON,
            array_column($this->errorLogs($history), 'message')
        );
        $this->assertContains('RBFA source completed with 1 failure(s).', array_map(
            fn (string $m) => str_replace('Error: ', '', $m),
            array_column($this->errorLogs($history), 'message')
        ));
        Log::shouldNotHaveReceived('warning');
        $this->assertDatabaseHas('prospects_prospects', ['external_id' => 'VL-RBFA-'.self::CLUB_OK, 'name' => 'Survivor']);
    }

    public function test_s5_successful_run_still_persists_clubs_and_completes(): void
    {
        $this->fakeRbfa(fn () => Http::response($this->clubInfoPayload()));

        $this->artisan('prospects:sync-rbfa-graphql')->assertExitCode(0);

        $history = $this->history();
        $this->assertSame('completed', $history->status);
        $this->assertSame(2, $history->records_count);
        $this->assertSame([], $this->errorLogs($history));
        $this->assertDatabaseHas('prospects_prospects', ['external_id' => 'VL-RBFA-'.self::CLUB_OK, 'name' => 'Surviving Club '.self::CLUB_OK]);
        $this->assertDatabaseHas('prospects_prospects', ['external_id' => 'VL-RBFA-'.self::CLUB_FAIL, 'name' => 'Test Club']);
    }
}
