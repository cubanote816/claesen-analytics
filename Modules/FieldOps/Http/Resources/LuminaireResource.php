<?php

namespace Modules\FieldOps\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class LuminaireResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'                 => $this->id,
            'luminaire_frame_id' => $this->luminaire_frame_id,
            'serial_number'      => $this->serial_number,
            'frame_position'     => $this->frame_position,
            'frame_x'            => $this->frame_x,
            'frame_y'            => $this->frame_y,
            'info'               => $this->getTranslations('info'),
            'cafca_material_id'  => $this->cafca_material_id,
            'luminaire_type'     => $this->whenLoaded('luminaireType', fn () => [
                'id'   => $this->luminaireType->id,
                'name' => $this->luminaireType->name,
            ]),
            'subgroup'           => $this->whenLoaded('subgroup', fn () => [
                'id'         => $this->subgroup->id,
                'brand'      => $this->subgroup->brand,
                'group_name' => $this->subgroup->group_name,
            ]),
            'created_by'         => $this->whenLoaded('createdBy', fn () => [
                'id'   => $this->createdBy->id,
                'name' => $this->createdBy->name,
            ]),
            'translation_status' => $this->ai_translation_status ?? 'pending',
        ];
    }
}
