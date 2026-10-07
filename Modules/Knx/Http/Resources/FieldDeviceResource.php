<?php

declare(strict_types=1);

namespace Modules\Knx\Http\Resources;

use Illuminate\Http\Request;
use Modules\Knx\Models\KnxDevice;

/**
 * `FieldDevice` of the Veld contract: what the app gets back after registering an
 * apparatus, and the same shape it gets back inside a `409` as the device already
 * occupying the address.
 *
 * Note what is NOT here: no `clientId` (the app already knows its own), no project
 * and no zone readiness. The app asked about one device, so that is what it gets.
 *
 * @property-read KnxDevice $resource
 */
class FieldDeviceResource extends KnxResource
{
    public function toArray(Request $request): array
    {
        $device = $this->resource;

        return [
            'id' => (string) $device->getKey(),
            'address' => $device->address,
            'type' => $device->type,
            // Both are strings in the contract, so an unmodelled room or board is an
            // empty string rather than a missing key.
            'roomName' => $device->room?->name ?? '',
            'boardCode' => $device->board?->code,
            'serial' => $device->serial ?? '',
            // The short display name, the same one the office sees for this person.
            'registeredBy' => $device->registeredBy?->shortName() ?? '',
            'registeredAt' => $device->registered_at?->toIso8601String(),
        ];
    }
}
