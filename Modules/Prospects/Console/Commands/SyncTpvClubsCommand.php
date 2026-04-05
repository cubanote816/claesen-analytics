<?php

namespace Modules\Prospects\Console\Commands;

use Illuminate\Console\Command;
use GuzzleHttp\Client;
use Symfony\Component\DomCrawler\Crawler;
use Modules\Prospects\Models\Prospect;
use Modules\Prospects\Models\Region;
use Illuminate\Support\Str;
use Modules\Prospects\Traits\LogsSyncEvents;
use Modules\Prospects\Traits\HandlesClubRegions;

class SyncTpvClubsCommand extends Command
{
    use LogsSyncEvents, HandlesClubRegions;

    protected $signature = 'prospects:sync-tpv-clubs {--user= : User ID who triggered the sync} {--history= : Existing sync history record ID}';
    protected $description = 'Synchronize Tennis & Padel clubs from Tennis en Padel Vlaanderen';

    protected $client;

    public function __construct()
    {
        parent::__construct();
        $this->client = new Client([
            'verify' => false,
            'headers' => [
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36'
            ]
        ]);
    }

    public function handle()
    {
        $this->startSyncLog($this->option('user'), $this->option('history'));
        $this->info('Starting Tennis & Padel Vlaanderen synchronization...');

        $offset = 0;
        $itemsPerPage = 100;
        $allClubs = [];

        do {
            $this->info("Fetching search results offset $offset...");
            $url = "https://www.tennisenpadelvlaanderen.be/zoek-een-club?sportId=1&itemsPerPage=$itemsPerPage&offset=$offset&sortColumn=gemeente&sortOrder=ASC";
            
            try {
                $response = $this->client->get($url);
                $html = (string) $response->getBody();
                $crawler = new Crawler($html);

                // Find club cards
                $cards = $crawler->filter('.result-card--club--info');
                
                if ($cards->count() === 0) {
                    break;
                }

                $cards->each(function (Crawler $node) use (&$allClubs) {
                    $titleNode = $node->filter('h5');
                    if ($titleNode->count()) {
                        $fullTitle = trim($titleNode->text());
                        // Extract external ID from "Name (ID)"
                        if (preg_match('/\(([^)]+)\)$/', $fullTitle, $matches)) {
                            $externalId = trim($matches[1]);
                            $name = trim(str_replace('(' . $externalId . ')', '', $fullTitle));
                        } else {
                            $externalId = null;
                            $name = $fullTitle;
                        }

                        $dashLink = $node->filter('a.tvl-cta-btn')->first();
                        if ($dashLink->count()) {
                            $href = $dashLink->attr('href');
                            if (preg_match('/clubId=([0-9]+)/', $href, $m)) {
                                $internalId = $m[1];
                                $allClubs[] = [
                                    'name' => $name,
                                    'external_id' => $externalId,
                                    'internal_id' => $internalId
                                ];
                            }
                        }
                    }
                });

                $offset += $itemsPerPage;
                usleep(500000); // 0.5s

            } catch (\Exception $e) {
                $this->error("Error fetching list: " . $e->getMessage());
                break;
            }

        } while ($offset < 1000); // Safety limit

        $count = count($allClubs);
        $this->info("Found " . $count . " clubs to sync details.");
        $this->logSyncEvent("Found {$count} clubs to sync. Processing details...", 'info', '🔍');

        //$bar = $this->output->createProgressBar(count($allClubs));
        $syncedCount = 0;
        foreach ($allClubs as $club) {
            $syncedCount++;
            $this->logSyncEvent("Syncing [{$syncedCount}/{$count}]: {$club['name']}", 'info', '🔄');
            try {
                $this->syncClubDetails($club);
                //$bar->advance();
                usleep(1000000); // 1s wait
            } catch (\Exception $e) {
                $this->error("\nError syncing club {$club['name']}: " . $e->getMessage());
                $this->logSyncEvent("Error syncing {$club['name']}: {$e->getMessage()}", 'error', '⚠️');
            }
        }

        //$bar->finish();
        $this->newLine();
        $this->info('TPV Synchronization completed.');
        $this->finishSyncLog($count);
    }

    protected function syncClubDetails($club)
    {
        $url = "https://www.tennisenpadelvlaanderen.be/nl/clubdashboard/over-club?clubId=" . $club['internal_id'];
        $response = $this->client->get($url);
        $html = (string) $response->getBody();
        $crawler = new Crawler($html);

        $data = [
            'address' => '',
            'email' => null,
            'phone' => null,
            'website' => null,
            'members' => null,
        ];

        // Extract list items (Address, Email, Phone, Website, Aantal Leden)
        $crawler->filter('li.clearfix')->each(function (Crawler $node) use (&$data) {
            $label = trim($node->filter('.list-label')->text());
            $valueNode = $node->filter('.list-value');
            
            if ($valueNode->count()) {
                $value = trim($valueNode->text());
                match ($label) {
                    'Adres (hoofdlocatie)' => $data['address'] = $value,
                    'Email' => $data['email'] = $valueNode->filter('a')->count() ? $valueNode->filter('a')->text() : $value,
                    'Telefoonnummer' => $data['phone'] = $value,
                    'Website' => $data['website'] = $valueNode->filter('a')->count() ? $valueNode->filter('a')->attr('href') : $value,
                    'Aantal leden' => $data['members'] = $value,
                    default => null,
                };
            }
        });

        // Mapping to DB
        $postalCode = null;
        if (preg_match('/\b[1-9][0-9]{3}\b/', $data['address'], $matches)) {
            $postalCode = $matches[0];
        }

        $regionId = $this->getRegionIdFromPostalCode($postalCode);

        $prospect = Prospect::updateOrCreate(
            [
                'external_id' => 'VL-TPV-' . ($club['external_id'] ?? $club['internal_id']),
            ],
            [
                'name' => $club['name'],
                'type' => 'tennis_padel_club',
                'federation' => 'VL-TPV',
                'language' => 'nl',
                'website' => $data['website'] ?? "https://www.tennisenpadelvlaanderen.be/nl/clubdashboard/over-club?clubId=" . $club['internal_id'],
                'region_id' => $regionId,
            ]
        );

        if ($data['address']) {
            $prospect->locations()->updateOrCreate(
                ['contact_type' => 'venue_name'],
                [
                    'email' => $data['email'],
                    'phone' => $data['phone'],
                    'address' => $data['address'],
                ]
            );
        }
    }
}
