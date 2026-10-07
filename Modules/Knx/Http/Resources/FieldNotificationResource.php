<?php

namespace Modules\Knx\Http\Resources;

use Illuminate\Http\Request;
use Modules\Knx\Models\KnxNotification;

/**
 * `FieldNotification` of docs/BACKEND-API.md §3 — an apparatus the field app
 * reported and the office has not confirmed yet.
 *
 * `board` (the code the UI shows) and `boardName` (the longer label) both come
 * from the board relation and are null when the field reported a board the office
 * has never seen: that is not an error, it is the case the office has to triage.
 *
 * @property-read KnxNotification $resource
 */
class FieldNotificationResource extends KnxResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->resource->id,
            'address' => $this->resource->address,
            'type' => $this->resource->type,
            'room' => $this->resource->room,
            'board' => $this->resource->board?->code,
            'boardName' => $this->resource->board?->name,
            'serial' => $this->resource->serial,
            'projectCode' => $this->resource->project?->code,
            'projectName' => $this->resource->project?->name,
            'reportedBy' => $this->resource->reportedBy?->shortName(),
            'reportedAt' => $this->resource->reported_at->toIso8601ZuluString(),
            'acked' => $this->resource->isAcked(),
        ];
    }
}
