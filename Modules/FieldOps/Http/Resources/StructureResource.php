<?php

namespace Modules\FieldOps\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class StructureResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'                 => $this->id,
            'structure_type'     => $this->whenLoaded('structureType', fn () => [
                'id'   => $this->structureType->id,
                'name' => $this->structureType->getTranslations('name'),
            ]),
            'height'             => $this->height,
            'lat'                => $this->lat,
            'lng'                => $this->lng,
            'info'               => $this->getTranslations('info'),
            'external_safety_id' => $this->external_safety_id,
            'external_access_id' => $this->external_access_id,
            'cafca_material_id'  => $this->cafca_material_id,
            'terrains'           => $this->whenLoaded('terrains', fn () =>
                $this->terrains->map(fn ($t) => [
                    'id'   => $t->id,
                    'name' => $t->getTranslations('name'),
                ])
            ),
            'created_by'         => $this->whenLoaded('createdBy', fn () => [
                'id'   => $this->createdBy->id,
                'name' => $this->createdBy->name,
            ]),
            // Only present in show() — luminaireFrames is not loaded in index()
            // because the nested payload is too heavy for a list response.
            'luminaire_frames'   => $this->whenLoaded('luminaireFrames', fn () =>
                $this->luminaireFrames->map(fn ($frame) => [
                    'id'        => $frame->id,
                    'luminaires' => $frame->luminaires->map(fn ($l) => [
                        'id'   => $l->id,
                        'type' => $l->luminaireType?->getTranslations('name') ?? [],
                    ]),
                ])
            ),
            'translation_status' => $this->ai_translation_status ?? 'pending',
        ];
    }
}
