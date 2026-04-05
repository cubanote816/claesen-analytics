<?php

require 'vendor/autoload.php';

use GuzzleHttp\Client;
use Symfony\Component\DomCrawler\Crawler;

$client = new Client(['verify' => false]);

echo "--- TENNIS VLAANDEREN ---\n";
try {
    $res = $client->get('https://www.tennisenpadelvlaanderen.be/club-zoeken');
    $html = (string) $res->getBody();
    
    // Look for API endpoints in the HTML
    preg_match_all('/https?:\/\/[a-zA-Z0-9\-\.]+\/api\/[a-zA-Z0-9\-\.\/]+/', $html, $matches);
    echo "Endpoints found in HTML: \n";
    print_r(array_unique($matches[0]));
    
    // Sometimes it's stored in a JSON object in a script tag like window.__INITIAL_STATE__
    preg_match('/window\.__INITIAL_STATE__\s*=\s*({.*?});/s', $html, $jsonMatch);
    if (!empty($jsonMatch[1])) {
        echo "Found INITIAL STATE\n";
    } else {
        echo "No INITIAL STATE found\n";
    }

} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

echo "--- AFT TENNIS ---\n";
try {
    $res = $client->get('https://tennis.tppwb.be/');
    $html = (string) $res->getBody();
    
    // Check if there's a link to club search
    $crawler = new Crawler($html);
    $clubLinks = $crawler->filter('a')->reduce(function (Crawler $node, $i) {
        return stripos($node->text(), 'club') !== false;
    });
    
    echo "Club links: \n";
    foreach ($clubLinks as $link) {
        echo $link->getAttribute('href') . " - " . $link->nodeValue . "\n";
    }

} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
