<?php

use Modules\Website\Models\Project;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

// 1. Find or Create Project
$project = Project::firstOrCreate([
    'slug' => 'debug-media-test',
], [
    'title' => 'Debug Media Test',
    'category' => 'industrial', // Valid enum value?
]);

echo "Project ID: {$project->id}\n";

// 2. Add Media (if not exists)
if ($project->getMedia('gallery')->isEmpty()) {
    echo "Adding dummy media...\n";
    // Create a dummy file
    $dummyPath = storage_path('app/dummy.txt');
    file_put_contents($dummyPath, 'dummy content');

    try {
        $project->addMedia($dummyPath)
            ->usingName('Dummy Image')
            ->toMediaCollection('gallery');
        echo "Media added.\n";
    } catch (\Exception $e) {
        echo "Error adding media: " . $e->getMessage() . "\n";
    }
} else {
    echo "Media already exists.\n";
}

// 3. Verify Media
$project->refresh();
$mediaCount = $project->getMedia('gallery')->count();
echo "Media Count (After Add): {$mediaCount}\n";

// 4. Simulate Update (Save) - WITHOUT Events first to isolate
echo "Updating project title (without events)...\n";
Project::withoutEvents(function () use ($project) {
    $project->title = 'Debug Media Test Updated ' . time();
    $project->save();
});

// 5. Verify Media Again
$project->refresh();
$mediaCountAfterUpdate = $project->getMedia('gallery')->count();
echo "Media Count (After Update - No Events): {$mediaCountAfterUpdate}\n";

// 6. Simulate Update WITH Events
echo "Updating project title (WITH events)...\n";
$project->title = 'Debug Media Test Updated Event ' . time();
try {
    $project->save();
} catch (\Exception $e) {
    echo "Save failed: " . $e->getMessage() . "\n";
}

// 7. Verify Media Again
$project->refresh();
$mediaCountAfterEventUpdate = $project->getMedia('gallery')->count();
echo "Media Count (After Update - With Events): {$mediaCountAfterEventUpdate}\n";

if ($mediaCount > 0 && $mediaCountAfterUpdate == 0) {
    echo "CRITICAL: Media disappeared after update!\n";
} elseif ($mediaCount == $mediaCountAfterUpdate) {
    echo "SUCCESS: Media persisted after update.\n";
} else {
    echo "State changed unexpectedly.\n";
}

// 6. Clean up
// $project->clearMediaCollection('gallery');
