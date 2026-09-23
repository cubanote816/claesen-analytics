<?php

declare(strict_types=1);

namespace Modules\Website\Console\Commands;

use Illuminate\Console\Command;
use Modules\Website\Models\Project;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * F3/CLA-467 of the multi-organization program — docs/ai/adr-multi-organization.md.
 *
 * The operational interface for the focal-point criterion. A Filament UI
 * for this is deliberately out of scope: Filament\Forms\Components\
 * SpatieMediaLibraryFileUpload has no first-party support for per-file
 * extra fields (alt/caption already work around this the same way — via
 * an AI job, not a form field — see
 * Modules\Website\Jobs\GenerateGalleryMediaMetadataJob). A real per-image
 * focal-point picker is a standalone UI feature (comparable in scope to
 * Modules\FieldOps's location pickers, each of which got its own ticket)
 * — this command is the correct-sized answer for now: the data layer
 * (Modules\Website\Models\Project::getApiGalleryAttribute()/
 * Modules\Website\App\Http\Resources\ProjectResource already read and
 * serve `focal_point`) is complete and real; every media item already
 * defaults to a centered focal point on creation
 * (Modules\Website\Observers\MediaObserver).
 */
class SetMediaFocalPointCommand extends Command
{
    protected $signature = 'website:set-media-focal-point {media : Media ID} {x : 0.0-1.0} {y : 0.0-1.0}';

    protected $description = "Set a Project media item's focal point (x/y, 0.0-1.0 each)";

    public function handle(): int
    {
        $x = (float) $this->argument('x');
        $y = (float) $this->argument('y');

        if ($x < 0.0 || $x > 1.0 || $y < 0.0 || $y > 1.0) {
            $this->error('x and y must each be between 0.0 and 1.0.');

            return self::FAILURE;
        }

        $media = Media::find((int) $this->argument('media'));

        if (! $media || $media->model_type !== Project::class) {
            $this->error('No Project media found with that ID.');

            return self::FAILURE;
        }

        $media->setCustomProperty('focal_point', ['x' => $x, 'y' => $y]);
        $media->save();

        $this->info("Focal point for media #{$media->id} set to x={$x}, y={$y}.");

        return self::SUCCESS;
    }
}
