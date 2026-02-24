<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Modules\Website\Models\Project;
use App\Filament\Clusters\Website\Resources\ProjectResource;
use Filament\Forms\ComponentContainer;
use Filament\Forms\Form;

// Get a project that has media
$project = Project::with('media')->whereHas('media')->first();

if (!$project) {
    echo "No project with media found.\n";
    exit;
}

echo "Project ID: {$project->id}\n";
echo "Media count: {$project->media->count()}\n";
foreach ($project->media as $m) {
    echo "Media: {$m->uuid} - {$m->file_name}\n";
}

// Simulate form hydration
$form = ProjectResource::form(new \Filament\Schemas\Schema());
$container = ComponentContainer::make(app(\Filament\Pages\Page::class)); // dummy livewire component
// Since we can't easily fake a full Livewire component, let's just inspect the fields
$components = $form->getComponents();

echo "\n--- SpatieMediaLibraryFileUpload fields ---\n";
foreach ($form->getFlatComponents() as $name => $component) {
    if ($component instanceof \Filament\Forms\Components\SpatieMediaLibraryFileUpload) {
        $component->record($project);
        $state = $component->getState();
        echo "Field '{$name}':\n";
        print_r($state);
    }
}
