<?php

declare(strict_types=1);

namespace Modules\FieldOps\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\FieldOps\Models\FoMaintenanceRecord;
use Modules\FieldOps\Models\FoMaintenanceType;
use Modules\FieldOps\Models\Luminaire;
use Modules\FieldOps\Models\LuminairePosition;

class LuminaireRemovalService
{
    /** @return array{luminaire: Luminaire, maintenance: FoMaintenanceRecord} */
    public function remove(Luminaire $luminaire, array $data, ?int $userId): array
    {
        return DB::transaction(function () use ($luminaire, $data, $userId): array {
            /** @var Luminaire $installation */
            $installation = Luminaire::query()->lockForUpdate()->findOrFail($luminaire->id);

            if ($installation->removed_at !== null || $installation->active_position_id === null) {
                throw ValidationException::withMessages(['luminaire' => __('fieldops::resource.luminaires.removal.already_removed')]);
            }

            /** @var LuminairePosition $position */
            $position = LuminairePosition::query()->lockForUpdate()->findOrFail($installation->luminaire_position_id);

            if ((int) $data['position_version'] !== (int) $position->position_version) {
                throw ValidationException::withMessages(['position_version' => __('fieldops::resource.luminaires.position_conflict')]);
            }

            $removedAt = isset($data['maintenance_at']) ? Carbon::parse($data['maintenance_at']) : now();
            $reason = trim((string) $data['removal_reason']);

            $installation->forceFill([
                'active_position_id' => null,
                'removed_at' => $removedAt,
                'removal_reason' => $reason,
            ])->save();

            $type = FoMaintenanceType::query()->where('code', FoMaintenanceType::CODE_REMOVAL)->firstOrFail();
            $maintenance = FoMaintenanceRecord::create([
                'created_by_user_id' => $userId,
                'fo_maintenance_type_id' => $type->id,
                'maintainable_type' => Luminaire::class,
                'maintainable_id' => $installation->id,
                'luminaire_position_id' => $position->id,
                'employee_id' => $data['employee_id'] ?? null,
                'maintenance_at' => $removedAt,
                'details' => ['removal' => true, 'position_became_vacant' => true, 'serial_number' => $installation->serial_number],
                'notes' => $data['notes'] ?? null,
                'problem_description' => $reason,
                'root_cause' => $data['root_cause'] ?? null,
                'solution_applied' => __('fieldops::resource.luminaires.removal.solution_applied'),
                'problem_reported_at' => $removedAt,
                'problem_solved_at' => $removedAt,
                'downtime_hours' => 0,
            ]);

            return ['luminaire' => $installation->fresh(['position']), 'maintenance' => $maintenance];
        });
    }
}
