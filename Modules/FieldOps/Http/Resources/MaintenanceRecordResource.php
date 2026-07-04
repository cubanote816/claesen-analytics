<?php

namespace Modules\FieldOps\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class MaintenanceRecordResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'                   => $this->id,
            'maintainable_type'    => $this->maintainable_type,
            'maintainable_id'      => $this->maintainable_id,
            'maintenance_type'     => $this->whenLoaded('maintenanceType', fn () => [
                'id'   => $this->maintenanceType->id,
                'name' => $this->maintenanceType->getTranslations('name'),
                'code' => $this->maintenanceType->code,
            ]),
            'employee'             => $this->whenLoaded('employee', fn () => $this->employee ? [
                'id'   => $this->employee->id,
                'name' => $this->employee->name ?? null,
            ] : null),
            'client'               => $this->whenLoaded('client', fn () => $this->client ? [
                'id'   => $this->client->id,
                'name' => $this->client->name,
            ] : null),
            'created_by'           => $this->whenLoaded('createdBy', fn () => $this->createdBy ? [
                'id'   => $this->createdBy->id,
                'name' => $this->createdBy->name,
            ] : null),
            'maintenance_at'       => $this->maintenance_at?->toIso8601String(),
            'details'              => $this->details,
            'notes'                => $this->notes,
            'problem_description'  => $this->problem_description,
            'root_cause'           => $this->root_cause,
            'solution_applied'     => $this->solution_applied,
            'is_emergency'         => $this->is_emergency,
            'problem_reported_at'  => $this->problem_reported_at?->toIso8601String(),
            'problem_solved_at'    => $this->problem_solved_at?->toIso8601String(),
            'downtime_hours'       => $this->downtime_hours !== null ? (float) $this->downtime_hours : null,
            'resolution_time_hours' => $this->resolution_time_hours,
            'problem_status'       => $this->problem_status,
            'reported_by_client'   => $this->reported_by_client,
            'priority'             => $this->priority,
            'contact_person'       => $this->contact_person,
            'contact_phone'        => $this->contact_phone,
            'location_details'     => $this->location_details,
        ];
    }
}
