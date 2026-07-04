<?php

namespace Modules\FieldOps\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class FoClientResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'              => $this->id,
            'name'            => $this->name,
            'street'          => $this->street,
            'city'            => $this->city,
            'phone'           => $this->phone,
            'email'           => $this->email,
            'language'        => $this->language,
            'complexes_count' => $this->whenCounted('complexes'),
        ];
    }
}
