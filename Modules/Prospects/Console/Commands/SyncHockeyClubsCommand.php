<?php

namespace Modules\Prospects\Console\Commands;

use Illuminate\Console\Command;
use Modules\Prospects\Models\Prospect;
use Modules\Prospects\Models\Region;
use Illuminate\Support\Str;
use Symfony\Component\DomCrawler\Crawler;
use GuzzleHttp\Client;

class SyncHockeyClubsCommand extends Command
{
    protected $signature = 'cafca:sync-hockey-clubs';
    protected $description = 'Synchronize Hockey clubs from hockey.be with full details';

    protected $clubsData = [
        ["Aalst", "CC6VG4O", "Vlaamse Hockey Liga"],
        ["Ambistix", "CF9QR9N", "Vlaamse Hockey Liga"],
        ["Amicale Anderlecht", "CC6TZ1Y", "Ligue Francophone de Hockey"],
        ["Andenne", "CF4BZ4Y", "Ligue Francophone de Hockey"],
        ["Antwerp", "CC6VH4R", "Vlaamse Hockey Liga"],
        ["Arcus", "CF4BT9L", "Vlaamse Hockey Liga"],
        ["Argos", "CC6VD0L", "Ligue Francophone de Hockey"],
        ["Arlon", "CC6VF0R", "Ligue Francophone de Hockey"],
        ["Artemis", "CC6VJ6Z", "Vlaamse Hockey Liga"],
        ["Ascalon", "CC6VD5G", "Ligue Francophone de Hockey"],
        ["BUHC", "CC6VB9E", "Ligue Francophone de Hockey"],
        ["Baudouin", "CC6VJ94", "Vlaamse Hockey Liga"],
        ["Bayard", "CC6VF3K", "Ligue Francophone de Hockey"],
        ["Beerschot", "CC6VH5S", "Vlaamse Hockey Liga"],
        ["Beveren", "CC6VH2P", "Vlaamse Hockey Liga"],
        ["Black Bears", "CC6VL23", "Vlaamse Hockey Liga"],
        ["Blackbirds", "CC6VJ5Y", "Vlaamse Hockey Liga"],
        ["Blue Lions", "CC6VK53", "Vlaamse Hockey Liga"],
        ["Blue Sox", "CC6VK75", "Vlaamse Hockey Liga"],
        ["Braxgata", "CC6VJ3W", "Vlaamse Hockey Liga"],
        ["Breeven", "CG2SB0G", "Vlaamse Hockey Liga"],
        ["Brugge", "CC6VF4L", "Vlaamse Hockey Liga"],
        ["Chessy", "CC6VF2J", "Ligue Francophone de Hockey"],
        ["Ciney", "CG2NF2T", "Ligue Francophone de Hockey"],
        ["Constantia", "CC6VG6Q", "Vlaamse Hockey Liga"],
        ["Daring", "CC6TZ2Z", "Ligue Francophone de Hockey"],
        ["Deers Team", "CF9XX65", "Ligue Francophone de Hockey"],
        ["Dender", "CC6VH0X", "Vlaamse Hockey Liga"],
        ["Dendermonde", "CC6VH3Q", "Vlaamse Hockey Liga"],
        ["Diksmuide", "CF3YY54", "Vlaamse Hockey Liga"],
        ["Dragons", "CC6VH6T", "Vlaamse Hockey Liga"],
        ["Eclair", "CC6VF5M", "Vlaamse Hockey Liga"],
        ["Embourg", "CC6VD9K", "Ligue Francophone de Hockey"],
        ["Gantoise", "CC6VF6N", "Vlaamse Hockey Liga"],
        ["Genk", "CC6VL34", "Vlaamse Hockey Liga"],
        ["Green Devils", "CC6VK08", "Vlaamse Hockey Liga"],
        ["Grez", "CG0RN2R", "Ligue Francophone de Hockey"],
        ["Growing by Hockey", "CF8JM2R", "Ligue Francophone de Hockey"],
        ["Hannut", "CC6VD1C", "Ligue Francophone de Hockey"],
        ["Hawks", "CG4ZR67", "Ligue Francophone de Hockey"],
        ["Herakles", "CC6VH8V", "Vlaamse Hockey Liga"],
        ["Hermes", "CC6VF9Q", "Vlaamse Hockey Liga"],
        ["Hockey Belgium", "CG4TQ46", "ARBH-KBHB"],
        ["Hoegaarden", "CC6VK3Z", "Vlaamse Hockey Liga"],
        ["Hoekies", "CF5PJ3S", "Vlaamse Hockey Liga"],
        ["Huy", "CC6VD2D", "Ligue Francophone de Hockey"],
        ["Indiana", "CC6VG1L", "Vlaamse Hockey Liga"],
        ["Isca", "CC6VK2Y", "Vlaamse Hockey Liga"],
        ["Ixelles", "CC6VC0I", "Ligue Francophone de Hockey"],
        ["Jaguar", "CC6VC6E", "Ligue Francophone de Hockey"],
        ["Keerbergen", "CC6VK42", "Vlaamse Hockey Liga"],
        ["Knokke", "CC6VF7O", "Vlaamse Hockey Liga"],
        ["Koksijde", "CC6VG8S", "Vlaamse Hockey Liga"],
        ["LARA", "CC6VC5D", "Ligue Francophone de Hockey"],
        ["La Louvière", "CC6VD4F", "Ligue Francophone de Hockey"],
        ["Langeveld", "CC6VB7C", "Ligue Francophone de Hockey"],
        ["Leopard", "CC6VG2M", "Vlaamse Hockey Liga"],
        ["Leopold", "CC6TZ43", "Ligue Francophone de Hockey"],
        ["Leuven", "CC6VK1X", "Vlaamse Hockey Liga"],
        ["Linkebeek", "CC6TZ54", "Ligue Francophone de Hockey"],
        ["Lokeren", "CC6VH1O", "Vlaamse Hockey Liga"],
        ["Lothier", "CG4RG62", "Ligue Francophone de Hockey"],
        ["Louvain-La-Neuve", "CC6VC2A", "Ligue Francophone de Hockey"],
        ["Luxembourg", "CD7MX6G", "Ligue Francophone de Hockey"],
        ["Lynx", "CC6VC7F", "Ligue Francophone de Hockey"],
        ["Maasmechelen", "CC6VL0B", "Vlaamse Hockey Liga"],
        ["Marche", "CC6VF1I", "Ligue Francophone de Hockey"],
        ["Mechelse", "CC6VJ05", "Vlaamse Hockey Liga"],
        ["Meetjesland", "CC6VG7R", "Vlaamse Hockey Liga"],
        ["Merode", "CC6VK64", "Vlaamse Hockey Liga"],
        ["Mol", "CC6VH9W", "Vlaamse Hockey Liga"],
        ["Namur", "CC6VD3E", "Ligue Francophone de Hockey"],
        ["Neupré", "CF6RH35", "Ligue Francophone de Hockey"],
        ["Noorderkempen", "CC6VJ72", "Vlaamse Hockey Liga"],
        ["Old Club", "CC6VD8J", "Ligue Francophone de Hockey"],
        ["Olympia", "CC6VJ83", "Vlaamse Hockey Liga"],
        ["Ombrage", "CC6TZ65", "Ligue Francophone de Hockey"],
        ["Onhaye", "CF4DZ39", "Ligue Francophone de Hockey"],
        ["Oree", "CC6TZ76", "Ligue Francophone de Hockey"],
        ["Parc", "CC6TZ87", "Ligue Francophone de Hockey"],
        ["Phoenix", "CC6VK86", "Vlaamse Hockey Liga"],
        ["Pingouin", "CC6VC4C", "Ligue Francophone de Hockey"],
        ["Polo", "CC6VC9H", "Ligue Francophone de Hockey"],
        ["Primerose", "CC6TZ98", "Ligue Francophone de Hockey"],
        ["Racing", "CC6VB0F", "Ligue Francophone de Hockey"],
        ["Rapid", "CC6VF8P", "Vlaamse Hockey Liga"],
        ["Rapide Hulst", "CF4MF9Y", "ARBH-KBHB"],
        ["Rasante", "CC6VB16", "Ligue Francophone de Hockey"],
        ["Red Wings", "CF7SH3H", "Vlaamse Hockey Liga"],
        ["Relax", "CF0BW3W", "Ligue Francophone de Hockey"],
        ["Rix", "CC6VC8G", "Ligue Francophone de Hockey"],
        ["Rodeland", "CG2RZ5A", "Vlaamse Hockey Liga"],
        ["Roeselare", "CC6VG5P", "Vlaamse Hockey Liga"],
        ["Rotselaar", "CF1WF4O", "Vlaamse Hockey Liga"],
        ["Saint-Georges", "CC6VG0U", "Vlaamse Hockey Liga"],
        ["Sapinière", "CC6VB8D", "Ligue Francophone de Hockey"],
        ["Sint-Truiden", "CC6VL12", "Vlaamse Hockey Liga"],
        ["Stix", "CC6VK97", "Vlaamse Hockey Liga"],
        ["Sukkelweg", "CC6VB27", "Ligue Francophone de Hockey"],
        ["Taxandria", "CC6VJ1U", "Vlaamse Hockey Liga"],
        ["Testvereniging 1", "CD7MX2C", "Vlaamse Hockey Liga"],
        ["Testvereniging 2", "CD7MX4E", "Ligue Francophone de Hockey"],
        ["Tournai", "CC6VD6H", "Ligue Francophone de Hockey"],
        ["Uccle Sport", "CC6VB38", "Ligue Francophone de Hockey"],
        ["Urban Hockey", "CG0CN8Q", "Vlaamse Hockey Liga"],
        ["Verviers", "CC6VD7I", "Ligue Francophone de Hockey"],
        ["Victory", "CC6VJ2V", "Vlaamse Hockey Liga"],
        ["Vivier d'Oie", "CC6VB49", "Ligue Francophone de Hockey"],
        ["Vrijbroek", "CC6VH7U", "Vlaamse Hockey Liga"],
        ["Waterloo Ducks", "CC6VC3B", "Ligue Francophone de Hockey"],
        ["Wellington", "CC6VB5A", "Ligue Francophone de Hockey"],
        ["Wetthra Giants", "CF9QQ3E", "Vlaamse Hockey Liga"],
        ["White Star", "CC6TZ32", "Ligue Francophone de Hockey"],
        ["Wildcats", "CC6VG9T", "Vlaamse Hockey Liga"],
        ["Woluwe", "CF6SG0E", "Ligue Francophone de Hockey"],
        ["Wolvendael", "CC6VC19", "Ligue Francophone de Hockey"],
        ["Yellow Sticks", "CF1WF9T", "Vlaamse Hockey Liga"],
        ["Zaid", "CC6VB6B", "Ligue Francophone de Hockey"],
    ];

