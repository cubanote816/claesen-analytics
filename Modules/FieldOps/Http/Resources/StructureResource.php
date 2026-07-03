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
            'access_type'        => $this->whenLoaded('accessType', fn () => $this->accessType ? [
                'id'   => $this->accessType->id,
                'name' => $this->accessType->getTranslations('name'),
            ] : null),
            'access_active'      => (bool) $this->access_active,
            'safety_type'        => $this->whenLoaded('safetyType', fn () => $this->safetyType ? [
                'id'   => $this->safetyType->id,
                'name' => $this->safetyType->getTranslations('name'),
            ] : null),
            'safety_certified'   => (bool) $this->safety_certified,
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
