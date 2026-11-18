<?php

namespace Modules\Knx\Http\Resources;

use Illuminate\Http\Request;
use Modules\Knx\Models\KnxNotification;

/**
 * `Notification` of docs/BACKEND-API.md §3 (KNX-54).
 *
 * The typed block comes first (`kind`, the human line, the `target`) and the legacy
 * device columns after it, so an old reader that only knows `address`/`board` keeps
 * parsing the same payload: the generalization is additive.
 *
 * `receivedAt` is when the server got it; `capturedAt` is the worker's own time, which
 * for a message typed on site can be hours earlier.
 *
 * @property-read KnxNotification $resource
 */
class FieldNotificationResource extends KnxResource
{
    public function toArray(Request $request): array
    {
        $notification = $this->resource;

        return [
            'id' => (string) $notification->id,
            'kind' => $notification->kind,
            'projectCode' => $notification->project?->code,
            'title' => $notification->title,
            'summary' => $notification->summary,
            'roomId' => $notification->room_id === null ? null : (string) $notification->room_id,
            'boardId' => $notification->board === null
                ? null
                : (string) ($notification->board->slug ?? $notification->board->code),
            'moduleSlot' => $notification->module_slot === null ? null : (int) $notification->module_slot,
            'deviceId' => $notification->device_id === null ? null : (string) $notification->device_id,
            'issueId' => $notification->issue_id === null ? null : (string) $notification->issue_id,
            'actor' => [
                'id' => $notification->reported_by_employee_id === null ? null : (string) $notification->reported_by_employee_id,
                'name' => $notification->reportedBy?->shortName(),
            ],
            'target' => [
                'type' => $notification->target_type,
                'id' => $notification->target_id,
            ],
            'capturedAt' => $notification->captured_at?->toIso8601ZuluString(),
            'receivedAt' => $notification->reported_at->toIso8601ZuluString(),
            'acked' => $notification->isAcked(),
            // Legacy block: the device-registration reader.
            'address' => $notification->address,
            'type' => $notification->type,
            'room' => $notification->room,
            'board' => $notification->board?->code,
            'boardName' => $notification->board?->name,
            'serial' => $notification->serial,
            'projectName' => $notification->project?->name,
            'reportedBy' => $notification->reportedBy?->shortName(),
            'reportedAt' => $notification->reported_at->toIso8601ZuluString(),
        ];
    }
}
