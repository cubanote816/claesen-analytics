<?php

declare(strict_types=1);

namespace Modules\Knx\Http\Resources;

use Illuminate\Http\Request;
use Modules\Knx\Models\KnxPlanDevice;

/**
 * One KNX device of a plan page (provisional contract §4). `id` and `analysisId` are strings.
 *
 * @property KnxPlanDevice $resource
 */
class PlanDeviceResource extends KnxResource
{
    public function toArray(Request $request): array
    {
        $device = $this->resource;

        return [
            'id' => (string) $device->getKey(),
            'provisionalId' => $device->provisional_id,
            'type' => $device->type,
            'nx' => $device->nx,
            'ny' => $device->ny,
            'bbox' => $device->bbox,
            'rotationDeg' => $device->rotation_deg,
            'symbolId' => $device->symbol_id,
            'label' => $device->label,
            'status' => $device->status,
            'doubts' => $device->doubts ?? [],
            'analysisId' => $device->analysis_id === null ? null : (string) $device->analysis_id,
            'updatedAt' => $device->updated_at?->toIso8601String(),
        ];
    }
}
