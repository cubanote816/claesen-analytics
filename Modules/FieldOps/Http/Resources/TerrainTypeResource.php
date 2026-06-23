<?php

namespace Modules\FieldOps\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class TerrainTypeResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'   => $this->id,
            'type' => $this->getTranslations('type'),
        ];
    }
}
