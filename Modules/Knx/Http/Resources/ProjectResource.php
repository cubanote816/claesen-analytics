<?php

namespace Modules\Knx\Http\Resources;

use Illuminate\Http\Request;

use Modules\Knx\Models\KnxProject;

/**
 * `Project` of docs/BACKEND-API.md §3.
 *
 * Two fields are derived rather than stored, both because the contract asks for
 * something the schema deliberately does not keep:
 *   - `clientName` comes from the client relation (the front shows it in the
 *     project list, and duplicating the name would let it go stale);
 *   - `lead` is the person's *short* name ("L. Smet"), formatted by the model.
 *
 * @property-read KnxProject $resource
 */
class ProjectResource extends KnxResource
{
    public function toArray(Request $request): array
    {
        return [
            'code' => $this->resource->code,
            'name' => $this->resource->name,
            'clientId' => (string) $this->resource->client_id,
            'clientName' => $this->resource->client?->name,
            'city' => $this->resource->city,
            'lead' => $this->resource->lead?->shortName(),
            'devicesPlanned' => $this->resource->devices_planned,
            'devicesDone' => $this->resource->devices_done,
            'photos' => $this->resource->photos,
            'status' => $this->resource->status,
            // Dates are calendar days in the contract (YYYY-MM-DD), not instants.
            'deadline' => $this->resource->deadline?->format('Y-m-d'),
        ];
    }
}
