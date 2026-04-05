<?php

require 'vendor/autoload.php';

use GuzzleHttp\Client;
use Symfony\Component\DomCrawler\Crawler;

$client = new Client(['verify' => false, 'cookies' => true]);

echo "--- TENNIS VLAANDEREN ---\n";
try {
    $res = $client->get('https://www.tennisenpadelvlaanderen.be/');
    $html = (string) $res->getBody();
    
    $crawler = new Crawler($html);
    $clubLinks = $crawler->filter('a')->reduce(function (Crawler $node, $i) {
        return stripos($node->text(), 'club') !== false;
    });
    
    echo "Club links on homepage: \n";
    foreach ($clubLinks as $link) {
        echo $link->getAttribute('href') . " - " . $link->nodeValue . "\n";
    }
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

echo "\n--- AFT TENNIS (MyAFT/Clubs/Search) ---\n";
try {
    $res = $client->get('https://tennis.tppwb.be/MyAFT/Clubs/Search');
    $html = (string) $res->getBody();
    
    echo substr($html, 0, 500) . "\n";
    // Check if there are select boxes for region or a table of clubs
    // Find forms
    $crawler = new Crawler($html);
    echo "Forms: " . $crawler->filter('form')->count() . "\n";
    
    if ($crawler->filter('form')->count() > 0) {
        echo "Form action: " . $crawler->filter('form')->attr('action') . "\n";
    }

} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
