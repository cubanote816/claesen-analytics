<?php

namespace Modules\Knx\Http\Resources;

use Illuminate\Http\Request;
use Modules\Knx\Models\KnxEmployee;

/**
 * `Employee` — the people the office can pick as a project lead (KNX-3).
 *
 * Deliberately three fields: the id the create form sends back as
 * `leadEmployeeId`, the full name, and the short display name the contract uses
 * everywhere ("L. Smet"). `GET /technicians` already exposes the field side of
 * this table with its own shape (`{id, initials, name}`), so this resource is the
 * office counterpart and keeps `shortName` instead of `initials`.
 *
 * @property-read KnxEmployee $resource
 */
class EmployeeResource extends KnxResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->resource->id,
            'name' => $this->resource->name,
            'shortName' => $this->resource->shortName(),
        ];
    }
}
