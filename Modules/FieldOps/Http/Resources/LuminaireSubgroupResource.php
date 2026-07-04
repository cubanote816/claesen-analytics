<?php

namespace Modules\FieldOps\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class LuminaireSubgroupResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'         => $this->id,
            'group_name' => $this->group_name,
            'brand'      => $this->brand,
        ];
    }
}
