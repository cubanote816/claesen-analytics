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

class SyncLbfaClubsCommand extends Command
{
    use LogsSyncEvents, HandlesClubRegions;

    protected $signature = 'prospects:sync-lbfa-clubs {--limit= : Limit the number of clubs to sync} {--user= : User ID who triggered the sync} {--history= : Existing sync history record ID}';
    protected $description = 'Sync athletics clubs from Ligue Belge Francophone d\'Athlétisme (LBFA) - lbfa.be';

    public function handle(): void
    {
        $this->startSyncLog($this->option('user'), $this->option('history'));
        $this->info('Starting LBFA athletics clubs synchronization...');

        $baseUrl = 'https://www.lbfa.be';
        $listUrl = "{$baseUrl}/fr/liste-des-clubs";

        try {
            $response = Http::withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36'
            ])->get($listUrl);

            if (!$response->successful()) {
                $errorMessage = "Failed to fetch club list from LBFA: " . $response->status();
                $this->error($errorMessage);
                $this->failSyncLog($errorMessage);
                return;
            }

            $crawler = new Crawler($response->body());
            
            // LBFA uses tables for clubs. Each club is usually in a <tr> with two <td>s.
            $clubItems = $crawler->filter('table tr')->each(function (Crawler $row) {
                $cells = $row->filter('td');
                if ($cells->count() < 2) return null;

                $leftCell = $cells->first();
                $rightCell = $cells->last();

                // Name is inside <strong>
                $nameNode = $leftCell->filter('strong');
                if (!$nameNode->count()) return null;
                $name = trim($nameNode->first()->text());

                // Secretary
                $secretary = null;
                $leftHtml = $leftCell->html();
                if (preg_match('/Secr&eacute;taire\s*:\s*([^<]+)/i', $leftHtml, $matches)) {
                    $secretary = trim(html_entity_decode($matches[1]));
                }

                // Email
                $emailLink = $leftCell->filter('a[href^="mailto:"]');
                $email = $emailLink->count() ? str_replace('mailto:', '', $emailLink->attr('href')) : null;

                // Website
                $websiteLink = $leftCell->filter('a')->reduce(function (Crawler $node) {
                    return Str::contains(Str::lower($node->text()), 'site internet');
                });
                $website = $websiteLink->count() ? $websiteLink->attr('href') : null;

                // Phone
                $phone = null;
                if (preg_match('/T&eacute;l\.\s*:\s*([^<]+)/i', $leftHtml, $matches)) {
                    $phone = trim(html_entity_decode($matches[1]));
                }

                // Address (Right Cell)
                $paragraphs = $rightCell->filter('p');
                if ($paragraphs->count() >= 2) {
                    $address = trim($paragraphs->eq($paragraphs->count() - 2)->text());
                }

                $postalCode = null;
                if (preg_match('/\b[1-9][0-9]{3}\b/', $address, $zipMatches)) {
                    $postalCode = $zipMatches[0];
                }

                $regionId = $this->getRegionIdFromPostalCode($postalCode);

                return [
                    'name' => $name,
                    'contact_person' => $secretary,
                    'email' => $email,
                    'website' => $website,
                    'phone' => $phone,
                    'address' => $address,
                    'region_id' => $regionId,
                    'external_id' => 'LBFA-' . Str::slug($name),
                ];
            });

            $clubItems = array_filter($clubItems);
            
            $limit = $this->option('limit');
            if ($limit) {
                $clubItems = array_slice($clubItems, 0, (int) $limit);
            }

            $count = count($clubItems);
            $this->info("Found {$count} clubs to sync.");
            $this->logSyncEvent("Found {$count} clubs to sync. Processing...", 'info', '🔍');

            $syncedCount = 0;
            foreach ($clubItems as $item) {
                $syncedCount++;
                $this->logSyncEvent("Syncing [{$syncedCount}/{$count}]: {$item['name']}", 'info', '🔄');
                
                $prospect = Prospect::updateOrCreate(
                    ['external_id' => $item['external_id'], 'federation' => 'LBFA'],
                    [
                        'name' => $item['name'],
                        'type' => 'athletics_club',
                        'contact_person' => $item['contact_person'],
                        'language' => 'fr',
                        'website' => $item['website'],
                        'region_id' => $item['region_id'],
                    ]
                );

                ProspectLocation::updateOrCreate(
                    ['prospect_id' => $prospect->id, 'contact_type' => 'headquarters'],
                    [
                        'email' => $item['email'],
                        'phone' => $item['phone'],
                        'address' => $item['address'],
                    ]
                );
            }

            $this->info('LBFA Synchronization completed.');
            $this->finishSyncLog($count);

        } catch (\Exception $e) {
            $this->error("Error during LBFA synchronization: {$e->getMessage()}");
            $this->failSyncLog($e->getMessage());
        }
    }
}
