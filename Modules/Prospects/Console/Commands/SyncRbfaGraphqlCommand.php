<?php

namespace Modules\Prospects\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use Modules\Prospects\Models\Prospect;
use Modules\Prospects\Models\ProspectLocation;
use Modules\Prospects\Traits\LogsSyncEvents;
use Modules\Prospects\Traits\HandlesClubRegions;

class SyncRbfaGraphqlCommand extends Command
{
    use LogsSyncEvents, HandlesClubRegions;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'prospects:sync-rbfa-graphql 
                            {--province=all : Sync a specific province or all} 
                            {--user= : User ID who triggered the sync}
                            {--history= : Existing sync history record ID}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync RBFA GraphQL data for the Prospects Module (No CAFCA)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->startSyncLog($this->option('user'), $this->option('history'));
        $this->info("Starting RBFA Discovery Phase...");
        $this->logSyncEvent("Iniciando fase de descubrimiento RBFA...", 'info', '🔍');

        $provinceOption = $this->option('province');
        $provincesConfig = config('rbfa.provinces');

        if ($provinceOption !== 'all') {
            if (!isset($provincesConfig[$provinceOption])) {
                $errorMessage = "Province '{$provinceOption}' not found in configuration.";
                $this->error($errorMessage);
                $this->failSyncLog($errorMessage);
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

        $uniqueClubs = [];

        foreach ($activeProvinces as $provinceName => $leagueIds) {
            foreach ($leagueIds as $seriesId) {
                $this->info("Fetching data for series: {$seriesId} ({$provinceName})");
                sleep(1); // Respect API rate limits

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

                $response = Http::withHeaders($headers)
                    ->timeout(60)
                    ->retry(3, 5000)
                    ->post($apiUrl, $payload);

                if ($response->successful()) {
                    $data = $response->json();
                    $seriesRankings = $data['data']['seriesRankings'] ?? null;

                    if (!$seriesRankings) continue;

                    $channel = $seriesRankings['channel'] ?? null;
                    $rankings = $seriesRankings['rankings'] ?? [];

                    foreach ($rankings as $ranking) {
                        foreach ($ranking['teams'] ?? [] as $team) {
                            $clubId = $team['clubId'] ?? null;
                            if ($clubId && !isset($uniqueClubs[$clubId])) {
                                $uniqueClubs[$clubId] = [
                                    'clubId' => $clubId,
                                    'logo' => $team['logo'] ?? null,
                                    'region' => $provinceName,
                                    'channel' => $channel,
                                ];
                            }
                        }
                    }
                }
            }
        }

        $count = count($uniqueClubs);
        $this->info("Found {$count} unique clubs. Starting Enrichment...");
        $this->logSyncEvent("Encontrados {$count} clubes únicos. Iniciando enriquecimiento...", 'info', '📊');

        $processed = 0;
        foreach ($uniqueClubs as $clubId => $clubData) {
            $processed++;
            $this->info("Processing [{$processed}/{$count}]: {$clubId}");
            
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
                $clubInfo = $response->json()['data']['clubInfo'] ?? null;
                if (!$clubInfo) continue;

                DB::beginTransaction();
                try {
                    $name = $clubInfo['name'] ?? 'Unknown Club';
                    $this->logSyncEvent("Sincronizando: {$name}", 'info', '🔄');

                    $isFlanders = in_array($clubData['region'], ['Antwerpen', 'Limburg', 'Oost-Vlaanderen', 'West-Vlaanderen', 'Vlaams-Brabant']);
                    $federation = $isFlanders ? 'VL-VV' : 'FR-ACFF';
                    $prefix = $isFlanders ? 'VL-' : 'FR-';

                    $prospect = Prospect::updateOrCreate(
                        ['external_id' => $prefix . 'RBFA-' . $clubId],
                        [
                            'name' => $name,
                            'type' => 'football_club',
                            'federation' => $federation,
                            'language' => $isFlanders ? 'nl' : 'fr',
                            'logo_url' => $clubData['logo'] ?? null,
                            'website' => $clubInfo['website'] ?? null,
                            'vat_number' => $clubInfo['vatNumber'] ?? null,
                            'region_id' => $this->getRegionIdFromPostalCode($clubInfo['postalCode'] ?? null),
                        ]
                    );

                    // Map contacts
                    $emails = [];
                    $phones = [];
                    foreach ($clubInfo['contacts'] ?? [] as $contact) {
                        if (!empty($contact['mail'])) $emails = array_merge($emails, (array)$contact['mail']);
                        if (!empty($contact['phone'])) $phones = array_merge($phones, (array)$contact['phone']);
                    }

                    $emailStr = substr(implode(', ', array_unique(array_filter($emails))), 0, 250);
                    $phoneStr = substr(implode(', ', array_unique(array_filter($phones))), 0, 250);

                    // Address
                    $addrParts = array_filter([$clubInfo['streetName'] ?? null, $clubInfo['postalCode'] ?? null, $clubInfo['localityName'] ?? null]);
                    $hqAddress = implode(', ', $addrParts);

                    if ($hqAddress || $emailStr || $phoneStr) {
                        ProspectLocation::updateOrCreate(
                            ['prospect_id' => $prospect->id, 'contact_type' => 'headquarters'],
                            ['email' => $emailStr ?: null, 'phone' => $phoneStr ?: null, 'address' => $hqAddress]
                        );
                    }

                    DB::commit();
                } catch (\Exception $e) {
                    DB::rollBack();
                    $this->error("Error: " . $e->getMessage());
                }
            }
            sleep(1);
        }

        $this->info("Sync completed.");
        $this->finishSyncLog($processed);
    }
}
