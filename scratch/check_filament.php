<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "Checking Filament V5 classes...\n";
$classes = [
    'Filament\Schemas\Schema',
    'Filament\Schemas\Components\Section',
    'Filament\Resources\Resource',
];

foreach ($classes as $class) {
    if (class_exists($class)) {
        echo "[OK] $class exists.\n";
    } else {
        echo "[FAIL] $class DOES NOT exist.\n";
    }
}
