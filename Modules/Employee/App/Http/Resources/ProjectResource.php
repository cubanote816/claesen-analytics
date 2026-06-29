<?php

namespace Modules\Employee\App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ProjectResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'                     => $this->id,
            'name'                   => $this->name,
            'description'            => $this->descr,
            'start_date'             => $this->date_start,
            'end_date'               => $this->date_end,
            'active'                 => $this->fl_active,
            'total_pending'          => $this->when(isset($this->total_pending), $this->total_pending, 0),
            'invoices'               => $this->when(isset($this->invoices), InvoiceResource::collection($this->invoices), []),
            'total_unique_employees' => $this->when(isset($this->total_unique_employees), $this->total_unique_employees, 0),
            'unique_employees'       => $this->when(isset($this->unique_employees), EmployeeResource::collection($this->unique_employees), []),
            'employees'              => $this->when(isset($this->employees), EmployeeResource::collection($this->employees), []),
        ];
    }
}
