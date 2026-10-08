<?php

declare(strict_types=1);

namespace Modules\Knx\Http\Resources;

use Illuminate\Http\Request;
use Modules\Knx\Models\KnxProjectRoom;
use Modules\Knx\Models\KnxRoomAlias;

/**
 * `Room` of the contract (KNX-44).
 *
 * The canonical name is the office's own. `aliases` carries the names the other
 * vocabularies use (ETS, the worklist, the plan) plus the manual ones the office
 * added; `zoneId` is the readiness zone of this room, because zona = habitación.
 *
 * @property-read KnxProjectRoom $resource
 */
class RoomResource extends KnxResource
{
    public function toArray(Request $request): array
    {
        $room = $this->resource;

        return [
            'id' => (string) $room->getKey(),
            'name' => $room->name,
            'floor' => $room->floor,
            // Null when the room has no zone yet. Should not happen for a room created
            // by any of this module's paths (all of them create one), but the read must
            // not invent an id.
            'zoneId' => $room->zone === null ? null : (string) $room->zone->getKey(),
            'aliases' => $room->aliases
                ->map(static fn (KnxRoomAlias $alias): array => [
                    'source' => $alias->source,
                    'name' => $alias->name,
                ])
                ->values()
                ->all(),
        ];
    }
}
