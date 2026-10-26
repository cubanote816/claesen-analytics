<?php

namespace Modules\Knx\Http\Resources;

use Illuminate\Http\Request;
use Modules\Knx\Models\KnxDevice;

/**
 * `Device` of docs/BACKEND-API.md §3.
 *
 * The two booleans are derived, because both are questions about *other* rows:
 *   - `isNew` — a field registration the office has not acknowledged yet;
 *   - `hasConflict` — this address collides with a conflict that is still
 *     `open`/`in_review` for the same project.
 *
 * `hasConflict` is passed in by the controller (one pre-loaded set of addresses
 * for the whole list) instead of querying per device.
 *
 * @property-read KnxDevice $resource
 */
class DeviceResource extends KnxResource
{
    public function __construct(KnxDevice $resource, private readonly array $conflictAddresses = [])
    {
        parent::__construct($resource);
    }

    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->resource->id,
            'address' => $this->resource->address,
            'type' => $this->resource->type,
            'room' => $this->resource->room?->name,
            // null means "unknown board" ("?" in the UI); it is not "no board".
            'board' => $this->resource->board?->code,
            'serial' => $this->resource->serial,
            'registeredBy' => $this->resource->registeredBy?->shortName(),
            'source' => $this->resource->source,
            'isNew' => $this->resource->isNew(),
            'hasConflict' => in_array($this->resource->address, $this->conflictAddresses, true),
        ];
    }
}
