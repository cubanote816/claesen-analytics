<?php

namespace Modules\FieldOps\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class LuminaireFrameResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'         => $this->id,
            'frame_type' => $this->whenLoaded('frameType', fn () => [
                'id'   => $this->frameType->id,
                'name' => $this->frameType->name,
            ]),
            'structures' => $this->whenLoaded('structures', fn () =>
                $this->structures->map(fn ($s) => ['id' => $s->id])
            ),
            'created_by' => $this->whenLoaded('createdBy', fn () => [
                'id'   => $this->createdBy->id,
                'name' => $this->createdBy->name,
            ]),
            // Only present in show() — too heavy for index responses
            'luminaires' => $this->whenLoaded('luminaires', fn () =>
                LuminaireResource::collection($this->luminaires)
            ),
        ];
    }
}
