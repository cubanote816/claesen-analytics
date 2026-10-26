<?php

namespace Modules\Knx\Http\Resources;

use Illuminate\Http\Request;

use Modules\Knx\Models\KnxClient;

/**
 * `Client` of docs/BACKEND-API.md §3.
 *
 * `id` is cast to string because the contract types it as a string and the front
 * uses it as an opaque key (its own fixture uses "c1").
 *
 * @property-read KnxClient $resource
 */
class ClientResource extends KnxResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->resource->id,
            'name' => $this->resource->name,
            'city' => $this->resource->city,
            'contact' => $this->resource->contact,
            'phone' => $this->resource->phone,
            'email' => $this->resource->email,
            'address' => $this->resource->address,
            'vat' => $this->resource->vat,
        ];
    }
}
