<?php

namespace Modules\Prospects\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Modules\Prospects\Models\Prospect;
use Modules\Prospects\Models\ProspectLocation;
use Modules\Prospects\Models\Region;
use Symfony\Component\DomCrawler\Crawler;
use Illuminate\Support\Str;

class SyncLBFAClubsCommand extends Command
{
    protected $signature = 'cafca:sync-lbfa-clubs {--limit= : Limit the number of clubs to sync}';
    protected $description = 'Sync athletics clubs from Ligue Belge Francophone d\'Athlétisme (LBFA) - lbfa.be';

    public function handle(): void
    {
        $this->info('Starting LBFA athletics clubs synchronization...');

        $baseUrl = 'https://www.lbfa.be';
        $listUrl = "{$baseUrl}/fr/liste-des-clubs";

        try {
            $response = Http::withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36'
            ])->get($listUrl);

            if (!$response->successful()) {
                $this->error("Failed to fetch club list from LBFA: " . $response->status());
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
                $address = trim(strip_tags($rightCell->filter('p')->last()->html()));
                // Address (Right Cell)
                $address = trim(strip_tags($rightCell->filter('p')->last()->html()));
                // If it contains "Consulter un plan", take the paragraph before it
                $paragraphs = $rightCell->filter('p');
                if ($paragraphs->count() >= 2) {
                    $address = trim($paragraphs->eq($paragraphs->count() - 2)->text());
                }

                $postalCode = null;
                if (preg_match('/\b[1-9][0-9]{3}\b/', $address, $zipMatches)) {
                    $postalCode = $zipMatches[0];
                }

                $regionId = null;
                if ($postalCode) {
                    $regionId = $this->getRegionIdFromPostalCode($postalCode);
                }

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

            foreach ($clubItems as $item) {
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
                    ['prospect_id' => $prospect->id, 'location_type' => 'headquarters'],
                    [
                        'email' => $item['email'],
                        'phone' => $item['phone'],
                        'address' => $item['address'],
                    ]
                );
            }

            $this->info('LBFA Synchronization completed.');

        } catch (\Exception $e) {
            $this->error("Error during LBFA synchronization: {$e->getMessage()}");
        }
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
