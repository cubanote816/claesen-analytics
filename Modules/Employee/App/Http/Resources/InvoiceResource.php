<?php

namespace Modules\Employee\App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class InvoiceResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'             => $this->id,
            'total_price'    => $this->total_price,
            'total_paid'     => $this->total_paid,
            'is_pending'     => $this->is_pending,
            'expiration_date'=> $this->date_expiration,
            'active'         => $this->fl_active,
            'pending_amount' => $this->balance,
        ];
    }
}
