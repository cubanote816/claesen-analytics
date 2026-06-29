<?php

namespace Modules\Employee\App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class WeeklyStatsResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'week_start'     => $this->weekStart,
            'week_end'       => $this->weekEnd,
            'week_number'    => $this->weekNumber,
            'hours'          => round($this->hours, 2),
            'productivity'   => round($this->productivity, 2),
            'completed_tasks'=> $this->completedTasks,
            'distance'       => round($this->distance, 2),
            'details'        => [
                'approved_hours' => round($this->details['approved_hours'], 2),
                'total_cost'     => round($this->details['total_cost'], 2),
                'total_sales'    => round($this->details['total_sales'], 2),
                'days_worked'    => $this->details['days_worked'],
            ],
        ];
    }
}
