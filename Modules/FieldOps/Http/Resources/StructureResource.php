<?php

namespace Modules\FieldOps\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class StructureResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'             => $this->id,
            'height'         => $this->height,
            'lat'            => $this->lat,
            'lng'            => $this->lng,
            'info'           => $this->getTranslations('info'),
            'structure_type' => $this->whenLoaded('structureType', fn () => [
                'id'   => $this->structureType->id,
                'name' => $this->structureType->getTranslations('name'),
            ]),
            'terrains'       => $this->whenLoaded('terrains', fn () =>
                $this->terrains->map(fn ($t) => ['id' => $t->id, 'name' => $t->getTranslations('name')])
            ),
        ];
    }
}
