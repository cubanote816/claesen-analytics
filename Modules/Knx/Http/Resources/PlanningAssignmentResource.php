<?php

namespace Modules\Knx\Http\Resources;

use Illuminate\Http\Request;
use Modules\Knx\Models\KnxPlanningAssignment;

/**
 * `PlanningAssignment` of docs/BACKEND-API.md §3: the technician, the day and the
 * project *code* — the front never sees the numeric ids behind them.
 *
 * @property-read KnxPlanningAssignment $resource
 */
class PlanningAssignmentResource extends KnxResource
{
    public function toArray(Request $request): array
    {
        return [
            'technicianId' => (string) $this->resource->employee_id,
            'date' => $this->resource->date->format('Y-m-d'),
            'projectCode' => $this->resource->project?->code,
        ];
    }
}
