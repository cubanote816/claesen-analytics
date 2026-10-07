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
        private readonly int $openConflicts = 0,
        private readonly bool $visitClosed = false,
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
            // The client's own address line, exactly as the office typed it. It is
            // free text on `knx_clients`, so it may already carry the city and the
            // postcode (the demo fixture does). The app therefore renders this line
            // and falls back to `city` only when it is empty — never both joined.
            // Optional in the schema, so an unknown one is an empty string, the way
            // `room` is.
            'address' => $project->client->address ?? '',
            'room' => $this->zone?->name ?? '',
            'zoneStatus' => $this->zone?->derivedStatus(),
            'blockingReason' => $blocker?->note,
            'tasks' => $this->tasks,
            // The four numbers the card draws its progress and its counters from,
            // from the same source as the office header (`ProjectStatsResource`):
            // three columns the office already maintains, plus the live conflict
            // count. They come in the day's payload on purpose — asking per job would
            // be a request per card.
            'devicesPlanned' => $project->devices_planned,
            'devicesDone' => $project->devices_done,
            'photos' => $project->photos,
            'openConflicts' => $this->openConflicts,
            // Whether today's visit was already signed off. It comes in the day's
            // payload for the same reason the counters do: the card and the job it
            // opens need it, and asking per card would be a request per card.
            'visitClosed' => $this->visitClosed,
        ];
    }
}
