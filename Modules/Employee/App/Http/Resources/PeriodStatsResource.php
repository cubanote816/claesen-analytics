<?php

namespace Modules\Employee\App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class PeriodStatsResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'period_type' => $this->periodType,
            'start_date'  => $this->startDate,
            'end_date'    => $this->endDate,
            'summary'     => [
                'total_hours'          => round($this->summary['total_hours'], 2),
                'average_productivity' => round($this->summary['average_productivity'], 2),
                'total_tasks'          => $this->summary['total_tasks'],
                'total_distance'       => round($this->summary['total_distance'], 2),
                'total_cost'           => round($this->summary['total_cost'], 2),
                'total_sales'          => round($this->summary['total_sales'], 2),
                'total_days'           => $this->summary['total_days'],
            ],
            'daily_stats' => DailyStatsResource::collection($this->dailyStats),
        ];
    }
}
