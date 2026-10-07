<?php

namespace Modules\Knx\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Knx\Http\Resources\ConflictResource;
use Modules\Knx\Http\Resources\ProjectResource;
use Modules\Knx\Http\Resources\TechnicianTodayResource;
use Modules\Knx\Models\KnxConflict;
use Modules\Knx\Services\ConflictService;
use Modules\Knx\Services\DashboardService;

/**
 * El Overzicht (§4.2): un agregado, una llamada.
 */
class DashboardController extends Controller
{
    public function show(Request $request, DashboardService $dashboard, ConflictService $conflicts): JsonResponse
    {
        $summary = $dashboard->summary();

        // The conflicts carry the same `takenAddresses` the Conflictencentrum shows,
        // resolved in two queries per project instead of two per conflict.
        $taken = $conflicts->takenAddressesForList($summary['openConflicts']);

        return response()->json([
            'kpis' => $summary['kpis'],
            'activeProjects' => ProjectResource::list($summary['activeProjects'], $request),
            'openConflicts' => $summary['openConflicts']
                ->map(fn (KnxConflict $conflict): array => (new ConflictResource($conflict, $taken[$conflict->getKey()] ?? []))
                    ->resolve($request))
                ->all(),
            'techniciansToday' => $summary['techniciansToday']
                ->map(fn (array $row): array => (new TechnicianTodayResource($row['technician'], $row['assignment']))
                    ->resolve($request))
                ->all(),
        ]);
    }
}
