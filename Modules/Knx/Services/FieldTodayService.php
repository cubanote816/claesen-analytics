<?php

namespace Modules\Knx\Services;

use Illuminate\Support\Collection;
use Modules\Knx\Models\KnxAcceptanceTest;
use Modules\Knx\Models\KnxConflict;
use Modules\Knx\Models\KnxEmployee;
use Modules\Knx\Models\KnxPlanningAssignment;
use Modules\Knx\Models\KnxProject;
use Modules\Knx\Models\KnxZone;

/**
 * What a technician has to do today (§2 of the Veld contract).
 *
 * **Scope is the assignment, not the company.** A technician only ever sees the
 * projects the planning put them on *today* — that is the contract's own rule, and
 * it is what keeps a phone from listing the whole office's work. Every `/field/*`
 * endpoint goes through this check, not just this one.
 *
 * Two derived bits live here, and both are decided rather than obvious:
 *
 *   - **`room`/`zoneStatus`/`blockingReason`** describe the zone that needs
 *     attention: the first one that is not ready, or the first one when everything
 *     is ready. A job is "go and work on this space", so pointing at a ready zone
 *     on a project that has a blocked one would be useless.
 *   - **`tasks`** is the one place in this API that returns display text. The front
 *     renders it as-is (`job.tasks.join(' · ')`), and its own fixture has Dutch
 *     labels, so the labels are Dutch here too — but they are *derived from the
 *     data* (devices still to register, tests still open) and not a fixed list.
 *     If the app ever wants them translated, the shape to send is a list of task
 *     kinds, and that is a one-line change on both sides.
 */
class FieldTodayService
{
    private const TASK_REGISTER_DEVICES = 'Toestellen registreren';

    private const TASK_TEST = 'Verlichting testen';

    /** Statuses that mean "this test still needs somebody". */
    private const OPEN_TEST_STATUSES = ['pending', 'failed', 'blocked'];

    /**
     * @return Collection<int, array{assignment: KnxPlanningAssignment, project: KnxProject, zone: KnxZone|null, tasks: list<string>, openConflicts: int}>
     */
    public function jobsFor(KnxEmployee $technician): Collection
    {
        $assignments = KnxPlanningAssignment::query()
            ->with([
                'project.client',
                'project.zones.checks.updatedBy',
            ])
            ->where('employee_id', $technician->getKey())
            ->whereDate('date', now()->toDateString())
            ->orderBy('id')
            ->get();

        // One query for the whole day rather than one per job: the card shows the
        // open-conflict count for every job, and a day usually has several. `pluck`
        // is applied to the fetched rows and not to the builder, because on the
        // builder it would replace the aggregate select.
        $openConflicts = KnxConflict::query()
            ->whereIn('project_id', $assignments->pluck('project_id'))
            ->open()
            ->groupBy('project_id')
            ->selectRaw('project_id, count(*) as aggregate')
            ->get()
            ->pluck('aggregate', 'project_id');

        return $assignments->map(fn (KnxPlanningAssignment $assignment): array => [
            'assignment' => $assignment,
            'project' => $assignment->project,
            'zone' => $this->zoneNeedingAttention($assignment->project),
            'tasks' => $this->tasksFor($assignment->project),
            'openConflicts' => (int) $openConflicts->get($assignment->project_id, 0),
        ]);
    }

    /**
     * True when this technician is planned on this project today.
     *
     * The single scope check every `/field/*` endpoint uses: a technician asking for
     * a project they are not on gets a 403, not a 404 — the project exists, they
     * simply have no business there.
     */
    public function isAssignedToday(KnxEmployee $technician, KnxProject $project): bool
    {
        return KnxPlanningAssignment::query()
            ->where('employee_id', $technician->getKey())
            ->where('project_id', $project->getKey())
            ->whereDate('date', now()->toDateString())
            ->exists();
    }

    private function zoneNeedingAttention(KnxProject $project): ?KnxZone
    {
        $zones = $project->zones;

        return $zones->first(fn (KnxZone $zone): bool => $zone->derivedStatus() !== KnxZone::STATUS_READY)
            ?? $zones->first();
    }

    /**
     * @return list<string>
     */
    private function tasksFor(KnxProject $project): array
    {
        $tasks = [];

        if ($project->devices_done < $project->devices_planned) {
            $tasks[] = self::TASK_REGISTER_DEVICES;
        }

        if (KnxAcceptanceTest::query()
            ->where('project_id', $project->getKey())
            ->whereIn('status', self::OPEN_TEST_STATUSES)
            ->exists()) {
            $tasks[] = self::TASK_TEST;
        }

        return $tasks;
    }
}
