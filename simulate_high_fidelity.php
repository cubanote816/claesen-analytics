use Modules\Intelligence\Services\BudgetAssistantService;

$service = app(BudgetAssistantService::class);
$description = "Grootschalige installatie voor sportpark 2930 Brasschaat. We hebben 6 masten van 15m nodig met LED-verlichting, 400 meter ondergrondse bekabeling, 2 slimme IoT-bedieningskasten en funderingsankers. Trek leringen uit Dossier Sportinfrabouw.";
$category = "Sportverlichting";
$zipcode = "2930";
$complexity = 1;

echo "--- STARTING HIGH-FIDELITY SIMULATION ---\n";
$results = $service->simulate($description, $category, $zipcode, $complexity);

echo "\n--- PROJECTED FINANCE ---\n";
print_r($results['breakdown']);

echo "\n--- MATERIAL BREAKDOWN (3-TIER) ---\n";
foreach($results['suggested_materials'] as $m) {
    echo sprintf("[%s] %-40s | Qty: %-3s %-3s | Unit: €%-7.2f | Total: €%-8.2f | Source: %s\n", 
        $m['ref'], 
        substr($m['description'], 0, 40), 
        $m['quantity'], 
        $m['unit'], 
        $m['price'], 
        $m['total'],
        $m['source']
    );
}

$calculatedTotal = collect($results['suggested_materials'])->sum('total');
echo "\nSUM OF MATERIALS: €" . number_format($calculatedTotal, 2) . "\n";
echo "--- END ---\n";
