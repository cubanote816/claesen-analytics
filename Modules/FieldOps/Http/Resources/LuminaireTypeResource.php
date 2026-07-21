<?php

namespace Modules\FieldOps\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class LuminaireTypeResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'product_family' => $this->product_family,
            'model_reference' => $this->model_reference,
            'typical_application' => $this->typical_application,
            'image' => $this->image,
            'image_source_url' => $this->image_source_url,
            'luminaire_subgroup_id' => $this->luminaire_subgroup_id,
        ];
    }
}
