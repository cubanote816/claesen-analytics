<?php

namespace Modules\FieldOps\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class LuminaireTypeResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'                    => $this->id,
            'name'                  => $this->name,
            'image'                 => $this->image,
            'luminaire_subgroup_id' => $this->luminaire_subgroup_id,
        ];
    }
}
