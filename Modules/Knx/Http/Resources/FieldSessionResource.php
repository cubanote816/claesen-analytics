<?php

namespace Modules\Knx\Http\Resources;

use Illuminate\Http\Request;
use Modules\Knx\Models\KnxEmployee;

/**
 * `Session` of the field app — deliberately **not** the office `Session`.
 *
 * It carries no `role` and no `email`: the phone shows a name on a loading screen,
 * and giving a technician the office payload would invite the rest of the app to
 * assume it can ask for the same things (§2 of the Veld contract).
 *
 * @property-read KnxEmployee $resource
 */
class FieldSessionResource extends KnxResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->resource->getKey(),
            'name' => $this->resource->name,
            'initials' => $this->resource->initials,
            'domain' => config('knx.veld_domain'),
        ];
    }
}
