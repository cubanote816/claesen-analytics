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

        // F3/CLA-467: every gallery/detail_gallery/featured_image item gets
        // a focal point from the moment it's created — center by default,
        // never absent — so the public API's api_gallery/ProjectResource
        // payload always has a value to serve, and an admin can later
        // override it (website:set-media-focal-point) without a migration.
        if ($media->wasRecentlyCreated && $media->getCustomProperty('focal_point') === null) {
            $media->setCustomProperty('focal_point', ['x' => 0.5, 'y' => 0.5]);
            $media->saveQuietly();
        }

        if ($media->collection_name === 'gallery') {
            // Gallery saves: dispatch AI metadata job first.
            // That job calls requestRebuild() in its finally block after
            // caption/alt are generated — avoids a rebuild before metadata lands.
            GenerateGalleryMediaMetadataJob::dispatch($media->id);
            return;
        }

        // featured_image, detail_gallery, or any future collection.
        $this->publicationService->requestRebuild($media->model?->site_id, 'content_changed');
    }

    public function deleted(Media $media): void
    {
        if ($media->model_type !== Project::class) {
            return;
        }

        $this->publicationService->requestRebuild($media->model?->site_id, 'content_changed');
    }
}
