<?php

namespace Modules\Knx\Http\Resources;

use Illuminate\Http\Request;

use Modules\Knx\Models\KnxClient;

/**
 * `ClientDetail` = `Client` + its projects. The detail screen is the only place
 * the front asks for both at once, so `GET /clients/{id}` answers in one call.
 *
 * @property-read KnxClient $resource
 */
class ClientDetailResource extends KnxResource
{
    public function toArray(Request $request): array
    {
        return [
            ...ClientResource::make($this->resource)->toArray($request),
            'projects' => ProjectResource::collection($this->resource->projects)->toArray($request),
        ];
    }
}
