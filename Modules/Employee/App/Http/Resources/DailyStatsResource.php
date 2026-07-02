<?php

namespace Modules\Employee\App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class DailyStatsResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'date'           => $this->date,
            'hours'          => round($this->hours, 2),
            'productivity'   => round($this->productivity, 2),
            'completedTasks' => $this->completedTasks,
            'distance'       => round($this->distance, 2),
            'details'        => [
                'startTime' => $this->details['startTime'],
                'endTime'   => $this->details['endTime'],
                'breaks'    => round($this->details['breaks'], 2),
            ],
        ];
    }
}
