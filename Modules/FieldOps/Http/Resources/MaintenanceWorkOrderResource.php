<?php

declare(strict_types=1);

namespace Modules\FieldOps\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class MaintenanceWorkOrderResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status->value,
            'priority' => $this->priority,
            'source' => $this->source,
            'maintainable_type' => $this->maintainable_type,
            'maintainable_id' => $this->maintainable_id,
            'luminaire_position_id' => $this->luminaire_position_id,
            'maintenance_plan_id' => $this->maintenance_plan_id,
            'maintenance_record_id' => $this->maintenance_record_id,
            'maintenance_type' => $this->whenLoaded('maintenanceType', fn () => [
                'id' => $this->maintenanceType->id,
                'code' => $this->maintenanceType->code,
                'name' => $this->maintenanceType->getTranslations('name'),
            ]),
            'client' => $this->whenLoaded('client', fn () => $this->client ? [
                'id' => $this->client->id,
                'name' => $this->client->name,
            ] : null),
            'assigned_employee' => $this->whenLoaded('assignedEmployee', fn () => $this->assignedEmployee ? [
                'id' => $this->assignedEmployee->id,
                'name' => $this->assignedEmployee->name,
            ] : null),
            'scheduled_for' => $this->scheduled_for?->toIso8601String(),
            'due_at' => $this->due_at?->toIso8601String(),
            'problem_description' => $this->problem_description,
            'instructions' => $this->instructions,
            'started_at' => $this->started_at?->toIso8601String(),
            'submitted_at' => $this->submitted_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'completion_details' => $this->completion_details,
            'root_cause' => $this->root_cause,
            'solution_applied' => $this->solution_applied,
            'completion_notes' => $this->completion_notes,
            'validated_at' => $this->validated_at?->toIso8601String(),
            'override_reason' => $this->override_reason,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
