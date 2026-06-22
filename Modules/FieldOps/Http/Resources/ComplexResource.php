<?php

namespace Modules\FieldOps\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ComplexResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'         => $this->id,
            'name'       => $this->name,
            'city'       => $this->city,
            'street'     => $this->street,
            'zipcode'    => $this->zipcode,
            'lat'        => $this->lat,
            'lng'        => $this->lng,
            'zoom'       => $this->zoom ?? 17.0,
            'client'     => $this->whenLoaded('client', fn () => new FoClientResource($this->client)),
            'created_by' => $this->whenLoaded('createdBy', fn () => [
                'id'   => $this->createdBy->id,
                'name' => $this->createdBy->name,
            ]),
        ];
    }
}
