<?php

namespace Modules\Knx\Http\Resources;

use Illuminate\Http\Request;
use Modules\Knx\Models\KnxEmployee;

/**
 * `Technician` of docs/BACKEND-API.md §3 — the people the planning assigns work
 * to. A subset of `knx_employees`: only the `field` ones are technicians; office
 * staff are never planned.
 *
 * @property-read KnxEmployee $resource
 */
class TechnicianResource extends KnxResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->resource->id,
            'initials' => $this->resource->initials,
            'name' => $this->resource->name,
        ];
    }
}
