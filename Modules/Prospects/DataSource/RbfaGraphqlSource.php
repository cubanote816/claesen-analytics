<?php

namespace Modules\Prospects\DataSource;

use Closure;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Modules\Prospects\Contracts\FederationDataSource;
use Modules\Prospects\DataObjects\ClubLocation;
use Modules\Prospects\DataObjects\NormalizedClub;
use Modules\Prospects\Exceptions\DataSourceException;
use Throwable;

class RbfaGraphqlSource implements FederationDataSource
{
    public const DEFAULT_URL = 'https://datalake-prod2018.rbfa.be/graphql';

    private const DISCOVERY_HASH = '0a53124a9bc8872b686f22d80fd545622dbaf4b27a7596e1207b097b92c87953';

    private const ENRICHMENT_HASH = '7c1bd99f0001a20d60208c60d4fb7c99aefdb810b9ee1c4de21a6d6ba4804b58';

    /** @var array<int, array{series: string, province: string, status: int|string}> */
    private array $failures = [];

    private string $apiUrl;

    /** @var array<string, array<int, string>> */
    private array $provincesConfig;

    private ?Closure $requester;

    private Closure $throttle;

    private ?int $limit = null;

    public function __construct(
        ?string $apiUrl = null,
        ?array $provincesConfig = null,
        ?callable $requester = null,
        ?callable $throttle = null,
    ) {
        $this->apiUrl = $apiUrl ?? self::DEFAULT_URL;
        $this->provincesConfig = $provincesConfig ?? config('rbfa.provinces', []);
        $this->requester = $requester === null ? null : Closure::fromCallable($requester);
        $this->throttle = Closure::fromCallable($throttle ?? static fn () => sleep(1));
    }

    public function name(): string
    {
        return 'RBFA';
    }

    public function selected(string $province = 'all', ?int $limit = null): self
    {
        $source = clone $this;
        if ($province !== 'all') {
            if (! isset($source->provincesConfig[$province])) {
                throw new DataSourceException("Province '{$province}' not found in configuration.");
            }
            $source->provincesConfig = [$province => $source->provincesConfig[$province]];
        }
        $source->limit = $limit;

        return $source;
    }

    /** @return array<int, NormalizedClub> */
    public function fetchClubs(): array
    {
        $this->failures = [];
        $clubs = [];
        $successfulSeries = 0;

        foreach ($this->provincesConfig as $province => $seriesIds) {
            foreach ($seriesIds as $seriesId) {
                try {
                    $response = $this->post($this->discoveryPayload($seriesId), 60);
                    $rankings = $response->successful()
                        ? ($response->json()['data']['seriesRankings'] ?? null)
                        : null;

                    if (! $response->successful() || $rankings === null) {
                        $this->recordFailure($seriesId, $province, $response->successful() ? 'unparseable' : $response->status());
                        continue;
                    }

                    $successfulSeries++;
                    foreach ($rankings['rankings'] ?? [] as $ranking) {
                        foreach ($ranking['teams'] ?? [] as $team) {
                            $clubId = $team['clubId'] ?? null;
                            if ($clubId && ! isset($clubs[$clubId])) {
                                $clubs[$clubId] = [
                                    'id' => (string) $clubId,
                                    'logo' => $team['logo'] ?? null,
                                    'province' => $province,
                                    'channel' => $rankings['channel'] ?? null,
                                ];
                            }
                        }
                    }
                } catch (Throwable $exception) {
                    $this->recordFailure($seriesId, $province, $exception->getMessage());
                } finally {
                    ($this->throttle)();
                }
            }
        }

        if ($successfulSeries === 0) {
            throw new DataSourceException('All RBFA discovery series failed.');
        }

        if ($this->limit !== null) {
            $clubs = array_slice($clubs, 0, $this->limit, true);
        }

        $normalized = [];
        foreach ($clubs as $club) {
            try {
                $response = $this->post($this->enrichmentPayload($club['id']), 30);
                $info = $response->successful() ? ($response->json()['data']['clubInfo'] ?? null) : null;

                if (! $response->successful() || $info === null) {
                    $this->recordFailure($club['id'], $club['province'], $response->successful() ? 'unparseable' : $response->status());
                    continue;
                }

                $normalized[] = $this->normalize($club, $info);
            } catch (Throwable $exception) {
                $this->recordFailure($club['id'], $club['province'], $exception->getMessage());
            }
        }

        return $normalized;
    }

