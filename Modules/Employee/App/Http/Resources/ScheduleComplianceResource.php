<?php

namespace Modules\Employee\App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ScheduleComplianceResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'date'             => $this['date'],
            'scheduled_hours'  => (float) $this['scheduled_hours'],
            'actual_hours'     => (float) $this['actual_hours'],
            'compliance_rate'  => (float) $this['compliance_rate'],
        ];
    }
}
