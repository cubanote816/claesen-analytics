<?php

declare(strict_types=1);

namespace Modules\FieldOps\Http\Controllers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Routing\Controller;
use Modules\FieldOps\Http\Requests\ResolveClientReportedMaintenanceRequest;
use Modules\FieldOps\Http\Requests\StoreClientReportedMaintenanceRequest;
use Modules\FieldOps\Http\Requests\StoreMaintenanceRecordRequest;
use Modules\FieldOps\Http\Requests\UpdateMaintenanceRecordRequest;
use Modules\FieldOps\Http\Resources\MaintenanceRecordResource;
use Modules\FieldOps\Http\Resources\MaintenanceTypeResource;
use Modules\FieldOps\Models\ElectricalBoard;
use Modules\FieldOps\Models\FoMaintenanceRecord;
use Modules\FieldOps\Models\FoMaintenanceType;
use Modules\FieldOps\Models\Luminaire;

class MaintenanceRecordController extends Controller
{
    private const RELATIONS = ['maintainable', 'maintenanceType', 'employee', 'client', 'createdBy'];

    // ── catalog ───────────────────────────────────────────────────────────────

    public function types(): \Illuminate\Http\JsonResponse
    {
        $types = FoMaintenanceType::orderBy('id')->get();

        return response()->json([
            'success' => true,
            'data'    => MaintenanceTypeResource::collection($types),
        ]);
    }

    // ── per-equipment listing/creation ───────────────────────────────────────

    public function indexForLuminaire(Luminaire $luminaire): \Illuminate\Http\JsonResponse
    {
        return $this->indexForMaintainable($luminaire);
    }

    public function indexForElectricalBoard(ElectricalBoard $electricalBoard): \Illuminate\Http\JsonResponse
    {
        return $this->indexForMaintainable($electricalBoard);
    }

    public function storeForLuminaire(StoreMaintenanceRecordRequest $request, Luminaire $luminaire): \Illuminate\Http\JsonResponse
    {
        return $this->storeForMaintainable($request, $luminaire);
    }

    public function storeForElectricalBoard(StoreMaintenanceRecordRequest $request, ElectricalBoard $electricalBoard): \Illuminate\Http\JsonResponse
    {
        return $this->storeForMaintainable($request, $electricalBoard);
    }

    private function indexForMaintainable(Model $maintainable): \Illuminate\Http\JsonResponse
    {
        $records = $maintainable->maintenanceRecords()
            ->with(self::RELATIONS)
            ->orderByDesc('maintenance_at')
            ->get();

        return response()->json([
            'success' => true,
            'data'    => MaintenanceRecordResource::collection($records),
        ]);
    }

    private function storeForMaintainable(StoreMaintenanceRecordRequest $request, Model $maintainable): \Illuminate\Http\JsonResponse
    {
        $data = array_merge($request->validated(), [
            'created_by_user_id' => $request->user()->id,
        ]);

        $record = $maintainable->maintenanceRecords()->create($data);
        $record->load(self::RELATIONS);

        return response()->json([
            'success' => true,
            'data'    => new MaintenanceRecordResource($record),
        ], 201);
    }

    // ── single record ─────────────────────────────────────────────────────────

    public function show(FoMaintenanceRecord $maintenanceRecord): \Illuminate\Http\JsonResponse
    {
        $maintenanceRecord->load(self::RELATIONS);

        return response()->json([
            'success' => true,
            'data'    => new MaintenanceRecordResource($maintenanceRecord),
        ]);
    }

    public function update(UpdateMaintenanceRecordRequest $request, FoMaintenanceRecord $maintenanceRecord): \Illuminate\Http\JsonResponse
    {
        $maintenanceRecord->update($request->validated());
        $maintenanceRecord->load(self::RELATIONS);

        return response()->json([
            'success' => true,
            'data'    => new MaintenanceRecordResource($maintenanceRecord),
        ]);
    }

    public function destroy(FoMaintenanceRecord $maintenanceRecord): \Illuminate\Http\Response
    {
        $maintenanceRecord->delete();

        return response()->noContent();
    }

    // ── stats ─────────────────────────────────────────────────────────────────

