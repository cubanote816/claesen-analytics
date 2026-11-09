<?php

namespace Modules\Prospects\Tests\Feature;

use GuzzleHttp\Psr7\Response as PsrResponse;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
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
        $this->assertSame('Antwerpen', $clubs[0]->regionHint);
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

        $this->assertSame('HTTP 200 application/json: {"data":[]}', $source->failures()[0]['status']);
    }

    // CLA-663 — every failure carries a human-readable reason

    private const ACCESS_DENIED_HTML = '<HTML><HEAD> <TITLE>Access Denied</TITLE> </HEAD><BODY> <H1>Access Denied</H1> '
        .'You don\'t have permission to access "http&#58;&#47;&#47;datalake&#45;prod2018.rbfa.be&#47;graphql" on this server.<P> '
        .'Reference&#32;&#35;18&#46;abc123 </BODY></HTML>';

    private const ACCESS_DENIED_REASON = 'HTTP 200 text/html: Access Denied Access Denied You don\'t have permission to access '
        .'"http://datalake-prod2018.rbfa.be/graphql" on this server. Reference #…';

    private function rawResponse(string $body, int $status = 200, string $contentType = 'text/html'): Response
    {
        return new Response(new PsrResponse($status, ['Content-Type' => $contentType], $body));
    }

    private function failureReasons(RbfaGraphqlSource $source): array
    {
        try {
            $source->fetchClubs();
        } catch (DataSourceException) {
        }

        return array_column($source->failures(), 'status');
    }

    public function test_s1_html_access_denied_with_http_200_is_a_series_failure_with_cleaned_snippet(): void
    {
        $source = $this->source(['Antwerpen' => ['BROKEN']], fn () => $this->rawResponse(self::ACCESS_DENIED_HTML));

        $this->assertSame([self::ACCESS_DENIED_REASON], $this->failureReasons($source));
        $this->assertSame('BROKEN', $source->failures()[0]['series']);
        $this->assertSame('Antwerpen', $source->failures()[0]['province']);
    }

    public function test_s1_long_non_json_body_is_cut_to_200_characters_with_ellipsis(): void
    {
        $source = $this->source(['Antwerpen' => ['BROKEN']], fn () => $this->rawResponse('<p>'.str_repeat('x', 500).'</p>'));

        $this->assertSame(['HTTP 200 text/html: '.str_repeat('x', 200).'…'], $this->failureReasons($source));
    }

    public function test_s1_graphql_errors_report_the_first_error_message(): void
    {
        $body = json_encode(['errors' => [['message' => 'PersistedQueryNotFound'], ['message' => 'second']]]);
        $source = $this->source(
            ['Antwerpen' => ['BROKEN']],
            fn () => $this->rawResponse($body, 200, 'application/json')
        );

        $this->assertSame(['HTTP 200 application/json: PersistedQueryNotFound'], $this->failureReasons($source));
    }

    public function test_s2_exception_reason_is_class_short_name_plus_truncated_message(): void
    {
        $source = $this->source(['Antwerpen' => ['A', 'B', 'C']], function (array $payload): Response {
            return match ($payload['variables']['seriesId']) {
                'A' => throw new ConnectionException('cURL error 28: Connection timed out'),
                'B' => throw new RequestException($this->rawResponse('nope', 403, 'text/plain')),
                default => throw new ConnectionException(str_repeat('m', 500)),
            };
        });

        $reasons = $this->failureReasons($source);

        $this->assertSame('ConnectionException: cURL error 28: Connection timed out', $reasons[0]);
        $this->assertStringStartsWith('RequestException: HTTP request returned status code 403', $reasons[1]);
        $this->assertSame('ConnectionException: '.str_repeat('m', 200).'…', $reasons[2]);
    }

    public function test_s6_enrichment_html_body_gets_the_same_reason_format_and_stays_non_fatal(): void
    {
        $source = $this->source(
            ['Antwerpen' => ['SERIES']],
            fn (array $payload) => $payload['operationName'] === 'GetSeriesRankings'
                ? $this->response($this->seriesPayload(['CLUB1']))
                : $this->rawResponse(self::ACCESS_DENIED_HTML)
        );

        $this->assertSame([], $source->fetchClubs());
        $this->assertSame(
            [['series' => 'CLUB1', 'province' => 'Antwerpen', 'status' => self::ACCESS_DENIED_REASON]],
            $source->failures()
        );
    }

    public function test_s5_http_error_status_reason_keeps_the_status_code(): void
    {
        $source = $this->source(['Antwerpen' => ['A', 'B']], fn (array $payload) => $payload['variables']['seriesId'] === 'A'
            ? $this->response(['error' => 'boom'], 500)
            : $this->response($this->seriesPayload([])));

        $source->fetchClubs();

        $this->assertSame('HTTP 500 application/json: {"error":"boom"}', $source->failures()[0]['status'] ?? null);
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
        return new Response(new PsrResponse($status, ['Content-Type' => 'application/json'], json_encode($body, JSON_THROW_ON_ERROR)));
    }
}
