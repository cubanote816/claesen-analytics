<?php

require 'vendor/autoload.php';

use GuzzleHttp\Client;
use Symfony\Component\DomCrawler\Crawler;

$client = new Client([
    'verify' => false,
    'headers' => [
        'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
    ]
]);

echo "--- AFT TENNIS ---\n";
try {
    $res = $client->post('https://tennis.tppwb.be/Recherche/Liste-de-resultats', [
        'form_params' => [
            // Leave empty to get all or try basic fields
        ]
    ]);
    $html = (string) $res->getBody();
    file_put_contents('aft_results.html', $html);
    
    $crawler = new Crawler($html);
    echo "Results found? " . $crawler->filter('.club-list, table')->count() . "\n";
    $links = $crawler->filter('a')->reduce(function($n){ return stripos($n->attr('href'), '/monclub') !== false; });
    echo "Club links found: " . $links->count() . "\n";
    if ($links->count() > 0) {
        echo "Example: " . $links->first()->attr('href') . "\n";
    }

} catch (\Exception $e) {
    echo "AFT Error: " . $e->getMessage() . "\n";
}

echo "\n--- TENNIS VLAANDEREN ---\n";
try {
    // There is an API known for this portal
    // Try sending a request to a generic search or looking at https://www.tennisenpadelvlaanderen.be/contact
    $res = $client->get('https://www.tennisenpadelvlaanderen.be/contact');
    echo "Contact page loaded (length: " . strlen((string)$res->getBody()) . ")\n";
    file_put_contents('tv_contact.html', (string)$res->getBody());

} catch (\Exception $e) {
    echo "TV Error: " . $e->getMessage() . "\n";
}
