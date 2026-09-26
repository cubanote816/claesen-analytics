<?php

namespace Modules\Knx\Http\Resources;

use Illuminate\Http\Request;
use Modules\Knx\Models\KnxEmployee;
use Modules\Knx\Models\KnxPlanningAssignment;

/**
 * `TechnicianToday` of docs/BACKEND-API.md §3: what a technician is doing today.
 *
 * `status` only ever answers `off` or `on_site`. The contract also lists
 * `travelling`, and the office app's own fixture produces it — but by *index*
 * (`index === 2 ? 'travelling' : 'on_site'`), not from any data. Nothing in this
 * domain knows whether a technician is on the road: that is a field check-in
 * (Veld), and until it exists, emitting `travelling` would be inventing a state
 * the office would then trust. The day Veld reports arrivals, this is where it
 * lands.
 *
 * @property-read KnxEmployee $resource
 */
class TechnicianTodayResource extends KnxResource
{
    public function __construct(
        KnxEmployee $technician,
        private readonly ?KnxPlanningAssignment $assignment = null,
    ) {
        parent::__construct($technician);
    }

    public function toArray(Request $request): array
    {
        $project = $this->assignment?->project;

        return [
            'technician' => TechnicianResource::make($this->resource)->toArray($request),
            'projectCode' => $project?->code,
            'projectName' => $project?->name,
            'city' => $project?->city,
            'status' => $project === null ? 'off' : 'on_site',
        ];
    }
}
