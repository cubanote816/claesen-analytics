<?php

namespace Modules\Employee\App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class EmployeeRankingResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'employee_id'    => $this['employee_id'],
            'total_hours'    => $this['total_hours'],
            'productivity'   => $this['productivity'],
            'days_worked'    => $this['days_worked'],
            'revenue'        => $this['revenue'],
            'revenue_per_hour' => $this['revenue_per_hour'],
        ];
    }
}
