<?php

namespace Modules\Prospects\Console\Commands;

use Illuminate\Console\Command;
use GuzzleHttp\Client;
use Symfony\Component\DomCrawler\Crawler;
use Modules\Prospects\Models\Prospect;
use Modules\Prospects\Models\Region;
use Illuminate\Support\Str;

class SyncTPVClubsCommand extends Command
{
    protected $signature = 'cafca:sync-tpv-clubs';
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

        $this->info("Found " . count($allClubs) . " clubs to sync details.");

        $bar = $this->output->createProgressBar(count($allClubs));

        foreach ($allClubs as $club) {
            try {
                $this->syncClubDetails($club);
                $bar->advance();
                usleep(1000000); // 1s wait
            } catch (\Exception $e) {
                $this->error("\nError syncing club {$club['name']}: " . $e->getMessage());
            }
        }

        $bar->finish();
        $this->newLine();
        $this->info('TPV Synchronization completed.');
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

        $regionId = null;
        if ($postalCode) {
            $regionId = $this->getRegionIdFromPostalCode((int)$postalCode);
        }

        $prospect = Prospect::updateOrCreate(
            [
                'external_id' => 'TPV-' . ($club['external_id'] ?? $club['internal_id']),
            ],
            [
                'name' => $club['name'],
                'type' => 'tennis_padel_club',
                'federation' => 'TPV',
                'language' => 'nl',
                'website' => $data['website'] ?? "https://www.tennisenpadelvlaanderen.be/nl/clubdashboard/over-club?clubId=" . $club['internal_id'],
                'region_id' => $regionId ?? 11,
            ]
        );

        if ($data['address']) {
            $prospect->locations()->updateOrCreate(
                ['location_type' => 'venue_name'],
                [
                    'email' => $data['email'],
                    'phone' => $data['phone'],
                    'address' => $data['address'],
                ]
            );
        }
    }

    protected function getRegionIdFromPostalCode(int $code): ?int
    {
        $regionName = match (true) {
            ($code >= 1000 && $code <= 1299) => 'Brussel',
            ($code >= 1300 && $code <= 1499) => 'Brabant Wallon',
            ($code >= 1500 && $code <= 1999) => 'Vlaams-Brabant',
            ($code >= 2000 && $code <= 2999) => 'Antwerpen',
            ($code >= 3000 && $code <= 3499) => 'Vlaams-Brabant',
            ($code >= 3500 && $code <= 3999) => 'Limburg',
            ($code >= 4000 && $code <= 4999) => 'Liège',
            ($code >= 5000 && $code <= 5999) => 'Namur',
            ($code >= 6000 && $code <= 6599) => 'Hainaut',
            ($code >= 6600 && $code <= 6999) => 'Luxembourg',
            ($code >= 7000 && $code <= 7999) => 'Hainaut',
            ($code >= 8000 && $code <= 8999) => 'West-Vlaanderen',
            ($code >= 9000 && $code <= 9999) => 'Oost-Vlaanderen',
            default => null,
        };

        if ($regionName) {
            return Region::where('name', $regionName)->first()?->id;
        }

        return null;
    }
}
