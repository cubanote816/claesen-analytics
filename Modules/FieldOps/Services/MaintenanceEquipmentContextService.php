<?php

declare(strict_types=1);

namespace Modules\FieldOps\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;
use Modules\FieldOps\Models\ElectricalBoard;
use Modules\FieldOps\Models\Luminaire;

class MaintenanceEquipmentContextService
{
    public function resolve(string $type, int $id): array
    {
        $equipment = match ($type) {
            Luminaire::class => Luminaire::query()->with([
                'luminaireType',
                'position',
                'luminaireFrame.structures.terrains.complex.client',
            ])->findOrFail($id),
            ElectricalBoard::class => ElectricalBoard::query()->with([
                'electricalBoardType',
                'complexes.client',
                'terrains.complex.client',
                'structures.terrains.complex.client',
            ])->findOrFail($id),
            default => throw ValidationException::withMessages([
                'maintainable_type' => __('fieldops::resource.work_orders.validation.invalid_equipment'),
            ]),
        };

        return [
            'equipment' => $equipment,
            'client_id' => $this->resolveClientId($equipment),
            'luminaire_position_id' => $equipment instanceof Luminaire ? $equipment->luminaire_position_id : null,
            'label' => $this->equipmentLabel($equipment),
            'site_label' => $this->siteLabel($equipment),
        ];
    }

    public function siteLabel(Model $equipment): ?string
    {
        $names = $equipment instanceof Luminaire
            ? $equipment->luminaireFrame?->structures
                ?->flatMap(fn ($structure) => $structure->terrains->pluck('complex.name'))
            : $equipment->complexes->pluck('name')
                ->merge($equipment->terrains->pluck('complex.name'))
                ->merge($equipment->structures->flatMap(fn ($structure) => $structure->terrains->pluck('complex.name')));

        $unique = collect($names)->filter()->unique()->values();

        return $unique->isEmpty() ? null : $unique->join(', ');
    }

    public function equipmentLabel(Model $equipment): string
    {
        if ($equipment instanceof Luminaire) {
            $product = $equipment->luminaireType?->product_family ?: $equipment->luminaireType?->name;

            return collect([$product, $equipment->serial_number, '#'.$equipment->frame_position])->filter()->join(' · ');
        }

        if ($equipment instanceof ElectricalBoard) {
            $type = $equipment->electricalBoardType?->getTranslation('name', app()->getLocale(), false)
                ?: $equipment->electricalBoardType?->getTranslation('name', 'nl', false);

            return collect([$type ?: __('fieldops::resource.electrical_boards.model_label').' #'.$equipment->id, $equipment->location_description])->filter()->join(' · ');
        }

        return '#'.$equipment->getKey();
    }

    private function resolveClientId(Model $equipment): int
    {
        $clientIds = $equipment instanceof Luminaire
            ? $equipment->luminaireFrame?->structures
                ?->flatMap(fn ($structure) => $structure->terrains->pluck('complex.client_id'))
            : $equipment->complexes->pluck('client_id')
                ->merge($equipment->terrains->pluck('complex.client_id'))
                ->merge($equipment->structures->flatMap(fn ($structure) => $structure->terrains->pluck('complex.client_id')));

        $unique = collect($clientIds)->filter()->unique()->values();

        if ($unique->isEmpty()) {
            throw ValidationException::withMessages([
                'maintainable_id' => __('fieldops::resource.work_orders.validation.missing_client'),
            ]);
        }

        if ($unique->count() > 1) {
            throw ValidationException::withMessages([
                'maintainable_id' => __('fieldops::resource.work_orders.validation.multiple_clients'),
            ]);
        }

        return (int) $unique->first();
    }
}
