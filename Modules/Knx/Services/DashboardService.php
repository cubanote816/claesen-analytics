<?php

namespace Modules\Knx\Services;

use Illuminate\Support\Collection;
use Modules\Knx\Models\KnxDevice;
use Modules\Knx\Models\KnxEmployee;
use Modules\Knx\Models\KnxPlanningAssignment;
use Modules\Knx\Models\KnxProject;

/**
 * The aggregate behind the Overzicht screen (§4.2). One call, because the screen
 * is the first thing the office opens and it should not fan out into five
 * requests.
 *
 * Every KPI is counted from rows, and two of them differ from the office app's own
 * fixture on purpose:
 *
 *   - `devicesThisWeek` counts apparatuses actually registered since Monday. The
 *     fixture hard-codes 42;
 *   - `techniciansScheduledToday` counts technicians with an assignment *today*,
 *     which depends on the weekday — the fixture's planner is seeded Monday to
 *     Friday, so on a weekend this is legitimately 0.
 */
class DashboardService
{
    /**
     * @return array{
     *     kpis: array<string, int>,
     *     activeProjects: Collection<int, KnxProject>,
     *     openConflicts: Collection<int, \Modules\Knx\Models\KnxConflict>,
     *     techniciansToday: Collection<int, array{technician: KnxEmployee, assignment: KnxPlanningAssignment|null}>
     * }
     */
    public function summary(): array
    {
        $today = now()->toDateString();
        // "This week" starts on Monday, like the planning grid.
        $monday = now()->startOfWeek();

        $activeProjects = KnxProject::query()
            ->with(['client', 'lead'])
            ->where('status', '!=', 'done')
            ->orderBy('id')
            ->get();

        // Already ordered by severity then date, which is the order the dashboard
        // shows its conflict panel in.
        $openConflicts = app(ConflictService::class)->list('active', null, null);

        $technicians = KnxEmployee::query()
            ->field()
            ->active()
            ->orderBy('id')
            ->get();

        $assignments = KnxPlanningAssignment::query()
            ->with('project')
            ->whereDate('date', $today)
            ->whereIn('employee_id', $technicians->pluck('id'))
            ->get()
            ->keyBy('employee_id');

        $techniciansToday = $technicians->map(fn (KnxEmployee $technician): array => [
            'technician' => $technician,
            'assignment' => $assignments->get($technician->getKey()),
        ])->values();

        $scheduled = $techniciansToday->filter(fn (array $row): bool => $row['assignment'] !== null)->count();

        return [
            'kpis' => [
                'activeProjects' => $activeProjects->count(),
                'inDelivery' => $activeProjects->where('status', 'delivery')->count(),
                'devicesThisWeek' => KnxDevice::query()
                    ->whereNotNull('registered_at')
                    ->where('registered_at', '>=', $monday)
                    ->count(),
                'openConflicts' => $openConflicts->count(),
                'criticalConflicts' => $openConflicts->where('severity', 'critical')->count(),
                'techniciansScheduledToday' => $scheduled,
                'techniciansTotal' => $technicians->count(),
            ],
            'activeProjects' => $activeProjects,
            'openConflicts' => $openConflicts,
            'techniciansToday' => $techniciansToday,
        ];
    }
}
