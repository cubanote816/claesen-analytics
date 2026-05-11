<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$class = 'Filament\Schemas\Components\Section';
if (class_exists($class)) {
    $reflector = new ReflectionClass($class);
    echo "Class $class found in: " . $reflector->getFileName() . "\n";
} else {
    echo "Class $class NOT found.\n";
}
