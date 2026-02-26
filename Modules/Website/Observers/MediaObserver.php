<?php

namespace Modules\Website\Observers;

use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Modules\Website\Models\Project;
use Modules\Website\Jobs\NotifyAstroFrontendJob;

class MediaObserver
{
    public function saved(Media $media): void
    {
        if ($media->model_type === Project::class) {
            NotifyAstroFrontendJob::dispatch();
        }
    }

    public function deleted(Media $media): void
    {
        if ($media->model_type === Project::class) {
            NotifyAstroFrontendJob::dispatch();
        }
    }
}
