<?php

namespace Modules\Website\Observers;

use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Modules\Website\Models\Project;
use Modules\Website\Jobs\GenerateGalleryMediaMetadataJob;
use Modules\Website\Services\StaticSitePublicationService;

class MediaObserver
{
    public function __construct(
        private readonly StaticSitePublicationService $publicationService,
    ) {}

    public function saved(Media $media): void
    {
        if ($media->model_type !== Project::class) {
            return;
        }

        if ($media->collection_name === 'gallery') {
            // Gallery saves: dispatch AI metadata job first.
            // That job calls requestRebuild() in its finally block after
            // caption/alt are generated — avoids a rebuild before metadata lands.
            GenerateGalleryMediaMetadataJob::dispatch($media->id);
            return;
        }

        // featured_image, detail_gallery, or any future collection.
        $this->publicationService->requestRebuild('content_changed');
    }

    public function deleted(Media $media): void
    {
        if ($media->model_type !== Project::class) {
            return;
        }

        $this->publicationService->requestRebuild('content_changed');
    }
}