    /** @return array<int, array{series: string, province: string, status: int|string}> */
    public function failures(): array
    {
        return $this->failures;
    }

    private function post(array $payload, int $timeout): Response
    {
        if ($this->requester !== null) {
            return ($this->requester)($payload, $timeout);
        }

        return Http::withHeaders([
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ])->timeout($timeout)->retry(3, 5000)->post($this->apiUrl, $payload);
    }

    private function discoveryPayload(string $seriesId): array
    {
        return $this->payload('GetSeriesRankings', ['seriesId' => $seriesId, 'language' => 'en'], self::DISCOVERY_HASH);
    }

    private function enrichmentPayload(string $clubId): array
    {
        return $this->payload('getClubInfo', ['clubId' => $clubId, 'language' => 'en'], self::ENRICHMENT_HASH);
    }

    private function payload(string $operation, array $variables, string $hash): array
    {
        return [
            'operationName' => $operation,
            'variables' => $variables,
            'extensions' => ['persistedQuery' => ['version' => 1, 'sha256Hash' => $hash]],
        ];
    }

    private function normalize(array $club, array $info): NormalizedClub
    {
        $postalCode = isset($info['postalCode']) ? (string) $info['postalCode'] : null;
        $isFlemish = $this->isFlemish($postalCode, $club['province']);
        $emails = $this->joinedContacts($info['contacts'] ?? [], 'mail');
        $phones = $this->joinedContacts($info['contacts'] ?? [], 'phone');
        $contactName = $this->contactName($info['contacts'] ?? []);
        $address = implode(', ', array_filter([
            $info['streetName'] ?? null,
            $postalCode,
            $info['localityName'] ?? null,
        ]));

        return NormalizedClub::fromArray([
            'externalId' => ($isFlemish ? 'VL-' : 'FR-').'RBFA-'.$club['id'],
            'federation' => $isFlemish ? 'VL-VV' : 'FR-ACFF',
            'name' => $info['name'] ?? 'Unknown Club',
            'type' => 'football_club',
            'language' => $isFlemish ? 'nl' : 'fr',
            'postalCode' => $postalCode,
            'website' => $info['website'] ?? null,
            'logoUrl' => $club['logo'],
            'vatNumber' => $info['vatNumber'] ?? null,
            'contactPerson' => $contactName,
            'channel' => $club['channel'],
            'headquarters' => new ClubLocation(
                address: $address,
                contactName: $contactName,
                email: $emails,
                phone: $phones,
                sourceLocationId: $club['id'],
            ),
        ]);
    }

    private function isFlemish(?string $postalCode, string $province): bool
    {
        if ($postalCode === null || $postalCode === '') {
            return in_array($province, config('rbfa.flemish_regions', []), true);
        }

        $code = (int) $postalCode;

        return ($code >= 1500 && $code <= 3999) || ($code >= 8000 && $code <= 9999);
    }

    private function joinedContacts(array $contacts, string $key): ?string
    {
        $values = [];
        foreach ($contacts as $contact) {
            $values = array_merge($values, (array) ($contact[$key] ?? []));
        }

        $joined = substr(implode(', ', array_unique(array_filter($values))), 0, 250);

        return $joined !== '' ? $joined : null;
    }

    private function contactName(array $contacts): ?string
    {
        foreach ($contacts as $contact) {
            $name = trim(($contact['firstName'] ?? '').' '.($contact['lastName'] ?? ''));
            if ($name !== '') {
                return $name;
            }
        }

        return null;
    }

    private function recordFailure(string $series, string $province, int|string $status): void
    {
        $this->failures[] = compact('series', 'province', 'status');
    }
}
