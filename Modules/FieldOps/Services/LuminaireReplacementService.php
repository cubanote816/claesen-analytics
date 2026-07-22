<?php

declare(strict_types=1);

namespace Modules\FieldOps\Services;

use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\FieldOps\Models\FoMaintenanceRecord;
use Modules\FieldOps\Models\FoMaintenanceType;
use Modules\FieldOps\Models\Luminaire;
use Modules\FieldOps\Models\LuminairePosition;

class LuminaireReplacementService
{
    /**
     * @param  array<string, mixed>  $data
     * @return array{previous: Luminaire, current: Luminaire, maintenance: FoMaintenanceRecord}
     */
    public function replace(Luminaire $luminaire, array $data, ?int $userId): array
    {
        try {
            return DB::transaction(function () use ($luminaire, $data, $userId): array {
                /** @var Luminaire $previous */
                $previous = Luminaire::query()->lockForUpdate()->findOrFail($luminaire->id);

                if ($previous->removed_at !== null || $previous->active_position_id === null) {
                    throw ValidationException::withMessages([
                        'luminaire' => __('fieldops::resource.luminaires.replacement.already_replaced'),
                    ]);
                }

                /** @var LuminairePosition $position */
                $position = LuminairePosition::query()->lockForUpdate()->findOrFail($previous->luminaire_position_id);
                $expectedVersion = (int) $data['position_version'];

                if ($expectedVersion !== (int) $position->position_version) {
                    throw ValidationException::withMessages([
                        'position_version' => __('fieldops::resource.luminaires.position_conflict'),
                    ]);
                }

                $serialNumber = trim((string) $data['serial_number']);

                if (Luminaire::withTrashed()->where('serial_number', $serialNumber)->exists()) {
                    throw ValidationException::withMessages([
                        'serial_number' => __('fieldops::resource.luminaires.replacement.serial_taken'),
                    ]);
                }

                $maintenanceAt = isset($data['maintenance_at'])
                    ? Carbon::parse($data['maintenance_at'])
                    : now();

                $previous->forceFill([
                    'active_position_id' => null,
                    'removed_at' => $maintenanceAt,
                    'removal_reason' => $data['replacement_reason'],
                ])->save();

                $current = Luminaire::create([
                    'created_by_user_id' => $userId,
                    'luminaire_type_id' => $data['luminaire_type_id'],
                    'luminaire_subgroup_id' => $data['luminaire_subgroup_id'],
                    'luminaire_frame_id' => $position->luminaire_frame_id,
                    'luminaire_position_id' => $position->id,
                    'active_position_id' => $position->id,
                    'frame_position' => $position->frame_position,
                    'serial_number' => $serialNumber,
                    'frame_x' => $position->frame_x,
                    'frame_y' => $position->frame_y,
                    'scale_x' => $position->scale_x,
                    'scale_y' => $position->scale_y,
                    'position_version' => $position->position_version,
                    'position_source' => $position->position_source,
                    'position_verified_by_user_id' => $position->position_verified_by_user_id,
                    'position_verified_at' => $position->position_verified_at,
                    'installed_at' => $maintenanceAt,
                ]);

                $previous->forceFill(['replaced_by_luminaire_id' => $current->id])->save();

                $replacementType = FoMaintenanceType::query()
                    ->where('code', FoMaintenanceType::CODE_REPLACEMENT)
                    ->firstOrFail();

                $maintenance = FoMaintenanceRecord::create([
                    'created_by_user_id' => $userId,
                    'fo_maintenance_type_id' => $replacementType->id,
                    'maintainable_type' => Luminaire::class,
                    'maintainable_id' => $previous->id,
                    'luminaire_position_id' => $position->id,
                    'replacement_from_luminaire_id' => $previous->id,
                    'replacement_to_luminaire_id' => $current->id,
                    'replacement_reason' => $data['replacement_reason'],
                    'employee_id' => $data['employee_id'] ?? null,
                    'maintenance_at' => $maintenanceAt,
                    'details' => [
                        'replacement' => true,
                        'previous_serial_number' => $previous->serial_number,
                        'new_serial_number' => $current->serial_number,
                    ],
                    'notes' => $data['notes'] ?? null,
                    'problem_description' => $data['replacement_reason'],
                    'root_cause' => $data['root_cause'] ?? null,
                    'solution_applied' => $data['solution_applied'] ?? null,
                    'problem_reported_at' => $maintenanceAt,
                    'problem_solved_at' => $maintenanceAt,
                    'downtime_hours' => 0,
                ]);

                return [
                    'previous' => $previous->fresh(['luminaireType', 'subgroup', 'position']),
                    'current' => $current->fresh(['luminaireType', 'subgroup', 'position', 'createdBy']),
                    'maintenance' => $maintenance->fresh(['maintenanceType', 'employee', 'createdBy']),
                ];
            });
        } catch (UniqueConstraintViolationException $exception) {
            if (! str_contains($exception->getMessage(), 'fo_luminaires_serial_number_unique')) {
                throw $exception;
            }

            throw ValidationException::withMessages([
                'serial_number' => __('fieldops::resource.luminaires.replacement.serial_taken'),
            ]);
        }
    }
}
