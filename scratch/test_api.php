<?php

use Modules\Core\Models\User;
use Modules\Safety\Models\Checklist;
use Modules\Performance\Models\Mirror\MirrorProject;
use Illuminate\Support\Facades\Auth;

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "--- TESTING SAFETY API ---\n";

// 1. Get a user
$user = User::first();
if (!$user) {
    die("Error: No users found.\n");
}
echo "User: {$user->name} ({$user->email})\n";

// 2. Generate a token with the required ability
$token = $user->createToken('test_token', ['role:safety-access'])->plainTextToken;
echo "Token: {$token}\n";

// 3. Test Active Checklist
echo "\nTesting GET /api/safety/checklists/active...\n";
$checklist = Checklist::where('is_active', true)->with('questions')->first();
if (!$checklist) {
    echo "FAIL: No active checklist found in DB.\n";
} else {
    echo "SUCCESS: Found checklist '{$checklist->name}' with " . $checklist->questions->count() . " questions.\n";
}

// 4. Test Projects
echo "\nTesting GET /api/safety/projects...\n";
$projects = MirrorProject::where('fl_active', true)->limit(5)->get();
if ($projects->isEmpty()) {
    echo "FAIL: No active projects found in DB.\n";
} else {
    echo "SUCCESS: Found " . $projects->count() . " projects.\n";
    foreach ($projects as $p) {
        echo " - [{$p->id}] {$p->name}\n";
    }
}

echo "\n--- TEST COMPLETE ---\n";
