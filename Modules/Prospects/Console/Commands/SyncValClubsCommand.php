<?php

namespace Modules\Prospects\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Modules\Prospects\Models\Prospect;
use Modules\Prospects\Models\ProspectLocation;
use Modules\Prospects\Models\Region;
use Symfony\Component\DomCrawler\Crawler;
use Illuminate\Support\Str;
use Modules\Prospects\Traits\LogsSyncEvents;
use Modules\Prospects\Traits\HandlesClubRegions;

class SyncValClubsCommand extends Command
{
    use LogsSyncEvents, HandlesClubRegions;

    protected $signature = 'prospects:sync-val-clubs {--limit= : Limit the number of clubs to sync} {--user= : User ID who triggered the sync} {--history= : Existing sync history record ID}';
    protected $description = 'Sync athletics clubs from Vlaamse Atletiekliga (VAL) - atletiek.be';

    public function handle(): void
    {
        $this->startSyncLog($this->option('user'), $this->option('history'));
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
            $this->logSyncEvent("Found {$count} clubs to process.", 'info', '🔍');

            $syncedCount = 0;
            foreach ($clubLinks as $link) {
                $syncedCount++;
                $this->logSyncEvent("Syncing [{$syncedCount}/{$count}]: {$link}", 'info', '🔄');
                $this->syncClub("{$baseUrl}{$link}");
            }

            $this->newLine();
            $this->info('Synchronization completed successfully.');
            $this->finishSyncLog($count);

        } catch (\Exception $e) {
            $this->error("Error during synchronization: {$e->getMessage()}");
            $this->failSyncLog($e->getMessage());
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
            $address = !empty($contactInfo['locations']) ? $contactInfo['locations'][0]['address'] : null;
            $postalCode = $address ? $this->extractPostalCode($address) : null;
            $regionId = $this->getRegionIdFromPostalCode($postalCode);

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
                            'contact_type' => 'venue_name',
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
}
