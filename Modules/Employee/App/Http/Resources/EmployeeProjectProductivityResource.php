<?php

namespace Modules\Employee\App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class EmployeeProjectProductivityResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'date'              => $this['date'],
            'hours'             => (float) $this['hours'],
            'productivity_rate' => (float) $this['productivity_rate'],
            'revenue'           => (float) $this['revenue'],
            'tasks_completed'   => (int) $this['tasks_completed'],
            'revenue_per_hour'  => (float) $this['revenue_per_hour'],
        ];
    }
}
