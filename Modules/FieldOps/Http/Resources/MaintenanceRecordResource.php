<?php

namespace Modules\FieldOps\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use Modules\FieldOps\Services\FieldOpsTenantService;

class MaintenanceRecordResource extends JsonResource
{
    public function toArray($request): array
    {
        // CLA-561: the per-asset history endpoints (.../maintenance-records) are
        // already reachable by a client (tenant-scoped GET on the asset itself),
        // but this resource used to hand back the same internal payload to every
        // actor. employee/created_by identify internal staff, and notes/root_cause
        // are internal-only free text (distinct from details/problem_description/
        // solution_applied, which document the factual work done on the client's
        // own equipment and stay visible) — redact those four for a client actor,
        // same pattern MaintenanceRequestResource already uses for messages/
        // attachments. Internal consumers (admin/technician/Filament) are unaffected.
        $isClient = app(FieldOpsTenantService::class)->isClientUser($request->user());

        return [
            'id' => $this->id,
            'maintainable_type' => $this->maintainable_type,
            'maintainable_id' => $this->maintainable_id,
            'luminaire_position_id' => $this->luminaire_position_id,
            'maintenance_type' => $this->whenLoaded('maintenanceType', fn () => [
                'id' => $this->maintenanceType->id,
                'name' => $this->maintenanceType->getTranslations('name'),
                'code' => $this->maintenanceType->code,
            ]),
            'employee' => $isClient ? null : $this->whenLoaded('employee', fn () => $this->employee ? [
                'id' => $this->employee->id,
                'name' => $this->employee->name ?? null,
            ] : null),
            'client' => $this->whenLoaded('client', fn () => $this->client ? [
                'id' => $this->client->id,
                'name' => $this->client->name,
            ] : null),
            'created_by' => $isClient ? null : $this->whenLoaded('createdBy', fn () => $this->createdBy ? [
                'id' => $this->createdBy->id,
                'name' => $this->createdBy->name,
            ] : null),
            'maintenance_at' => $this->maintenance_at?->toIso8601String(),
            'details' => $this->details,
            'notes' => $isClient ? null : $this->notes,
            'problem_description' => $this->problem_description,
            'root_cause' => $isClient ? null : $this->root_cause,
            'solution_applied' => $this->solution_applied,
            'is_emergency' => $this->is_emergency,
            'problem_reported_at' => $this->problem_reported_at?->toIso8601String(),
            'problem_solved_at' => $this->problem_solved_at?->toIso8601String(),
            'downtime_hours' => $this->downtime_hours !== null ? (float) $this->downtime_hours : null,
            'resolution_time_hours' => $this->resolution_time_hours,
            'problem_status' => $this->problem_status,
            'reported_by_client' => $this->reported_by_client,
            'priority' => $this->priority,
            'contact_person' => $this->contact_person,
            'contact_phone' => $this->contact_phone,
            'location_details' => $this->location_details,
            'replacement' => $this->replacement_from_luminaire_id ? [
                'from_luminaire_id' => $this->replacement_from_luminaire_id,
                'to_luminaire_id' => $this->replacement_to_luminaire_id,
                'reason' => $this->replacement_reason,
            ] : null,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
