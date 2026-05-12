<?php

use Modules\Core\Models\User;
use Modules\Safety\Models\Checklist;
use Modules\Safety\Http\Controllers\InspectionController;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "--- INTEGRATION TEST: INSPECTION SUBMISSION ---\n";

$user = User::first();
$checklist = Checklist::where('is_active', true)->first();
$question = $checklist->questions()->first();

if (!$checklist || !$question) {
    die("Error: No active checklist or questions found.\n");
}

// Simulate Request
$request = Request::create('/api/safety/inspections', 'POST', [
    'checklist_id' => $checklist->id,
    'project_id'   => 'PRJ-2026-999', // Valid mock project ID
    'answers'      => json_encode([
        [
            'question_id' => $question->id,
            'value'       => 'YES',
            'remark'      => 'Test success from internal script'
        ]
    ])
]);

// Add a fake photo
$photo = UploadedFile::fake()->image("photo_{$question->id}.jpg");
$request->files->set('photos', [$question->id => $photo]);

// Authenticate user for the request
$request->setUserResolver(fn() => $user);

echo "Submitting inspection for Project: PRJ-2026-999...\n";

try {
    $controller = new InspectionController();
    $response = $controller->store($request);
    
    echo "RESPONSE STATUS: " . $response->getStatusCode() . "\n";
    echo "RESPONSE BODY: " . $response->getContent() . "\n";
    
    if ($response->getStatusCode() === 201) {
        echo "\n✅ SUCCESS: Inspection saved and Job dispatched!\n";
    } else {
        echo "\n❌ FAILED: Unexpected response status.\n";
    }
} catch (\Exception $e) {
    echo "\n❌ ERROR: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
}
