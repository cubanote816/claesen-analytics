<?php

namespace Modules\Employee\App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class MonthlyDataResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'month'          => $this['month'],
            'month_number'   => (int) $this['month_number'],
            'total_hours'    => (float) $this['total_hours'],
            'employee_count' => (int) $this['employee_count'],
            'project_count'  => (int) $this['project_count'],
        ];
    }
}
