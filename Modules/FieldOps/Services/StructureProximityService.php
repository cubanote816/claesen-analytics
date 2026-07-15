<?php

declare(strict_types=1);

namespace Modules\FieldOps\Services;

use Illuminate\Support\Collection;
use Modules\FieldOps\Models\Structure;
use Modules\FieldOps\Models\Terrain;

class StructureProximityService
{
    /**
     * @param  array<int, int|string>  $terrainIds
     * @return array{structure: Structure, distance_meters: float, radius_meters: int}|null
     */
    public function findNearbyStructure(array $terrainIds, ?float $lat, ?float $lng, int $radiusMeters): ?array
    {
        if ($lat === null || $lng === null) {
            return null;
        }

        $terrainIds = $this->normalizeIds($terrainIds);

        if ($terrainIds->isEmpty()) {
            return null;
        }

        $complexId = Terrain::query()
            ->whereIn('id', $terrainIds)
            ->value('complex_id');

        if ($complexId === null) {
            return null;
        }

        $candidate = Structure::query()
            ->whereNotNull('lat')
            ->whereNotNull('lng')
            ->whereHas('terrains', fn ($query) => $query->where('complex_id', $complexId))
            ->with(['structureType', 'terrains'])
            ->get()
            ->map(function (Structure $structure) use ($lat, $lng): array {
                return [
                    'structure' => $structure,
                    'distance_meters' => $this->distanceMeters(
                        $lat,
                        $lng,
                        (float) $structure->lat,
                        (float) $structure->lng,
                    ),
                ];
            })
            ->sortBy('distance_meters')
            ->first(function (array $match) use ($radiusMeters): bool {
                return $match['distance_meters'] <= $radiusMeters;
            });

        if ($candidate === null) {
            return null;
        }

        return [
            'structure' => $candidate['structure'],
            'distance_meters' => round($candidate['distance_meters'], 1),
            'radius_meters' => $radiusMeters,
        ];
    }

    /**
     * @param  array<int, int|string>  $terrainIds
     */
    protected function normalizeIds(array $terrainIds): Collection
    {
        return collect($terrainIds)
            ->filter(fn ($value) => is_numeric($value))
            ->map(fn ($value) => (int) $value)
            ->unique()
            ->values();
    }

    private function distanceMeters(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadius = 6371000;

        $latDelta = deg2rad($lat2 - $lat1);
        $lngDelta = deg2rad($lng2 - $lng1);

        $a = sin($latDelta / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($lngDelta / 2) ** 2;

        return 2 * $earthRadius * asin(min(1, sqrt($a)));
    }
}
