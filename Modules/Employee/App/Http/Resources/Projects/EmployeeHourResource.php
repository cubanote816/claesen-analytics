<?php

namespace Modules\Employee\App\Http\Resources\Projects;

use Illuminate\Http\Resources\Json\JsonResource;

class EmployeeHourResource extends JsonResource
{
    public function toArray($request): array
    {
        if (is_array($this->resource)) {
            return [
                'id'          => $this->resource['employee_id'] ?? $this->resource['id'],
                'name'        => $this->resource['employee_name'] ?? $this->resource['name'],
                'total_hours' => (float) ($this->resource['total_hours'] ?? 0),
            ];
        }

        return [
            'id'          => $this->employee_id ?? $this->id,
            'name'        => $this->employee_name ?? $this->name,
            'total_hours' => (float) ($this->total_hours ?? 0),
        ];
    }
}
