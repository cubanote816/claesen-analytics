<?php

namespace Modules\FieldOps\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class MaintenanceTypeResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'   => $this->id,
            'name' => $this->getTranslations('name'),
            'code' => $this->code,
        ];
    }
}
