<?php

namespace Modules\Website\Observers;

use Modules\Website\Models\Project;
use Modules\Website\Jobs\NotifyAstroFrontendJob;

class ProjectObserver
{
    public function saved(Project $project): void
    {
        NotifyAstroFrontendJob::dispatch();
    }

    public function deleted(Project $project): void
    {
        NotifyAstroFrontendJob::dispatch();
    }

    public function restored(Project $project): void
    {
        NotifyAstroFrontendJob::dispatch();
    }

    public function forceDeleted(Project $project): void
    {
        NotifyAstroFrontendJob::dispatch();
    }
}
