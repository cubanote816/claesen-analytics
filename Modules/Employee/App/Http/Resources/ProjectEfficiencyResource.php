<?php

namespace Modules\Employee\App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ProjectEfficiencyResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'progress_percentage'       => (float) $this['progress_percentage'],
            'hours_variance'            => (float) $this['hours_variance'],
            'cost_performance_index'    => (float) $this['cost_performance_index'],
            'revenue_per_hour'          => (float) $this['revenue_per_hour'],
            'total_employees_involved'  => (int) $this['total_employees_involved'],
            'project_duration'          => [
                'planned'            => (int) $this['project_duration']['planned'],
                'extension_max'      => (int) $this['project_duration']['extension_max'],
                'start_date'         => $this['project_duration']['start_date'],
                'planned_start_date' => $this['project_duration']['planned_start_date'],
                'end_date'           => $this['project_duration']['end_date'],
            ],
        ];
    }
}