    public function correctiveStats(): \Illuminate\Http\JsonResponse
    {
        $corrective = FoMaintenanceRecord::corrective()->get();

        return response()->json([
            'success' => true,
            'data'    => [
                'total_corrective'    => $corrective->count(),
                'emergency_count'     => $corrective->where('is_emergency', true)->count(),
                'avg_resolution_time' => $corrective->whereNotNull('problem_reported_at')
                    ->whereNotNull('problem_solved_at')
                    ->avg(fn ($record) => $record->resolution_time_hours),
                'total_downtime'      => $corrective->sum('downtime_hours'),
                'unresolved_problems' => $corrective->whereNotNull('problem_reported_at')
                    ->whereNull('problem_solved_at')
                    ->count(),
            ],
        ]);
    }

    // ── client-reported ──────────────────────────────────────────────────────

    public function storeClientReported(StoreClientReportedMaintenanceRequest $request): \Illuminate\Http\JsonResponse
    {
        $validated = $request->validated();

        $emergencyType = FoMaintenanceType::where('code', FoMaintenanceType::CODE_EMERGENCY)->first();
        if (!$emergencyType) {
            return response()->json([
                'success' => false,
                'message' => 'No hay un tipo de mantenimiento de emergencia configurado.',
            ], 422);
        }

        $record = FoMaintenanceRecord::create([
            'created_by_user_id'     => $request->user()->id,
            'fo_maintenance_type_id' => $emergencyType->id,
            'maintainable_id'        => $validated['maintainable_id'],
            'maintainable_type'      => $validated['maintainable_type'],
            'client_id'              => $validated['client_id'],
            'reported_by_client'     => true,
            'is_emergency'           => true,
            'priority'               => $validated['priority'] ?? 'high',
            'problem_description'    => $validated['problem_description'],
            'problem_reported_at'    => now(),
            'maintenance_at'         => now(),
            'location_details'       => $validated['location_details'] ?? null,
            'contact_person'         => $validated['contact_person'] ?? null,
            'contact_phone'          => $validated['contact_phone'] ?? null,
        ]);

        $record->load(self::RELATIONS);

        return response()->json([
            'success' => true,
            'data'    => new MaintenanceRecordResource($record),
        ], 201);
    }

    public function pendingClientReported(): \Illuminate\Http\JsonResponse
    {
        $records = FoMaintenanceRecord::pendingClientReported()
            ->with(self::RELATIONS)
            ->orderByDesc('problem_reported_at')
            ->get();

        return response()->json([
            'success' => true,
            'data'    => MaintenanceRecordResource::collection($records),
        ]);
    }

    public function clientReportedStatistics(): \Illuminate\Http\JsonResponse
    {
        $records = FoMaintenanceRecord::clientReported()->get();
        $resolved = $records->whereNotNull('problem_solved_at');

        return response()->json([
            'success' => true,
            'data'    => [
                'total_reported'         => $records->count(),
                'pending_count'          => $records->count() - $resolved->count(),
                'resolved_count'         => $resolved->count(),
                'resolution_percentage'  => $records->count() > 0
                    ? round(($resolved->count() / $records->count()) * 100, 2)
                    : 0,
                'avg_resolution_time_hours' => $resolved->avg(fn ($record) => $record->resolution_time_hours) ?? 0,
                'by_equipment_type'      => $records->groupBy('maintainable_type')->map->count(),
                'by_client'              => $records->groupBy('client_id')->map->count(),
                'by_priority'            => $records->groupBy('priority')->map->count(),
            ],
        ]);
    }

    public function resolveClientReported(ResolveClientReportedMaintenanceRequest $request, FoMaintenanceRecord $maintenanceRecord): \Illuminate\Http\JsonResponse
    {
        if (!$maintenanceRecord->reported_by_client) {
            return response()->json([
                'success' => false,
                'message' => 'Este registro no es un servicio reportado por un cliente.',
            ], 422);
        }

        if ($maintenanceRecord->problem_solved_at) {
            return response()->json([
                'success' => false,
                'message' => 'Este servicio ya ha sido marcado como resuelto.',
            ], 422);
        }

        $validated = $request->validated();
        $solvedAt  = now();

        $maintenanceRecord->update([
            'solution_applied'  => $validated['solution_applied'],
            'employee_id'       => $validated['employee_id'],
            'problem_solved_at' => $solvedAt,
            'downtime_hours'    => $maintenanceRecord->problem_reported_at
                ? $maintenanceRecord->problem_reported_at->diffInHours($solvedAt, true)
                : null,
        ]);

        $maintenanceRecord->load(self::RELATIONS);

        return response()->json([
            'success' => true,
            'data'    => new MaintenanceRecordResource($maintenanceRecord),
        ]);
    }
}
