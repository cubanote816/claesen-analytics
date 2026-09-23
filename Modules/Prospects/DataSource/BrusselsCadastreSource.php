<?php

namespace Modules\Prospects\DataSource;

use Illuminate\Support\Facades\Http;
use Modules\Prospects\Contracts\FederationDataSource;
use Modules\Prospects\DataObjects\NormalizedClub;
use Modules\Prospects\Exceptions\DataSourceException;

/**
 * Source: Infrastructures sportives — Région de Bruxelles-Capitale.
 *
 * @license CC-BY-2.0 https://data.gov.be/en/datasets/fed2f7cb-2159-40e0-8cef-c6531901f188
 */
class BrusselsCadastreSource implements FederationDataSource
{
    public const URL = 'https://backend.datastore.brussels/rest/metadata/fed2f7cb-2159-40e0-8cef-c6531901f188'
        .'/resource/969a8337-68f4-476a-b204-a5b3be86e043/download/infra_export_opendata_1.csv';

    public function __construct(private string $url = self::URL)
    {
    }

    public function name(): string
    {
        return 'BR-CAD';
    }

    /** @return array<int, NormalizedClub> */
    public function fetchClubs(): array
    {
        $response = Http::timeout(60)->retry(2, 5000, throw: false)->get($this->url);

        if (! $response->successful()) {
            throw new DataSourceException("Failed to download Brussels cadastre CSV: HTTP {$response->status()}.");
        }

        $body = ltrim($response->body(), "\xEF\xBB\xBF");
        $rows = array_map('str_getcsv', preg_split('/\R/', trim($body)));
        $header = array_shift($rows);

        if ($header === null) {
            throw new DataSourceException('Brussels cadastre CSV is empty.');
        }

        $clubs = [];
        foreach ($rows as $row) {
            if (count($row) !== count($header)) {
                continue;
            }

            $fields = array_combine($header, $row);
            $club = $this->normalize($fields);

            if ($club !== null) {
                $clubs[] = $club;
            }
        }

        return $clubs;
    }

    private function normalize(array $fields): ?NormalizedClub
    {
        $nameFr = $this->clean($fields['name_fr'] ?? null);
        $nameNl = $this->clean($fields['name_nl'] ?? null);
        $nameEn = $this->clean($fields['name_en'] ?? null);

        $name = $nameFr ?? $nameNl ?? $nameEn;
        if ($name === null) {
            return null;
        }

        $isFrenchOnly = $nameFr !== null && $nameNl === null && $nameEn === null;
        $language = $isFrenchOnly ? 'fr' : 'nl';

        $streetFr = $this->clean($fields['Place_street_fr'] ?? null);
        $streetNl = $this->clean($fields['Plca_street_nl'] ?? null);
        $streetEn = $this->clean($fields['Place_street_en'] ?? null);
        $street = $language === 'fr'
            ? ($streetFr ?? $streetNl ?? $streetEn)
            : ($streetNl ?? $streetFr ?? $streetEn);

        $number = $this->clean($fields['Place_num'] ?? null);
        $zipcode = $this->clean($fields['Place_zipcode'] ?? null);
        $city = $this->clean($fields['Place_city'] ?? null);

        if ($street === null || $zipcode === null) {
            return null;
        }

        $address = trim($street.($number !== null ? " {$number}" : '').", {$zipcode} {$city}");
        $externalId = 'BR-CAD-'.$fields['IN_ID'];

        return NormalizedClub::fromArray([
            'externalId' => $externalId,
            'federation' => 'BR-CAD',
            'name' => $name,
            'type' => 'sports_infrastructure',
            'language' => $language,
            'postalCode' => $zipcode,
            'venues' => [[
                'address' => $address,
                'sourceLocationId' => $fields['IN_ID'],
            ]],
        ]);
    }

    private function clean(?string $value): ?string
    {
        $value = trim((string) $value);

        return ($value === '' || strtoupper($value) === 'NULL') ? null : $value;
    }
}
