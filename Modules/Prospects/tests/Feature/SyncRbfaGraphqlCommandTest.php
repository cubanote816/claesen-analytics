<?php

namespace Modules\Prospects\Tests\Feature;

use GuzzleHttp\Psr7\Response as PsrResponse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Mockery;
use Modules\Prospects\DataObjects\NormalizedClub;
use Modules\Prospects\DataSource\RbfaGraphqlSource;
use Modules\Prospects\Models\Prospect;
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
}
