<?php

namespace Modules\Employee\App\Http\Resources\Projects;

use Illuminate\Http\Resources\Json\JsonResource;

class InvoiceResource extends JsonResource
{
    public function toArray($request): array
    {
        if ($this->resource === null) {
            return ['id' => '', 'date' => null, 'total_price' => 0, 'is_pending' => true];
        }

        return [
            'id'          => $this->id ?? '',
            'date'        => $this->date_expiration ?? null,
            'total_price' => $this->total_price ?? 0,
            'is_pending'  => $this->is_pending ?? true,
        ];
    }
}
