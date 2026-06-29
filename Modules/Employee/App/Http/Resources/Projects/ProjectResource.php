<?php

namespace Modules\Employee\App\Http\Resources\Projects;

use Illuminate\Http\Resources\Json\JsonResource;

class ProjectResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'            => $this->id,
            'name'          => $this->name,
            'descr'         => $this->descr,
            'date_start'    => $this->date_start ? $this->date_start->toISOString() : null,
            'date_end'      => $this->date_end ? $this->date_end->toISOString() : null,
            'state'         => $this->state,
            'contract_price'=> $this->contract_price,
            'total_invoiced'=> $this->when(isset($this->total_invoiced), $this->total_invoiced, 0),
            'total_paid'    => $this->when(isset($this->total_paid), $this->total_paid, 0),
            'total_pending' => $this->when(isset($this->total_pending), $this->total_pending, 0),
        ];
    }
}
