<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$components = [
    'Filament\Schemas\Components\Section',
    'Filament\Schemas\Components\TextInput',
    'Filament\Schemas\Components\TextEntry',
    'Filament\Schemas\Components\RepeatableEntry',
    'Filament\Schemas\Components\ImageEntry',
    'Filament\Schemas\Components\Repeater',
];

foreach ($components as $component) {
    if (class_exists($component)) {
        echo "[OK] $component exists.\n";
    } else {
        echo "[FAIL] $component DOES NOT exist.\n";
    }
}
