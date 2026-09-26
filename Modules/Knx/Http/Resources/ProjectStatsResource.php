<?php

namespace Modules\Knx\Http\Resources;

use Illuminate\Http\Request;

use Modules\Knx\Models\KnxProject;

/**
 * `ProjectStats` (§3): the numbers of the project's header.
 *
 * `openConflicts` is the only computed one — conflicts of this project still in
 * `open`/`in_review`, i.e. the ones the office has to work.
 *
 * @property-read KnxProject $resource
 */
class ProjectStatsResource extends KnxResource
{
    public function toArray(Request $request): array
    {
        return [
            'devicesPlanned' => $this->resource->devices_planned,
            'devicesDone' => $this->resource->devices_done,
            'openConflicts' => $this->resource->openConflicts()->count(),
            'photos' => $this->resource->photos,
            'deadline' => $this->resource->deadline?->format('Y-m-d'),
        ];
    }
}
