<?php

require __DIR__ . '/vendor/autoload.php';

use Illuminate\Support\Facades\Http;

$apiUrl = 'https://datalake-prod2018.rbfa.be/graphql';
$headers = [
    'Content-Type' => 'application/json',
    'Accept' => 'application/json',
];

$results = [];

// Range for 2nd and 3rd divisions
$start = 123317;
$end = 123400;

echo "Probing IDs from $start to $end...\n";

for ($id = $start; $id <= $end; $id++) {
    $seriesId = "CHP_" . $id;
    $payload = [
        "operationName" => "GetSeriesRankings",
        "variables" => [
            "seriesId" => $seriesId,
            "language" => "en"
        ],
        "extensions" => [
            "persistedQuery" => [
                "version" => 1,
                "sha256Hash" => "0a53124a9bc8872b686f22d80fd545622dbaf4b27a7596e1207b097b92c87953"
            ]
        ]
    ];

    $ch = curl_init($apiUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, array_merge($headers, ['Content-Length: ' . strlen(json_encode($payload))]));
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode === 200) {
        $data = json_decode($response, true);
        $name = $data['data']['seriesRankings']['name'] ?? 'Unknown';
        if ($name !== 'Unknown') {
            echo "Found: $seriesId -> $name\n";
            $results[$seriesId] = $name;
        }
    }
}

file_put_contents('leagues_catalog.json', json_encode($results, JSON_PRETTY_PRINT));
echo "Finished.\n";
