<?php

namespace Modules\Prospects\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Modules\Prospects\Models\Prospect;
use Modules\Prospects\Models\ProspectLocation;
use Modules\Prospects\Models\Region;
use Symfony\Component\DomCrawler\Crawler;
use Illuminate\Support\Str;

class SyncVALClubsCommand extends Command
{
    protected $signature = 'cafca:sync-val-clubs {--limit= : Limit the number of clubs to sync}';
    protected $description = 'Sync athletics clubs from Vlaamse Atletiekliga (VAL) - atletiek.be';

    public function handle(): void
    {
        $this->info('Starting VAL athletics clubs synchronization...');

        $baseUrl = 'https://www.atletiek.be';
        $listUrl = "{$baseUrl}/organisatie/clubs";

        try {
            $response = Http::get($listUrl);
            if (!$response->successful()) {
                $this->error("Failed to fetch club list: {$response->status()}");
                return;
            }

            $crawler = new Crawler($response->body());
            $clubLinks = $crawler->filter('a[href*="/organisatie/clubs/"]')->each(function (Crawler $node) use ($baseUrl) {
                return $node->attr('href');
            });

            // Remove duplicates and main page link
            $clubLinks = array_unique($clubLinks);
            $clubLinks = array_filter($clubLinks, fn($link) => $link !== '/organisatie/clubs');

            $limit = $this->option('limit');
            if ($limit) {
                $clubLinks = array_slice($clubLinks, 0, (int) $limit);
            }

            $count = count($clubLinks);
            $this->info("Found {$count} clubs to sync.");

            $bar = $this->output->createProgressBar($count);
            $bar->start();

            foreach ($clubLinks as $link) {
                $this->syncClub("{$baseUrl}{$link}");
                $bar->advance();
            }

            $bar->finish();
            $this->newLine();
            $this->info('Synchronization completed successfully.');

        } catch (\Exception $e) {
            $this->error("Error during synchronization: {$e->getMessage()}");
        }
    }

    protected function syncClub(string $url): void
    {
        try {
            $response = Http::get($url);
            if (!$response->successful()) {
                return;
            }

            $crawler = new Crawler($response->body());
            
            // Name: Usually inside <h1> or a specific header
            $name = trim($crawler->filter('h1')->first()->text());
            $externalId = (string) Str::afterLast($url, '/');

            // Club details sections
            $contactInfo = [];
            $crawler->filter('h5:contains("Contact"), h5:contains("Secretaris"), h5:contains("Terreinen")')->each(function (Crawler $header) use (&$contactInfo) {
                $sectionName = strtolower($header->text());
                $contentNode = $header->nextAll()->first();
                
                if ($sectionName === 'contact') {
                    $contactInfo['email'] = $contentNode->filter('a[href^="mailto:"]')->count() ? $contentNode->filter('a[href^="mailto:"]')->text() : null;
                    $contactInfo['website'] = $contentNode->filter('a[href^="http"]')->count() ? $contentNode->filter('a[href^="http"]')->text() : null;
                } elseif ($sectionName === 'secretaris') {
                    $contactInfo['secretary'] = trim(str_replace('Naam:', '', $contentNode->text()));
                } elseif ($sectionName === 'terreinen') {
                    // Terreinen are often multiple nodes
                    $contactInfo['locations'] = [];
                    $header->nextAll()->each(function (Crawler $node) use (&$contactInfo) {
                        if ($node->nodeName() === 'h5') return false; // Stop at next section
                        if (Str::contains($node->text(), 'Type:')) {
                            // This looks like a location block
                            $lines = explode("\n", trim($node->text()));
                            $contactInfo['locations'][] = [
                                'name' => $lines[0] ?? 'Terrein',
                                'address' => implode(', ', array_slice($lines, 1, -1)),
                                'type_info' => trim(str_replace('Type:', '', end($lines)))
                            ];
                        }
                    });
                }
            });

            // If no email found in the "Contact" header, try a global search
            if (empty($contactInfo['email'])) {
                $contactInfo['email'] = $crawler->filter('a[href^="mailto:"]')->count() ? $crawler->filter('a[href^="mailto:"]')->first()->text() : null;
            }

            // Determine Region from the first location's postal code
            $regionId = null;
            if (!empty($contactInfo['locations'])) {
                $address = $contactInfo['locations'][0]['address'];
                $postalCode = $this->extractPostalCode($address);
                if ($postalCode) {
                    $regionId = $this->getRegionIdFromPostalCode($postalCode);
                }
            }

            // Persistence
            $prospect = Prospect::updateOrCreate(
                ['external_id' => $externalId, 'federation' => 'VAL'],
                [
                    'name' => $name,
                    'type' => 'athletics_club',
                    'federation' => 'VAL',
                    'language' => 'nl',
                    'contact_person' => $contactInfo['secretary'] ?? null,
                    'website' => $contactInfo['website'] ?? null,
                    'region_id' => $regionId,
                ]
            );

            // Locations
            // For now, let's just clear and re-add or update based on name
            if (!empty($contactInfo['locations'])) {
                foreach ($contactInfo['locations'] as $loc) {
                    ProspectLocation::updateOrCreate(
                        [
                            'prospect_id' => $prospect->id,
                            'address' => $loc['address']
                        ],
                        [
                            'location_type' => 'venue_name',
                            'email' => $contactInfo['email'] ?? null,
                            'phone' => null, // Phone is rarely on the page in a structured way
                        ]
                    );
                }
            }

        } catch (\Exception $e) {
            // Log or ignore specific club failure
        }
    }

    protected function extractPostalCode(string $address): ?string
    {
        if (preg_match('/\b[1-9][0-9]{3}\b/', $address, $matches)) {
            return $matches[0];
        }
        return null;
    }

    protected function getRegionIdFromPostalCode(string $zip): ?int
    {
        $code = (int) $zip;
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