    public function handle()
    {
        $this->info('Starting Hockey clubs enhancement sync...');
        
        $client = new Client([
            'verify' => false,
            'headers' => [
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36'
            ]
        ]);

        $bar = $this->output->createProgressBar(count($this->clubsData));

        foreach ($this->clubsData as $club) {
            try {
                $name = $club[0];
                $externalId = $club[1];
                $federationRaw = $club[2];
                
                $federation = match ($federationRaw) {
                    'Vlaamse Hockey Liga' => 'VHL',
                    'Ligue Francophone de Hockey' => 'LFH',
                    default => 'ARBH-KBHB',
                };

                $language = ($federation === 'LFH') ? 'fr' : 'nl';
                
                // Fetch Detailed Page (nl-club has more info and logo)
                $detailUrl = "https://hockey.be/nl/nl-club/?id=" . $externalId;
                $response = $client->get($detailUrl);
                $html = (string) $response->getBody();
                $crawler = new Crawler($html);

                // Scraped Fields
                $scraped = [
                    'logo' => null,
                    'address' => null,
                    'postcode' => null,
                    'stad' => null,
                    'phone' => null,
                    'email' => null,
                    'website' => null,
                ];

                // 1. Extract Logo (Club Crest)
                try {
                    $allImages = $crawler->filter('img')->reduce(function (Crawler $node) {
                        $src = $node->attr('src') ?? '';
                        $lower = strtolower($src);
                        $blacklist = [
                            'newsinsider', 'hockey-belgium', 'cropped-icon', 'google', 'facebook', 
                            'instagram', 'twitter', 'banner', 'footer', 'header', 'placeholder', 
                            'logo-hockey-be', '.svg', 'alt='
                        ];
                        foreach ($blacklist as $term) {
                            if (str_contains($lower, $term)) return false;
                        }
                        return str_contains($src, 'wp-content/uploads');
                    });

                    if ($allImages->count()) {
                        $scraped['logo'] = $allImages->first()->attr('src');
                    }
                } catch (\Exception $e) {
                    // Silently fail logo if not found
                }

                // 2. Extract Informatie Table
                $crawler->filter('table.sl-table tr')->each(function (Crawler $node) use (&$scraped) {
                    $labelNode = $node->filter('td')->first();
                    $valueNode = $node->filter('td')->last();
                    
                    if ($labelNode->count() && $valueNode->count()) {
                        $label = trim($labelNode->text());
                        $value = trim($valueNode->text());
                        
                        match ($label) {
                            'Adres' => $scraped['address'] = $value,
                            'Postcode' => $scraped['postcode'] = $value,
                            'Stad' => $scraped['stad'] = $value,
                            'Phone' => $scraped['phone'] = $value,
                            'E-mail' => $scraped['email'] = $valueNode->filter('a')->count() ? $valueNode->filter('a')->text() : $value,
                            'Website' => $scraped['website'] = $valueNode->filter('a')->count() ? $valueNode->filter('a')->attr('href') : $value,
                            default => null,
                        };
                    }
                });

                // Combine Address
                $fullAddress = trim(($scraped['address'] ?? '') . ' ' . ($scraped['postcode'] ?? '') . ' ' . ($scraped['stad'] ?? ''));
                
                // Extract Postal Code for Region Mapping
                $postalCode = $scraped['postcode'] ?? null;
                if (!$postalCode && preg_match('/\b[1-9][0-9]{3}\b/', $fullAddress, $matches)) {
                    $postalCode = $matches[0];
                }

                $regionId = null;
                if ($postalCode) {
                    $regionId = $this->getRegionIdFromPostalCode((int)$postalCode);
                }

                // Update or Create Prospect
                $prospect = Prospect::updateOrCreate(
                    ['external_id' => 'HOCKEY-' . $externalId],
                    [
                        'name' => $name,
                        'type' => 'hockey_club',
                        'federation' => $federation,
                        'language' => $language,
                        'logo_url' => $scraped['logo'],
                        'website' => $scraped['website'] ?? "https://hockey.be/nl/club-details/?id=" . $externalId,
                        'region_id' => $regionId ?? 11,
                    ]
                );

                // Update Location Details
                if ($fullAddress || $scraped['email'] || $scraped['phone']) {
                    $prospect->locations()->updateOrCreate(
                        ['location_type' => 'venue_name'],
                        [
                            'address' => $fullAddress ?: $prospect->locations()->first()?->address,
                            'email' => $scraped['email'],
                            'phone' => $scraped['phone'],
                        ]
                    );
                }

                usleep(700000); // 0.7s sleep

            } catch (\Exception $e) {
                $this->error("\nError syncing club " . ($club[0] ?? 'Unknown') . ": " . $e->getMessage());
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info('Hockey enhancement synchronization completed.');
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
