<?php

declare(strict_types=1);

namespace Modules\FieldOps\Services;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Modules\Core\Models\User;
use Modules\FieldOps\Enums\MaintenanceWorkOrderStatus;
use Modules\FieldOps\Models\ElectricalBoard;
use Modules\FieldOps\Models\Luminaire;

/**
 * Conteos por complejo para la UI (lista y encabezados), calculados por lote con
 * consultas agrupadas — nunca una subconsulta por fila.
 *
 * Solo cuenta bajo los complejos que el caller ya pudo ver (los IDs vienen del query
 * con scope de tenant); no aplica scope de tenant por su cuenta. Las work orders
 * abiertas sí replican la regla de visibilidad de MaintenanceWorkOrderController::assigned():
 * admin/super_admin ven todas, el resto solo las asignadas a su empleado.
 */
class ComplexCountsService
{
    /**
     * @param  array<int, int>  $complexIds
     * @return array<int, array{terrains: int, structures: int, luminaires: int, open_work_orders: int}>
     */
    public function forComplexes(array $complexIds, User $user): array
    {
        $counts = [];
        foreach ($complexIds as $id) {
            $counts[$id] = ['terrains' => 0, 'structures' => 0, 'luminaires' => 0, 'open_work_orders' => 0];
        }

        if ($complexIds === []) {
            return $counts;
        }

        foreach ($this->terrains($complexIds) as $id => $n) {
            $counts[$id]['terrains'] = (int) $n;
        }
        foreach ($this->structures($complexIds) as $id => $n) {
            $counts[$id]['structures'] = (int) $n;
        }
        foreach ($this->luminaires($complexIds) as $id => $n) {
            $counts[$id]['luminaires'] = (int) $n;
        }
        foreach ($this->openWorkOrders($complexIds, $user) as $id => $n) {
            $counts[$id]['open_work_orders'] = (int) $n;
        }

        return $counts;
    }

    private function terrains(array $ids)
    {
        return DB::table('fo_terrains')
            ->whereIn('complex_id', $ids)
            ->whereNull('deleted_at')
            ->groupBy('complex_id')
            ->selectRaw('complex_id, count(*) as c')
            ->pluck('c', 'complex_id');
    }

    private function structures(array $ids)
    {
        return DB::table('fo_structure_terrain as st')
            ->join('fo_terrains as t', 't.id', '=', 'st.terrain_id')
            ->join('fo_structures as s', 's.id', '=', 'st.structure_id')
            ->whereIn('t.complex_id', $ids)
            ->whereNull('t.deleted_at')
            ->whereNull('s.deleted_at')
            ->groupBy('t.complex_id')
            ->selectRaw('t.complex_id as complex_id, count(distinct st.structure_id) as c')
            ->pluck('c', 'complex_id');
    }

    private function luminaires(array $ids)
    {
        return $this->luminaireToComplex(DB::table('fo_luminaires as l'), $ids)
            ->whereNull('l.deleted_at')
            ->whereNull('l.removed_at')
            ->whereNotNull('l.active_position_id')
            ->groupBy('t.complex_id')
            ->selectRaw('t.complex_id as complex_id, count(distinct l.id) as c')
            ->pluck('c', 'complex_id');
    }

    /** Une fo_luminaires (alias l) con fo_terrains (alias t) por frame → estructura → terreno. */
    private function luminaireToComplex(Builder $query, array $ids): Builder
    {
        return $query
            ->join('fo_luminaire_frames as f', 'f.id', '=', 'l.luminaire_frame_id')
            ->join('fo_luminaire_frame_structure as fs', 'fs.luminaire_frame_id', '=', 'f.id')
            ->join('fo_structures as s', 's.id', '=', 'fs.structure_id')
            ->join('fo_structure_terrain as st', 'st.structure_id', '=', 's.id')
            ->join('fo_terrains as t', 't.id', '=', 'st.terrain_id')
            ->whereIn('t.complex_id', $ids)
            ->whereNull('f.deleted_at')
            ->whereNull('s.deleted_at')
            ->whereNull('t.deleted_at');
    }

