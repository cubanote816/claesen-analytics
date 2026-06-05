<?php

namespace Modules\Website\Observers;

use Modules\Website\Models\Project;
use Modules\Website\Services\StaticSitePublicationService;

class ProjectObserver
{
    public function __construct(
        private readonly StaticSitePublicationService $publicationService,
    ) {}

    public function created(Project $project): void
    {
        $this->publicationService->requestRebuild('content_changed');
    }

    public function updated(Project $project): void
    {
        $this->publicationService->requestRebuild('content_changed');
    }

    public function deleted(Project $project): void
    {
        $this->publicationService->requestRebuild('content_changed');
    }

    public function restored(Project $project): void
    {
        $this->publicationService->requestRebuild('content_changed');
    }

    public function forceDeleted(Project $project): void
    {
        $this->publicationService->requestRebuild('content_changed');
    }
}
