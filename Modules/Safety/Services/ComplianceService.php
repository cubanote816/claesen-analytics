<?php

declare(strict_types=1);

namespace Modules\Safety\Services;

use Illuminate\Support\Collection;
use Modules\Performance\Models\Mirror\MirrorProject;
use Modules\Safety\Models\Inspection;

class ComplianceService
{
    public function getMissingInspections(?int $days = null): Collection
    {
        $days ??= (int) config('safety.compliance_days');

        try {
            $activeProjects = MirrorProject::where('fl_active', true)->get();
        } catch (\Throwable) {
            return collect();
        }

        return $activeProjects->filter(function ($project) use ($days) {
            $latest = Inspection::where('project_id', $project->id)
                ->latest('completed_at')
                ->first();

            return !$latest || $latest->completed_at->diffInDays(now()) > $days;
        });
    }
}