    private function openWorkOrders(array $ids, User $user)
    {
        $openWorkOrders = function (Builder $q) use ($user): Builder {
            $q->whereNull('wo.deleted_at')
                ->whereNotIn('wo.status', [
                    MaintenanceWorkOrderStatus::COMPLETED->value,
                    MaintenanceWorkOrderStatus::CANCELLED->value,
                ]);

            if (! $user->hasAnyRole(['super_admin', 'admin'])) {
                $q->where('wo.assigned_employee_id', $user->employee_id ?: '__unlinked__');
            }

            return $q;
        };

        // Par (complex_id, work_order_id) por cada ruta posible hasta el complejo.
        $viaLuminaire = $openWorkOrders(DB::table('fo_maintenance_work_orders as wo'))
            ->where('wo.maintainable_type', Luminaire::class)
            ->join('fo_luminaires as l', 'l.id', '=', 'wo.maintainable_id')
            ->whereNull('l.deleted_at')
            ->join('fo_luminaire_frames as f', 'f.id', '=', 'l.luminaire_frame_id')
            ->join('fo_luminaire_frame_structure as fs', 'fs.luminaire_frame_id', '=', 'f.id')
            ->join('fo_structures as s', 's.id', '=', 'fs.structure_id')
            ->join('fo_structure_terrain as st', 'st.structure_id', '=', 's.id')
            ->join('fo_terrains as t', 't.id', '=', 'st.terrain_id')
            ->whereIn('t.complex_id', $ids)
            ->whereNull('f.deleted_at')->whereNull('s.deleted_at')->whereNull('t.deleted_at')
            ->select('t.complex_id as complex_id', 'wo.id as work_order_id');

        $boardBase = fn () => $openWorkOrders(DB::table('fo_maintenance_work_orders as wo'))
            ->where('wo.maintainable_type', ElectricalBoard::class)
            ->join('fo_electrical_boards as eb', 'eb.id', '=', 'wo.maintainable_id')
            ->whereNull('eb.deleted_at');

        $viaBoardComplex = $boardBase()
            ->join('fo_complex_electrical_board as ceb', 'ceb.electrical_board_id', '=', 'eb.id')
            ->whereIn('ceb.complex_id', $ids)
            ->select('ceb.complex_id as complex_id', 'wo.id as work_order_id');

        $viaBoardTerrain = $boardBase()
            ->join('fo_electrical_board_terrain as ebt', 'ebt.electrical_board_id', '=', 'eb.id')
            ->join('fo_terrains as t', 't.id', '=', 'ebt.terrain_id')
            ->whereNull('t.deleted_at')
            ->whereIn('t.complex_id', $ids)
            ->select('t.complex_id as complex_id', 'wo.id as work_order_id');

        $viaBoardStructure = $boardBase()
            ->join('fo_electrical_board_structure as ebs', 'ebs.electrical_board_id', '=', 'eb.id')
            ->join('fo_structures as s', 's.id', '=', 'ebs.structure_id')
            ->whereNull('s.deleted_at')
            ->join('fo_structure_terrain as st', 'st.structure_id', '=', 's.id')
            ->join('fo_terrains as t', 't.id', '=', 'st.terrain_id')
            ->whereNull('t.deleted_at')
            ->whereIn('t.complex_id', $ids)
            ->select('t.complex_id as complex_id', 'wo.id as work_order_id');

        $pairs = $viaLuminaire->union($viaBoardComplex)->union($viaBoardTerrain)->union($viaBoardStructure);

        return DB::query()
            ->fromSub($pairs, 'pairs')
            ->groupBy('pairs.complex_id')
            ->selectRaw('pairs.complex_id as complex_id, count(distinct pairs.work_order_id) as c')
            ->pluck('c', 'complex_id');
    }
}
