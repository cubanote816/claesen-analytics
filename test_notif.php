<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$user = \App\Models\User::first();
if (!$user) {
    echo "No user found.\n";
    exit;
}

echo "Recent Notifications:\n";
$notifs = $user->notifications()->latest()->limit(5)->get();
foreach ($notifs as $n) {
    echo "ID: {$n->id}, Type: {$n->type}\n";
    echo "Data: " . json_encode($n->data) . "\n";
    echo "-----\n";
}

echo "\nController Query Output:\n";
$query = $user->notifications()
              ->whereJsonContains('data->viewData->module', 'safety')
              ->latest()
              ->limit(5)
              ->get();

echo "Count from query: " . $query->count() . "\n";
