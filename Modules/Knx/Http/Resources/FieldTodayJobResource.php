<?php

namespace Modules\Knx\Http\Resources;

use Illuminate\Http\Request;
use Modules\Knx\Models\KnxPlanningAssignment;
use Modules\Knx\Models\KnxProject;
use Modules\Knx\Models\KnxZone;

/**
 * `TodayJob` of the Veld contract.
 *
 * `room` is a string and never null there, so a project without zones answers an
 * empty string rather than a missing field: the phone renders a line, and a `null`
 * would crash it. Everything else (`zoneStatus`, `blockingReason`) is nullable on
 * purpose — "we do not know yet" is a real answer.
 */
class FieldTodayJobResource extends KnxResource
{
    public function __construct(
        KnxPlanningAssignment $assignment,
        private readonly ?KnxZone $zone,
        private readonly array $tasks,
    ) {
        parent::__construct($assignment);
    }

    public function toArray(Request $request): array
    {
        /** @var KnxProject $project */
        $project = $this->resource->project;
        $blocker = $this->zone?->blockingCheck();

        return [
            'id' => (string) $this->resource->getKey(),
            'projectCode' => $project->code,
            'projectName' => $project->name,
            'city' => $project->city,
            'room' => $this->zone?->name ?? '',
            'zoneStatus' => $this->zone?->derivedStatus(),
            'blockingReason' => $blocker?->note,
            'tasks' => $this->tasks,
        ];
    }
}
