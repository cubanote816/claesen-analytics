<?php

declare(strict_types=1);

namespace Modules\FieldOps\Http\Controllers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\FieldOps\Http\Resources\MaintenanceRecordResource;
use Modules\FieldOps\Http\Resources\MaintenanceTypeResource;
use Modules\FieldOps\Models\ElectricalBoard;
use Modules\FieldOps\Models\FoMaintenanceRecord;
use Modules\FieldOps\Models\FoMaintenanceType;
use Modules\FieldOps\Models\Luminaire;
use Modules\FieldOps\Services\FieldOpsTenantService;

class MaintenanceRecordController extends Controller
{
    private const RELATIONS = ['maintainable', 'maintenanceType', 'employee', 'client', 'createdBy'];

    public function __construct(private readonly FieldOpsTenantService $tenants) {}

    // ── catalog ───────────────────────────────────────────────────────────────

    public function types(): \Illuminate\Http\JsonResponse
    {
        $types = FoMaintenanceType::orderBy('id')->get();

        return response()->json([
            'success' => true,
            'data' => MaintenanceTypeResource::collection($types),
        ]);
    }

    // ── read-only history per equipment ─────────────────────────────────────

    public function indexForLuminaire(Luminaire $luminaire): \Illuminate\Http\JsonResponse
    {
        if ($luminaire->luminaire_position_id !== null) {
            $records = FoMaintenanceRecord::query()
                ->where('luminaire_position_id', $luminaire->luminaire_position_id)
                ->with(self::RELATIONS)
                ->orderByDesc('maintenance_at')
                ->get();

            return response()->json([
                'success' => true,
                'data' => MaintenanceRecordResource::collection($records),
            ]);
        }

        return $this->indexForMaintainable($luminaire);
    }

    public function indexForElectricalBoard(ElectricalBoard $electricalBoard): \Illuminate\Http\JsonResponse
    {
        return $this->indexForMaintainable($electricalBoard);
    }

    private function indexForMaintainable(Model $maintainable): \Illuminate\Http\JsonResponse
    {
        $records = $maintainable->maintenanceRecords()
            ->with(self::RELATIONS)
            ->orderByDesc('maintenance_at')
            ->get();

        return response()->json([
            'success' => true,
            'data' => MaintenanceRecordResource::collection($records),
        ]);
    }

    // ── single record ─────────────────────────────────────────────────────────

    public function show(FoMaintenanceRecord $maintenanceRecord): \Illuminate\Http\JsonResponse
    {
        $maintenanceRecord->load(self::RELATIONS);

        return response()->json([
            'success' => true,
            'data' => new MaintenanceRecordResource($maintenanceRecord),
        ]);
    }

    // ── stats ─────────────────────────────────────────────────────────────────

    // CLA-497: these 3 aggregate/list endpoints have no route-bound model, so
    // EnforceFieldOpsTenantAccess never touches them (its authorization loop only
    // runs for a parameter that is a bound Eloquent instance) — the tenant scope has
    // to be applied here, in the query, before the collection is materialized.
    public function correctiveStats(Request $request): \Illuminate\Http\JsonResponse
    {
        $query = FoMaintenanceRecord::corrective();
        $this->tenants->scopeForUser($query, $request->user(), FoMaintenanceRecord::class);
        $corrective = $query->get();

        return response()->json([
            'success' => true,
            'data' => [
                'total_corrective' => $corrective->count(),
                'emergency_count' => $corrective->where('is_emergency', true)->count(),
                'avg_resolution_time' => $corrective->whereNotNull('problem_reported_at')
                    ->whereNotNull('problem_solved_at')
                    ->avg(fn ($record) => $record->resolution_time_hours),
                'total_downtime' => $corrective->sum('downtime_hours'),
                'unresolved_problems' => $corrective->whereNotNull('problem_reported_at')
                    ->whereNull('problem_solved_at')
                    ->count(),
            ],
        ]);
    }

    // ── client-reported ──────────────────────────────────────────────────────

    public function pendingClientReported(Request $request): \Illuminate\Http\JsonResponse
    {
        $query = FoMaintenanceRecord::pendingClientReported()
            ->with(self::RELATIONS)
            ->orderByDesc('problem_reported_at');
        $this->tenants->scopeForUser($query, $request->user(), FoMaintenanceRecord::class);
        $records = $query->get();

        return response()->json([
            'success' => true,
            'data' => MaintenanceRecordResource::collection($records),
        ]);
    }

    public function clientReportedStatistics(Request $request): \Illuminate\Http\JsonResponse
    {
        $query = FoMaintenanceRecord::clientReported();
        $this->tenants->scopeForUser($query, $request->user(), FoMaintenanceRecord::class);
        $records = $query->get();
        $resolved = $records->whereNotNull('problem_solved_at');

        return response()->json([
            'success' => true,
            'data' => [
                'total_reported' => $records->count(),
                'pending_count' => $records->count() - $resolved->count(),
                'resolved_count' => $resolved->count(),
                'resolution_percentage' => $records->count() > 0
                    ? round(($resolved->count() / $records->count()) * 100, 2)
                    : 0,
                'avg_resolution_time_hours' => $resolved->avg(fn ($record) => $record->resolution_time_hours) ?? 0,
                'by_equipment_type' => $records->groupBy('maintainable_type')->map->count(),
                'by_client' => $records->groupBy('client_id')->map->count(),
                'by_priority' => $records->groupBy('priority')->map->count(),
            ],
        ]);
    }
}
