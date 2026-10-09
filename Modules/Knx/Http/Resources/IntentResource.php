<?php

namespace Modules\Knx\Http\Resources;

use Illuminate\Http\Request;
use Modules\Knx\Models\KnxIntent;

/**
 * `Intent` of the KNX-55 contract: what a module, a channel or a device must do.
 *
 * `target` is the element identity and `author`/`updatedAt` say who wrote this version
 * and when. `version` is per target: the same element can have version 1, 2, 3… over
 * time, and only one of them is current.
 *
 * @property-read KnxIntent $resource
 */
class IntentResource extends KnxResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->resource->getKey(),
            'target' => [
                'type' => $this->resource->target_type,
                'boardId' => $this->resource->boardExternalId(),
                'slot' => $this->resource->module_slot,
                'channel' => $this->resource->channel,
                'deviceId' => $this->resource->device_id === null ? null : (string) $this->resource->device_id,
            ],
            'roomId' => $this->resource->room_id === null ? null : (string) $this->resource->room_id,
            'functionId' => $this->resource->function_id === null ? null : (string) $this->resource->function_id,
            'text' => $this->resource->text,
            'version' => $this->resource->version,
            'author' => $this->resource->author === null ? null : [
                'id' => (string) $this->resource->author->getKey(),
                'name' => $this->resource->author->shortName(),
            ],
            'updatedAt' => $this->resource->updated_at->toIso8601ZuluString(),
        ];
    }
}
