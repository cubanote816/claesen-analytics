<?php

namespace Modules\Employee\App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class DashboardDataResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'yearly_hours' => new YearlyHoursResource($this['yearly_hours']),
            'ranking_data' => EmployeeRankingResource::collection($this['ranking_data']),
        ];
    }
}
