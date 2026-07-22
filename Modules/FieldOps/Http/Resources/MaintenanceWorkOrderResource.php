<?php

declare(strict_types=1);

namespace Modules\FieldOps\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use Modules\FieldOps\Models\ElectricalBoard;
use Modules\FieldOps\Models\Luminaire;
use Modules\FieldOps\Services\MaintenanceEquipmentContextService;

class MaintenanceWorkOrderResource extends JsonResource
{
    public function toArray($request): array
    {
        $equipment = $this->relationLoaded('maintainable') ? $this->maintainable : null;
        $context = app(MaintenanceEquipmentContextService::class);

        return [
            'id' => $this->id,
            'status' => $this->status->value,
            'priority' => $this->priority,
            'source' => $this->source,
            'maintainable_type' => $this->maintainable_type,
            'maintainable_id' => $this->maintainable_id,
            'equipment' => $equipment ? [
                'kind' => match (true) {
                    $equipment instanceof Luminaire => 'luminaire',
                    $equipment instanceof ElectricalBoard => 'electrical_board',
                    default => 'equipment',
                },
                'id' => (string) $equipment->getKey(),
                'label' => $context->equipmentLabel($equipment),
                'site_label' => $context->siteLabel($equipment),
                'image_url' => $this->equipmentImageUrl($equipment),
                'serial_number' => $equipment instanceof Luminaire ? $equipment->serial_number : null,
                'frame_position' => $equipment instanceof Luminaire ? $equipment->frame_position : null,
            ] : null,
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
            'assigned_by' => $this->whenLoaded('assignedBy', fn () => $this->assignedBy ? [
                'id' => $this->assignedBy->id,
                'name' => $this->assignedBy->name,
            ] : null),
            'assigned_at' => $this->assigned_at?->toIso8601String(),
            'scheduled_for' => $this->scheduled_for?->toIso8601String(),
            'due_at' => $this->due_at?->toIso8601String(),
            'problem_description' => $this->problem_description,
            'instructions' => $this->instructions,
            'started_at' => $this->started_at?->toIso8601String(),
            'submitted_at' => $this->submitted_at?->toIso8601String(),
            'returned_at' => $this->returned_at?->toIso8601String(),
            'returned_by' => $this->whenLoaded('returnedBy', fn () => $this->returnedBy ? [
                'id' => $this->returnedBy->id,
                'name' => $this->returnedBy->name,
            ] : null),
            'return_reason' => $this->return_reason,
            'completed_at' => $this->completed_at?->toIso8601String(),
            'completion_details' => $this->completion_details,
            'root_cause' => $this->root_cause,
            'solution_applied' => $this->solution_applied,
            'completion_notes' => $this->completion_notes,
            'validated_at' => $this->validated_at?->toIso8601String(),
            'override_reason' => $this->override_reason,
            'events' => $this->whenLoaded('events', fn () => $this->events->map(fn ($event): array => [
                'id' => $event->id,
                'type' => $event->event_type->value,
                'actor' => $event->actor ? [
                    'id' => $event->actor->id,
                    'name' => $event->actor->name,
                ] : null,
                'from_status' => $event->from_status,
                'to_status' => $event->to_status,
                'from_assigned_employee_id' => $event->from_assigned_employee_id,
                'to_assigned_employee_id' => $event->to_assigned_employee_id,
                'data' => $event->data,
                'occurred_at' => $event->occurred_at?->toIso8601String(),
            ])->values()),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }

    private function equipmentImageUrl(object $equipment): ?string
    {
        if (! $equipment instanceof Luminaire) {
            return null;
        }

        $image = $equipment->luminaireType?->image;

        if (! $image) {
            return asset('assets/luminaire-subgroups/image_placeholder.png');
        }

        return str_starts_with($image, 'http') ? $image : asset(ltrim($image, '/'));
    }
}
