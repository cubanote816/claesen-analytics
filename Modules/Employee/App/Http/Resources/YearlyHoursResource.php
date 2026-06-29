<?php

namespace Modules\Employee\App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class YearlyHoursResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'year'    => $this['year'],
            'summary' => [
                'total_hours'    => $this['total_hours'],
                'approved_hours' => $this['approved_hours'],
                'working_days'   => $this['working_days'],
                'days_worked'    => $this['days_worked'],
                'attendance_rate'=> $this['attendance_rate'],
                'daily_average'  => $this['daily_average'],
                'monthly_average'=> $this['monthly_average'],
            ],
            'financial' => [
                'costs'   => $this['costs'],
                'revenue' => $this['revenue'],
                'profit'  => $this['profit'],
            ],
            'monthly_breakdown' => $this['monthly_data'],
        ];
    }
}
