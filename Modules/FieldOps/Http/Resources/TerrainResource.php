<?php

namespace Modules\FieldOps\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use Modules\FieldOps\Http\Resources\Concerns\HasMediaPayload;

class TerrainResource extends JsonResource
{
    use HasMediaPayload;

    public function toArray($request): array
    {
        return [
            'id'           => $this->id,
            'complex_id'   => $this->complex_id,
            'name'         => $this->getTranslations('name'),
            'terrain_type' => $this->whenLoaded('terrainType', fn () => new TerrainTypeResource($this->terrainType)),
            'lat'          => $this->lat,
            'lng'          => $this->lng,
            'photos'       => $this->photosPayload(),
            'documents'    => $this->documentsPayload(),
            'created_by'         => $this->whenLoaded('createdBy', fn () => [
                'id'   => $this->createdBy->id,
                'name' => $this->createdBy->name,
            ]),
            'translation_status' => $this->ai_translation_status ?? 'pending',
        ];
    }
}
