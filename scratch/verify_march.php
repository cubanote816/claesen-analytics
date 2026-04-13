<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Modules\Cafca\Models\Employee;
use Modules\Performance\Services\EmployeePerformanceService;
use Illuminate\Support\Carbon;

$date = Carbon::parse('2026-03-01');
$ids = [170, 114, 193, 133, 103];
$service = app(EmployeePerformanceService::class);

foreach($ids as $id) {
    $employee = Employee::find($id);
    if(!$employee) continue;
    
    $stats = $service->getMonthlyStats($employee, $date);
    
    echo "--- Employee: {$employee->name} (ID: {$id}) ---\n";
    echo "Total Hours: " . number_format($stats['hours'], 2) . "\n";
    echo "Categories: " . json_encode($stats['categories']) . "\n";
    
    if(isset($stats['projects']) && count($stats['projects']) > 0) {
        echo "Top Projects (first 2):\n";
        foreach($stats['projects']->sortByDesc('total_hours')->take(2) as $p) {
            echo "  - {$p['project_name']}: " . number_format($p['total_hours'], 2) . "h " . json_encode($p['categories']) . "\n";
        }
    }
    echo "\n";
}
