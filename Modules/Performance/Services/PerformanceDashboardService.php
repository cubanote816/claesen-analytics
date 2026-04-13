<?php
 
namespace Modules\Performance\Services;
 
use Modules\Cafca\Models\Employee;
use Modules\Cafca\Models\Labor;
use Modules\Performance\Models\ProjectInsight;
use Modules\Performance\Models\EmployeeInsight;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
 
class PerformanceDashboardService
{
    /**
     * Get 12-month trend of total hours.
     */
    public function getTwelveMonthTrend(): array
    {
        $start = now()->subMonths(11)->startOfMonth();
 
        $data = Labor::where('date', '>=', $start)
            ->selectRaw('YEAR(date) as year, MONTH(date) as month, SUM(hours) as total_hours')
            ->groupBy('year', 'month')
            ->orderBy('year', 'asc')
            ->orderBy('month', 'asc')
            ->get();
 
        return $data->map(function ($item) {
            return [
                'label' => Carbon::createFromDate($item->year, $item->month, 1)->format('M Y'),
                'hours' => (float) $item->total_hours,
            ];
        })->toArray();
    }
 
    /**
     * Get ranking of employees based on the efficiency of projects they worked on.
     * Note: Performs cross-database manual aggregation as Labor (SQLsrv) and 
     * ProjectInsight (MySQL) cannot be joined directly in SQL.
     */
    public function getEmployeeRanking(int $limit = 10): Collection
    {
        // 1. Get Efficiency Scores per Project (MySQL)
        $projectEfficiencies = ProjectInsight::whereNotNull('efficiency_score')
            ->pluck('efficiency_score', 'project_id');
 
        if ($projectEfficiencies->isEmpty()) {
            return collect();
        }
 
        // 2. Get Employee-Project associations from Labor (SQLsrv)
        // We limit to active projects that have insights
        $labors = Labor::whereIn('project_id', $projectEfficiencies->keys())
            ->select('employee_id', 'project_id')
            ->distinct()
            ->get();
 
        // 3. Aggregate Efficiency in PHP
        $rankingData = $labors->groupBy('employee_id')
            ->map(function ($items) use ($projectEfficiencies) {
                $scores = $items->pluck('project_id')
                    ->map(fn($id) => $projectEfficiencies->get($id))
                    ->filter();
                
                return $scores->isNotEmpty() ? $scores->avg() : 0;
            })
            ->sortDesc()
            ->take($limit);
 
        // 4. Load employee names and insights
        $employeeIds = $rankingData->keys();
        $employees = Employee::whereIn('id', $employeeIds)
            ->with('insight')
            ->get()
            ->keyBy('id');
 
        return $rankingData->map(function ($avgEfficiency, $employeeId) use ($employees) {
            $employee = $employees->get($employeeId);
            return [
                'employee_id' => $employeeId,
                'name' => $employee ? $employee->name : 'Unknown',
                'avg_project_efficiency' => (float) $avgEfficiency,
                'archetype' => $employee && $employee->insight ? $employee->insight->archetype_label : 'N/A',
                'icon' => $employee && $employee->insight ? $employee->insight->archetype_icon : '👤',
            ];
        })->values();
    }
}
