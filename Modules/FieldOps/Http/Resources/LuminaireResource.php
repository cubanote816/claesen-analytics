<?php

namespace Modules\FieldOps\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use Modules\FieldOps\Http\Resources\Concerns\HasMediaPayload;

class LuminaireResource extends JsonResource
{
    use HasMediaPayload;

    public function toArray($request): array
    {
        $position = $this->relationLoaded('position') ? $this->position : null;

        return [
            'id'                 => $this->id,
            'luminaire_position_id' => $this->luminaire_position_id,
            'luminaire_frame_id' => $position?->luminaire_frame_id ?? $this->luminaire_frame_id,
            'serial_number'      => $this->serial_number,
            'frame_position'     => $position?->frame_position ?? $this->frame_position,
            'frame_x'            => $position?->frame_x ?? $this->frame_x,
            'frame_y'            => $position?->frame_y ?? $this->frame_y,
            'scale_x'            => $position?->scale_x ?? $this->scale_x,
            'scale_y'            => $position?->scale_y ?? $this->scale_y,
            'position_version'   => $position?->position_version ?? $this->position_version,
            'position_source'    => $position?->position_source ?? $this->position_source,
            'position_verified_at' => ($position?->position_verified_at ?? $this->position_verified_at)?->toIso8601String(),
            'installed_at'       => $this->installed_at?->toIso8601String(),
            'removed_at'         => $this->removed_at?->toIso8601String(),
            'is_current'         => $this->removed_at === null && $this->active_position_id !== null,
            'replaced_by_luminaire_id' => $this->replaced_by_luminaire_id,
            'info'               => $this->getTranslations('info'),
            'cafca_material_id'  => $this->cafca_material_id,
            'photos'             => $this->photosPayload(),
            'videos'             => $this->videosPayload(),
            'documents'          => $this->documentsPayload(),
            'luminaire_type'     => $this->whenLoaded('luminaireType', fn () => [
                'id'    => $this->luminaireType->id,
                'name'  => $this->luminaireType->name,
                'image' => $this->luminaireType->image,
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
