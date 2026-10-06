<?php

namespace Modules\Website\Observers;

use Modules\Website\Models\Project;
use Modules\Website\Services\StaticSitePublicationService;

/**
 * A project edit marks its site dirty. It does not publish it.
 *
 * These five events used to call requestRebuild(), so saving a project asked the frontend
 * to rebuild 20 seconds later: an edit was live before anyone decided it was ready. Since
 * 2026-10-06 the user chose deliberate publishing — the explicit "publish now" action is
 * the only trigger, and this observer's job is to record that there is something to
 * publish.
 */
class ProjectObserver
{
    public function __construct(
        private readonly StaticSitePublicationService $publicationService,
    ) {}

    public function created(Project $project): void
    {
        $this->publicationService->markContentChanged($project->site_id);
    }

    public function updated(Project $project): void
    {
        $this->publicationService->markContentChanged($project->site_id);
    }

    public function deleted(Project $project): void
    {
        $this->publicationService->markContentChanged($project->site_id);
    }

    public function restored(Project $project): void
    {
        $this->publicationService->markContentChanged($project->site_id);
    }

    public function forceDeleted(Project $project): void
    {
        $this->publicationService->markContentChanged($project->site_id);
    }
}
