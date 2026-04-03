<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use Modules\Prospects\Models\Prospect;
use Modules\Prospects\Models\ProspectLocation;

class SyncRbfaGraphqlCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cafca:sync-rbfa-graphql {--province=all : Sync a specific province or all}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync RBFA GraphQL data for the CAFCA Marketing Module';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $provinceOption = $this->option('province');
        $provincesConfig = config('rbfa.provinces');

        if ($provinceOption !== 'all') {
            if (!isset($provincesConfig[$provinceOption])) {
                $this->error("Province '{$provinceOption}' not found in configuration.");
                return 1;
            }
            $activeProvinces = [$provinceOption => $provincesConfig[$provinceOption]];
        } else {
            $activeProvinces = $provincesConfig;
        }

        $apiUrl = 'https://datalake-prod2018.rbfa.be/graphql';
        $headers = [
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ];

        // Step 2A: DISCOVERY PHASE
        $this->info("Starting Discovery Phase...");
        $uniqueClubs = [];

        foreach ($activeProvinces as $provinceName => $leagueIds) {
            foreach ($leagueIds as $seriesId) {
                $this->info("Fetching data for series: {$seriesId} ({$provinceName})");
                sleep(2); // CRITICAL: respect API rate limits

            $payload = [
                "operationName" => "GetSeriesRankings",
                "variables" => [
                    "seriesId" => $seriesId,
                    "language" => "en"
                ],
                "extensions" => [
                    "persistedQuery" => [
                        "version" => 1,
                        "sha256Hash" => "0a53124a9bc8872b686f22d80fd545622dbaf4b27a7596e1207b097b92c87953"
                    ]
                ]
            ];

            $response = Http::withHeaders($headers)->post($apiUrl, $payload);

            if ($response->successful()) {
                $data = $response->json();
                $seriesRankings = $data['data']['seriesRankings'] ?? null;

                if (!$seriesRankings) {
                    continue;
                }

                $leagueName = $seriesRankings['name'] ?? null;
                $channel = $seriesRankings['channel'] ?? null;
                $rankings = $seriesRankings['rankings'] ?? [];

                // 1. Set region directly from the province name in the configuration
                $region = $provinceName;

                foreach ($rankings as $ranking) {
                    $teams = $ranking['teams'] ?? [];
                    foreach ($teams as $team) {
                        $clubId = $team['clubId'] ?? null;
                        $logo = $team['logo'] ?? null;

                        if ($clubId && !isset($uniqueClubs[$clubId])) {
                            $uniqueClubs[$clubId] = [
                                'clubId' => $clubId,
                                'logo' => $logo,
                                'region' => $region,
                                'channel' => $channel,
                            ];
                        }
                    }
                }
            } else {
                $this->error("Failed to fetch series {$seriesId}");
            }
            }
        }

        $this->info("Found " . count($uniqueClubs) . " unique clubs.");

        // Step 2B: ENRICHMENT PHASE
        $this->info("Starting Enrichment Phase...");

        foreach ($uniqueClubs as $clubId => $clubData) {
            $this->info("Fetching details for club: {$clubId}");
            sleep(2); // CRITICAL: respect API rate limits

            $payload = [
                "operationName" => "getClubInfo",
                "variables" => [
                    "clubId" => $clubId,
                    "language" => "en"
                ],
                "extensions" => [
                    "persistedQuery" => [
                        "version" => 1,
                        "sha256Hash" => "7c1bd99f0001a20d60208c60d4fb7c99aefdb810b9ee1c4de21a6d6ba4804b58"
                    ]
                ]
            ];

            $response = Http::withHeaders($headers)->post($apiUrl, $payload);

            if ($response->successful()) {
                $data = $response->json();
                $clubInfo = $data['data']['clubInfo'] ?? null;

                if (!$clubInfo) continue;

                // STRICTLY IGNORE kits (done by not accessing it)

                DB::beginTransaction();

                try {
                    $name = $clubInfo['name'] ?? 'Unknown Club';
                    $vatNumber = $clubInfo['vatNumber'] ?? null;
                    $website = $clubInfo['website'] ?? null;
                    $logoUrl = $clubData['logo'] ?? null;

                    $region = $clubData['region'] ?? null;
                    $channel = $clubData['channel'] ?? null;

                    // 1. UpdateOrCreate Prospect
                    $prospect = Prospect::updateOrCreate(
                        ['name' => $name], // Use 'name' to identify the prospect uniquely.
                        [
                            'type' => 'football_club',
                            'region' => $region,
                            'channel' => $channel,
                            'logo_url' => $logoUrl,
                            'website' => $website,
                            'vat_number' => $vatNumber,
                        ]
                    );

                    // 2. Map contacts array
                    $contacts = $clubInfo['contacts'] ?? [];
                    $emails = [];
                    $phones = [];

                    foreach ($contacts as $contact) {
                        if (!empty($contact['mail']) && is_array($contact['mail'])) {
                            foreach ($contact['mail'] as $email) {
                                if ($email) $emails[] = $email;
                            }
                        }
                        if (!empty($contact['phone']) && is_array($contact['phone'])) {
                            foreach ($contact['phone'] as $phone) {
                                if ($phone) $phones[] = $phone;
                            }
                        }
                    }

                    $emailStr = substr(implode(', ', array_unique($emails)), 0, 250);
                    $phoneStr = substr(implode(', ', array_unique($phones)), 0, 250);

                    // 3. UpdateOrCreate ProspectLocation (Headquarters)
                    $hqAddressParts = [];
                    if (!empty($clubInfo['streetName'])) $hqAddressParts[] = trim($clubInfo['streetName']);
                    if (!empty($clubInfo['postalCode'])) $hqAddressParts[] = trim($clubInfo['postalCode']);
                    if (!empty($clubInfo['localityName'])) $hqAddressParts[] = trim($clubInfo['localityName']);

                    $hqAddress = !empty($hqAddressParts) ? implode(', ', $hqAddressParts) : null;

                    if ($hqAddress || $emailStr || $phoneStr) {
                        ProspectLocation::updateOrCreate(
                            [
                                'prospect_id' => $prospect->id,
                                'location_type' => 'headquarters',
                            ],
                            [
                                'email' => $emailStr ?: null,
                                'phone' => $phoneStr ?: null,
                                'address' => $hqAddress,
                            ]
                        );
                    }

                    // 4. UpdateOrCreate ProspectLocation (Stadium)
                    $venue = $clubInfo['venue'] ?? null;
                    if ($venue) {
                        $stadiumAddressParts = [];
                        if (!empty($venue['name'])) $stadiumAddressParts[] = trim($venue['name']);
                        if (!empty($venue['streetName'])) $stadiumAddressParts[] = trim($venue['streetName']);
                        if (!empty($venue['postalCode'])) $stadiumAddressParts[] = trim($venue['postalCode']);
                        if (!empty($venue['localityName'])) $stadiumAddressParts[] = trim($venue['localityName']);

                        $stadiumAddress = !empty($stadiumAddressParts) ? implode(', ', $stadiumAddressParts) : null;

                        if ($stadiumAddress) {
                            ProspectLocation::updateOrCreate(
                                [
                                    'prospect_id' => $prospect->id,
                                    'location_type' => 'stadium',
                                ],
                                [
                                    'address' => $stadiumAddress,
                                    'email' => null,
                                    'phone' => null,
                                ]
                            );
                        }
                    }

                    DB::commit();
                } catch (\Exception $e) {
                    DB::rollBack();
                    $this->error("Failed to process club {$clubId}: " . $e->getMessage());
                }
            } else {
                $this->error("Failed to fetch details for club {$clubId}");
            }
        }

        $this->info("Sync completed successfully.");
    }

    /**
     * Detect Belgian province from league name.
     */
    private function detectRegion(?string $leagueName): ?string
    {
        if (!$leagueName) return null;

        $mapping = config('rbfa.regions', []);

        foreach ($mapping as $province => $keywords) {
            foreach ($keywords as $keyword) {
                if (stripos($leagueName, $keyword) !== false) {
                    return $province;
                }
            }
        }

        return null;
    }
}
