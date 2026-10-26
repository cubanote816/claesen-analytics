<?php

namespace Modules\Knx\Services;

use Modules\Knx\Models\KnxEmployee;
use Modules\Knx\Models\KnxPlanningAssignment;
use Modules\Knx\Models\KnxProject;

/**
 * Assigning work to a technician on a day.
 *
 * `PUT /planning` is documented as idempotent on (technicianId, date): the same
 * call twice leaves one assignment, and a second call with a different project
 * *replaces* the first instead of piling up. That is enforced here and by the
 * unique index on the table, so two concurrent requests cannot create two rows.
 */
class PlanningService
{
    public function assign(KnxEmployee $employee, string $date, KnxProject $project): KnxPlanningAssignment
    {
        return KnxPlanningAssignment::query()->updateOrCreate(
            ['employee_id' => $employee->id, 'date' => $date],
            ['project_id' => $project->id],
        );
    }

    /**
     * Removing an assignment that does not exist is not an error: the caller asked
     * for "no assignment on that day", and that is the state it gets.
     */
    public function unassign(KnxEmployee $employee, string $date): void
    {
        KnxPlanningAssignment::query()
            ->where('employee_id', $employee->id)
            ->whereDate('date', $date)
            ->delete();
    }
}
