<?php

namespace Modules\Prospects\Tests\Feature;

use GuzzleHttp\Psr7\Response as PsrResponse;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Mockery;
use Modules\Prospects\DataObjects\NormalizedClub;
use Modules\Prospects\DataSource\RbfaGraphqlSource;
use Modules\Prospects\Exceptions\DataSourceException;
use Tests\TestCase;

class RbfaGraphQLSourceTest extends TestCase
{
    public function test_fetch_clubs_discovers_enriches_and_normalizes_headquarters(): void
    {
        $source = $this->source(
            ['Antwerpen' => ['SERIES']],
            fn (array $payload) => ($payload['operationName'] === 'GetSeriesRankings')
                ? $this->response($this->seriesPayload(['CLUB1']))
                : $this->response($this->clubPayload('CLUB1', '2000'))
        );

        $clubs = $source->fetchClubs();

        $this->assertCount(1, $clubs);
        $this->assertInstanceOf(NormalizedClub::class, $clubs[0]);
        $this->assertSame('VL-RBFA-CLUB1', $clubs[0]->externalId);
        $this->assertSame('RBFA Club CLUB1', $clubs[0]->name);
        $this->assertSame('Main Street 1, 2000, Antwerpen', $clubs[0]->headquarters?->address);
        $this->assertSame('CLUB1', $clubs[0]->headquarters?->sourceLocationId);
        $this->assertSame('secretary@example.test', $clubs[0]->headquarters?->email);
    }

    public function test_federation_is_derived_from_postal_code(): void
    {
        $postals = ['FL' => '2000', 'WA' => '4000', 'BR' => '1000'];
        $source = $this->source(
            ['Hainaut' => ['SERIES']],
            fn (array $payload) => ($payload['operationName'] === 'GetSeriesRankings')
                ? $this->response($this->seriesPayload(array_keys($postals)))
                : $this->response($this->clubPayload(
                    $payload['variables']['clubId'],
                    $postals[$payload['variables']['clubId']]
                ))
        );

        $clubs = [];
        foreach ($source->fetchClubs() as $club) {
            $clubs[$club->name] = $club;
        }

        $this->assertSame(['VL-VV', 'nl'], [$clubs['RBFA Club FL']->federation, $clubs['RBFA Club FL']->language]);
        $this->assertSame(['FR-ACFF', 'fr'], [$clubs['RBFA Club WA']->federation, $clubs['RBFA Club WA']->language]);
        $this->assertSame(['FR-ACFF', 'fr'], [$clubs['RBFA Club BR']->federation, $clubs['RBFA Club BR']->language]);
    }

    public function test_partial_series_and_enrichment_failures_are_collected(): void
    {
        $source = $this->source(
            ['Antwerpen' => ['SERIES_FAIL', 'SERIES_OK']],
            function (array $payload): Response {
                if (($payload['variables']['seriesId'] ?? null) === 'SERIES_FAIL') {
                    return $this->response([], 500);
                }

                if ($payload['operationName'] === 'GetSeriesRankings') {
                    return $this->response($this->seriesPayload(['CLUB_FAIL', 'CLUB_OK']));
                }

                return $payload['variables']['clubId'] === 'CLUB_FAIL'
                    ? $this->response([], 503)
                    : $this->response($this->clubPayload('CLUB_OK', '2000'));
            }
        );

        $clubs = $source->fetchClubs();

        $this->assertCount(1, $clubs);
        $this->assertSame('CLUB_OK', $clubs[0]->headquarters?->sourceLocationId);
        $this->assertSame(['SERIES_FAIL', 'CLUB_FAIL'], array_column($source->failures(), 'series'));
    }

    public function test_unparseable_series_is_recorded_as_unparseable(): void
    {
        $source = $this->source(['Antwerpen' => ['BROKEN']], fn () => $this->response(['data' => []]));

        try {
            $source->fetchClubs();
        } catch (DataSourceException) {
        }

        $this->assertSame('unparseable', $source->failures()[0]['status']);
    }

    public function test_all_series_fail_throws_data_source_exception(): void
    {
        $source = $this->source(
            ['Antwerpen' => ['SERIES_FAIL']],
            fn () => $this->response([], 500)
        );

        $this->expectException(DataSourceException::class);

        $source->fetchClubs();
    }

    public function test_discovery_and_enrichment_keep_timeout_and_retry_contract(): void
    {
        $discovery = Mockery::mock(PendingRequest::class);
        $discovery->shouldReceive('timeout')->once()->with(60)->andReturnSelf();
        $discovery->shouldReceive('retry')->once()->with(3, 5000)->andReturnSelf();
        $discovery->shouldReceive('post')->once()->with(RbfaGraphqlSource::DEFAULT_URL, Mockery::type('array'))
            ->andReturn($this->response($this->seriesPayload(['CLUB1'])));

        $enrichment = Mockery::mock(PendingRequest::class);
        $enrichment->shouldReceive('timeout')->once()->with(30)->andReturnSelf();
        $enrichment->shouldReceive('retry')->once()->with(3, 5000)->andReturnSelf();
        $enrichment->shouldReceive('post')->once()->with(RbfaGraphqlSource::DEFAULT_URL, Mockery::type('array'))
            ->andReturn($this->response($this->clubPayload('CLUB1', '2000')));

        Http::shouldReceive('withHeaders')->twice()->andReturn($discovery, $enrichment);

        $this->source(['Antwerpen' => ['SERIES']])->fetchClubs();
    }

    private function source(array $provinces, ?callable $requester = null): RbfaGraphqlSource
    {
        return new RbfaGraphqlSource(
            provincesConfig: $provinces,
            requester: $requester,
            throttle: static fn () => null,
        );
    }

    private function seriesPayload(array $clubIds): array
    {
        return ['data' => ['seriesRankings' => [
            'channel' => 'SERIES',
            'rankings' => [['teams' => array_map(
                fn (string $clubId) => ['clubId' => $clubId, 'logo' => "{$clubId}.png"],
                $clubIds
            )]],
        ]]];
    }

    private function clubPayload(string $clubId, string $postalCode): array
    {
        return ['data' => ['clubInfo' => [
            'name' => "RBFA Club {$clubId}",
            'streetName' => 'Main Street 1',
            'postalCode' => $postalCode,
            'localityName' => 'Antwerpen',
            'website' => 'https://club.example.test',
            'vatNumber' => 'BE0123456789',
            'contacts' => [[
                'firstName' => 'Test',
                'lastName' => 'Secretary',
                'mail' => ['secretary@example.test'],
                'phone' => ['+32 1 234 56 78'],
            ]],
        ]]];
    }

    private function response(array $body, int $status = 200): Response
    {
        return new Response(new PsrResponse($status, [], json_encode($body, JSON_THROW_ON_ERROR)));
    }
}
