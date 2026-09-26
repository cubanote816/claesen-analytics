<?php

namespace Modules\Knx\Services;

use Modules\Knx\Models\KnxConflict;
use Modules\Knx\Models\KnxNotification;

/**
 * The events of §6, and the cursor that makes them "since".
 *
 * Polling three endpoints every 5-15 seconds works, and that is what the office app
 * does today. This is the push version: two events, both of them about something
 * *appearing* — a device reported from the field, and a conflict opened. Nothing
 * else in the domain happens while somebody is looking at a screen and needs to
 * know immediately.
 *
 * The cursor is a pair of "highest id already sent" values rather than timestamps:
 * ids are monotonic and cannot be reordered by clock skew, and the office cares
 * about the order things arrived, not the instant they were stamped with.
 *
 * The service returns data; the controller turns it into `text/event-stream`. That
 * keeps the part worth testing (what is pending, and from where) separate from the
 * part that holds a socket open.
 */
class EventStreamService
{
    /**
     * @param  array{notification: int, conflict: int}  $cursor
     * @return array{cursor: array{notification: int, conflict: int}, events: list<array{event: string, data: array<string, mixed>}>}
     */
    public function pendingSince(array $cursor): array
    {
        $events = [];

        $notifications = KnxNotification::query()
            ->with('project')
            ->where('id', '>', $cursor['notification'])
            ->orderBy('id')
            ->get();

        foreach ($notifications as $notification) {
            $cursor['notification'] = $notification->getKey();

            $events[] = [
                'event' => 'field.device.created',
                'data' => array_filter([
                    'notificationId' => (string) $notification->getKey(),
                    'projectCode' => $notification->project?->code,
                    'address' => $notification->address,
                    'room' => $notification->room,
                ], static fn ($value): bool => $value !== null),
            ];
        }

        $conflicts = KnxConflict::query()
            ->with('project')
            ->where('id', '>', $cursor['conflict'])
            ->orderBy('id')
            ->get();

        foreach ($conflicts as $conflict) {
            $cursor['conflict'] = $conflict->getKey();

            $events[] = [
                'event' => 'conflict.created',
                'data' => array_filter([
                    'conflictId' => (string) $conflict->getKey(),
                    'projectCode' => $conflict->project?->code,
                    'address' => $conflict->address,
                ], static fn ($value): bool => $value !== null),
            ];
        }

        return ['cursor' => $cursor, 'events' => $events];
    }

    /**
     * Where a fresh connection starts: at the present, not at the beginning. A
     * client that just opened a tab does not want yesterday's failures replayed —
     * it wants the next thing that happens, and it already fetched the current state.
     *
     * @return array{notification: int, conflict: int}
     */
    public function currentCursor(): array
    {
        return [
            'notification' => (int) KnxNotification::query()->max('id'),
            'conflict' => (int) KnxConflict::query()->max('id'),
        ];
    }
}
