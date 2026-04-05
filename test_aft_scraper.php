<?php
require 'vendor/autoload.php';

use GuzzleHttp\Client;
use Symfony\Component\DomCrawler\Crawler;

$client = new Client(['verify' => false, 'cookies' => true]);

try {
    // 1. GET request to get the Search form and the CSRF token
    $res = $client->get('https://tennis.tppwb.be/MyAFT/Clubs/Search');
    $html = (string) $res->getBody();
    
    $crawler = new Crawler($html);
    $tokenNode = $crawler->filter('input[name="__RequestVerificationToken"]');
    if ($tokenNode->count() === 0) {
        die("No CSRF token found\n");
    }
    $token = $tokenNode->attr('value');
    echo "Token acquired: $token\n";
    
    // 2. Submit the search form via POST to https://tennis.tppwb.be/Recherche/Liste-de-resultats
    $postRes = $client->post('https://tennis.tppwb.be/Recherche/Liste-de-resultats', [
        'form_params' => [
            '__RequestVerificationToken' => $token,
            // the form might expect other fields like Name, Region, etc. Let's send empty to get all
            'ClubName' => '',
            'ClubNumber' => '',
            'RegionId' => '',
            'ProvinceId' => '',
        ]
    ]);
    
    $resultHtml = (string) $postRes->getBody();
    $crawler = new Crawler($resultHtml);
    echo "Result clubs found: " . $crawler->filter('.club-item, table tr, a[href*="/monclub"]')->count() . "\n";
    
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
