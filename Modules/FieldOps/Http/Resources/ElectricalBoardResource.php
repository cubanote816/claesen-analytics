<?php

namespace Modules\FieldOps\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use Modules\FieldOps\Http\Resources\Concerns\HasMediaPayload;

class ElectricalBoardResource extends JsonResource
{
    use HasMediaPayload;

    public function toArray($request): array
    {
        return [
            'id'                    => $this->id,
            'electrical_board_type' => $this->whenLoaded('electricalBoardType', fn () => [
                'id'   => $this->electricalBoardType->id,
                'name' => $this->electricalBoardType->getTranslations('name'),
            ]),
            'lat'                   => $this->lat,
            'lng'                   => $this->lng,
            'location_description'  => $this->getTranslations('location_description'),
            'photos'                => $this->photosPayload(),
            'documents'             => $this->documentsPayload(),
            'complexes'             => $this->whenLoaded('complexes', fn () =>
                $this->complexes->map(fn ($c) => ['id' => $c->id, 'name' => $c->name])
            ),
            'terrains'              => $this->whenLoaded('terrains', fn () =>
                $this->terrains->map(fn ($t) => ['id' => $t->id, 'name' => $t->getTranslations('name')])
            ),
            'structures'            => $this->whenLoaded('structures', fn () =>
                $this->structures->map(fn ($s) => ['id' => $s->id])
            ),
            'created_by'            => $this->whenLoaded('createdBy', fn () => [
                'id'   => $this->createdBy->id,
                'name' => $this->createdBy->name,
            ]),
            'translation_status'    => $this->ai_translation_status ?? 'pending',
        ];
    }
}
