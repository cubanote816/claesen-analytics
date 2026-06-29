<?php

namespace Modules\Employee\App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class EmployeeRankingItemResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'ranking'             => (int) $this['ranking'],
            'employee_id'         => (string) $this['employee_id'],
            'employee_name'       => $this['employee_name'],
            'employee_avatar'     => $this['employee_avatar'],
            'department'          => $this['department'],
            'total_hours'         => (float) $this['total_hours'],
            'average_daily_hours' => (float) $this['average_daily_hours'],
            'days_worked'         => (int) $this['days_worked'],
            'attendance_rate'     => (float) $this['attendance_rate'],
            'projects_count'      => (int) $this['projects_count'],
            'total_revenue'       => (float) $this['total_revenue'],
            'revenue_per_hour'    => (float) $this['revenue_per_hour'],
        ];
    }
}
