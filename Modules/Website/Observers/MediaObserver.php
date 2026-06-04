<?php

namespace Modules\Website\Observers;

use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Modules\Website\Models\Project;
use Modules\Website\Jobs\GenerateGalleryMediaMetadataJob;
use Modules\Website\Jobs\NotifyAstroFrontendJob;

class MediaObserver
{
    public function saved(Media $media): void
    {
        if ($media->model_type === Project::class) {
            if ($media->collection_name === 'gallery') {
                // Notify frontend after the metadata job persists caption/alt (job handles it)
                GenerateGalleryMediaMetadataJob::dispatch($media->id);
            } else {
                NotifyAstroFrontendJob::dispatch();
            }
        }
    }

    public function deleted(Media $media): void
    {
        if ($media->model_type === Project::class) {
            NotifyAstroFrontendJob::dispatch();
        }
    }
}
