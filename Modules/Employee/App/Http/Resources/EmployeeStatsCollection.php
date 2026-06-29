<?php

namespace Modules\Employee\App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class EmployeeStatsCollection extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'success' => true,
            'data'    => match ($this->resource->periodType) {
                'current-week', 'previous-week'   => new PeriodStatsResource($this->resource),
                'current-month', 'previous-month' => new MonthlyPeriodStatsResource($this->resource),
                default => null,
            },
        ];
    }
}
