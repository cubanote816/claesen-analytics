<?php

declare(strict_types=1);

namespace Modules\Safety\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Modules\Performance\Models\Mirror\MirrorProject;
use Modules\Safety\Models\Inspection;

class ComplianceService
{
    /**
     * Returns enriched non-compliant project records for the compliance API.
     *
     * Sorting: projects with the most days overdue come first.
     * Projects never inspected (null date) go at the end — their urgency
     * is unknown, so they are grouped separately rather than promoted.
     *
     * @param  int|null  $userId  When set, scopes to projects where this user has submitted at least one inspection.
     * @param  int|null  $days    Override the compliance_days config threshold.
     * @return Collection<int, array{project_id:string, project_name:string, project_code:null, last_inspection_date:string|null, days_since_inspection:int|null}>
     */
    public function getNonCompliantProjects(?int $userId = null, ?int $days = null): Collection
    {
        $days ??= (int) config('safety.compliance_days');

        try {
            $query = MirrorProject::where('fl_active', true);

            if ($userId !== null) {
                $userProjectIds = Inspection::where('user_id', $userId)
                    ->distinct()
                    ->pluck('project_id');

                $query->whereIn('id', $userProjectIds);
            }

            $activeProjects = $query->get();
        } catch (\Throwable) {
            return collect();
        }

        if ($activeProjects->isEmpty()) {
            return collect();
        }

        // Batch-load latest inspection date per project (1 query, not N)
        $latestDates = Inspection::whereIn('project_id', $activeProjects->pluck('id'))
            ->selectRaw('project_id, MAX(completed_at) as latest_at')
            ->groupBy('project_id')
            ->pluck('latest_at', 'project_id');

        $cutoff = now()->subDays($days);

        return $activeProjects
            ->map(function (MirrorProject $project) use ($latestDates, $cutoff): ?array {
                $rawDate    = $latestDates->get($project->id);
                $lastCarbon = $rawDate ? Carbon::parse($rawDate) : null;

                if ($lastCarbon && $lastCarbon->greaterThan($cutoff)) {
                    return null; // compliant — skip
                }

                return [
                    'project_id'            => $project->id,
                    'project_name'          => $project->name,
                    'project_code'          => null,
                    'last_inspection_date'  => $lastCarbon?->toDateString(),
                    'days_since_inspection' => $lastCarbon ? (int) $lastCarbon->diffInDays(now()) : null,
                ];
            })
            ->filter()
            ->sortByDesc(fn (array $p) => $p['days_since_inspection'] ?? -1)
            ->values();
    }

    /**
     * Kept for CheckSafetyComplianceCommand — delegates to getNonCompliantProjects().
     */
    public function getMissingInspections(?int $days = null): Collection
    {
        return $this->getNonCompliantProjects(userId: null, days: $days);
    }
}
