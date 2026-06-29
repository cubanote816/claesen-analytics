<?php
 
namespace Modules\Performance\Services;
 
use Modules\Cafca\Models\Employee;
use Modules\Performance\Models\Mirror\MirrorLabor;
use Modules\Performance\Models\Mirror\MirrorProject;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
 
class EmployeePerformanceService
{
    protected TechnicianAnalysisService $analysisService;
 
    public function __construct(TechnicianAnalysisService $analysisService)
    {
        $this->analysisService = $analysisService;
    }
 
    /**
     * Calculate contractual achievement rate percentage.
     * Formula: (real_hours / daily_target) * 100
     * Returns null when uren_per_week is absent or zero — metric is not calculable.
     */
    public function calculateAchievementRate(float $realHours, ?float $urenPerWeek, int $days = 1): ?float
    {
        if ($urenPerWeek === null || $urenPerWeek <= 0) {
            return null;
        }

        $target = ($urenPerWeek / 5) * $days;

        if ($target <= 0) {
            return null;
        }

        return ($realHours / $target) * 100;
    }
 

    /**
     * Categorize a labor entry into Werf, Laden or Mobiliteit.
     * Accepts both Labor (sqlsrv) and MirrorLabor (mysql) records;
     * falls back to ID-only classification when labor_descr is absent.
     */
    public function categorizeLaborEntry($labor): string
    {
        $id = (int) $labor->labor_id;
        $descr = isset($labor->labor_descr) ? trim($labor->labor_descr) : '';

        // 1. Mobiliteit (Transport)
        if ($id === 111 || $descr === 'Verplaatsing' || str_starts_with($descr, 'IN @') || str_contains($descr, ' >> ')) {
            return 'Mobiliteit';
        }

        // 2. Laden (Warehouse/Loading)
        if ($id === 114 || $descr === 'Laden') {
            return 'Laden';
        }

        // 3. Werf (Effective work) - Default for IDs 100-113
        return 'Werf';
    }

    /**
     * Get performance stats for a daily period with categories.
     */
    public function getDailyStats(Employee $employee, Carbon $date): array
    {
        $entries = MirrorLabor::where('employee_id', $employee->id)
            ->whereDate('date', $date->toDateString())
            ->get();

        $categories  = $this->aggregateCategories($entries);
        $totalHours  = array_sum($categories);

        return [
            'period'           => 'daily',
            'date'             => $date->toDateString(),
            'hours'            => $totalHours,
            'categories'       => $categories,
            'achievement_rate' => $this->calculateAchievementRate($totalHours, $employee->uren_per_week, 1),
        ];
    }

    /**
     * Get performance stats for a weekly period including project breakdown.
     */
    public function hasAnyLaborHistory(Employee $employee): bool
    {
        return MirrorLabor::where('employee_id', $employee->id)->exists();
    }

    /**
     * Get stats for a representative period (Last 30 days) to avoid empty dashboards.
     */
    public function getRecentStats(Employee $employee): array
    {
        return $this->getStatsForPeriod($employee, now()->subDays(30), now());
    }

    /**
     * Get stats for a custom period.
     */
    public function getStatsForPeriod(Employee $employee, \Carbon\CarbonInterface $start, \Carbon\CarbonInterface $end): array
    {
        $logs = MirrorLabor::where('employee_id', $employee->id)
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->get();

        return $this->aggregateStats($logs, $start, $end);
    }

    /**
     * Centralized aggregation logic.
     *
     * NOTE: achievement_rate uses a fixed 7.6h/day operational baseline
     * (potentialDays × 7.6h). It is NOT derived from Employee::uren_per_week
     * and must not be interpreted as contractual compliance.
     * Use getDailyStats() for the contractual achievement rate.
     */
    protected function aggregateStats(\Illuminate\Support\Collection $logs, \Carbon\CarbonInterface $start, \Carbon\CarbonInterface $end): array
    {
        $hours = $logs->sum('hours');
        $workingDays = $logs->where('hours', '>', 0)->pluck('date')->unique()->count();

        // Calculate expected hours (working days in period * 7.6)
        $potentialDays = $start->diffInDaysFiltered(fn(Carbon $date) => !$date->isWeekend(), $end);
        $expectedHours = $potentialDays * 7.6;
        $achievementRate = $expectedHours > 0 ? ($hours / $expectedHours) * 100 : 0;

        $diffInDays = $start->diffInDays($end);
        $isMonthlyGroup = $diffInDays > 92;

        $temporalDistribution = $logs->groupBy(function($log) use ($isMonthlyGroup) {
                return $isMonthlyGroup 
                    ? \Carbon\Carbon::parse($log->date)->format('Y-m') 
                    : \Carbon\Carbon::parse($log->date)->toDateString();
            })
            ->map(function ($periodLogs) {
                return $this->aggregateCategories($periodLogs);
            })
            ->sortKeys()
            ->toArray();

        // Ensure consecutive periods exist for smooth charts
        $cursor = $start->copy();
        $smoothSeries = [];
        
        if ($isMonthlyGroup) {
            $cursor->startOfMonth();
            $endLimit = $end->copy()->startOfMonth();
            while ($cursor->lte($endLimit) || $cursor->isSameMonth($endLimit)) {
                $monthString = $cursor->format('Y-m');
                $smoothSeries[$monthString] = $temporalDistribution[$monthString] ?? [
                    'Werf' => 0.0,
                    'Laden' => 0.0,
                    'Mobiliteit' => 0.0,
                ];
                $cursor->addMonth();
            }
        } else {
            // Ensure consecutive days exist for smooth charts
            while ($cursor->lte($end)) {
                $dateString = $cursor->toDateString();
                $smoothSeries[$dateString] = $temporalDistribution[$dateString] ?? [
                    'Werf' => 0.0,
                    'Laden' => 0.0,
                    'Mobiliteit' => 0.0,
                ];
                $cursor->addDay();
            }
        }

        return [
            'hours' => $hours,
            'working_days' => $workingDays,
            'achievement_rate' => min(100, $achievementRate),
            'categories' => $this->aggregateCategories($logs),
            'projects' => $this->getTemporalProjectDetails($logs),
            'temporal_distribution' => $smoothSeries,
            'temporal_type' => $isMonthlyGroup ? 'monthly' : 'daily',
            'period' => [
                'start' => $start->toDateString(),
                'end' => $end->toDateString(),
            ]
        ];
    }

    public function getWeeklyStats(Employee $employee, \Carbon\CarbonInterface $date): array
    {
        $start = $date->copy()->startOfWeek();
        $end = $date->copy()->endOfWeek();

        $stats = $this->getStatsForPeriod($employee, $start, $end);
        $stats['period_label'] = 'weekly';
        
        return $stats;
    }

    /**
     * Get performance stats for a monthly period including project breakdown.
     */
    public function getMonthlyStats(Employee $employee, \Carbon\CarbonInterface $date): array
    {
        $start = $date->copy()->startOfMonth();
        $end = $date->copy()->endOfMonth();

        $stats = $this->getStatsForPeriod($employee, $start, $end);
        $stats['period_label'] = 'monthly';

        return $stats;
    }

    /**
     * Aggregate hours by categories.
     */
    protected function aggregateCategories(Collection $entries): array
    {
        $categories = [
            'Werf' => 0.0,
            'Laden' => 0.0,
            'Mobiliteit' => 0.0,
        ];

        foreach ($entries as $entry) {
            $cat = $this->categorizeLaborEntry($entry);
            $categories[$cat] += (float) $entry->hours;
        }

        return $categories;
    }

    /**
     * Get unique projects and hours for a period.
     */
    public function getTemporalProjectDetails(Collection $labors): Collection
    {
        $projectIds = $labors->pluck('project_id')->unique()->filter();
        $projects = MirrorProject::whereIn('id', $projectIds)->get()->keyBy('id');

        return $labors->groupBy('project_id')
            ->map(function ($labors) use ($projects) {
                $projectId = trim($labors->first()->project_id);
                $project = $projects->get($projectId);
                $categories = $this->aggregateCategories($labors);

                return [
                    'project_id' => $projectId,
                    'project_name' => $project?->name ?? 'Unknown Project',
                    'project_type_name' => $project?->type_label ?? 'Industrie',
                    'total_hours' => array_sum($categories),
                    'last_active' => $labors->max('date'),
                    'categories' => $categories,
                    'labor_breakdown' => $labors->groupBy('labor_id')->map(fn($l) => [
                        'type' => $this->categorizeLaborEntry($l->first()),
                        'descr' => isset($l->first()->labor_descr) ? trim($l->first()->labor_descr) : (string) $l->first()->labor_id,
                        'hours' => $l->sum('hours'),
                    ])->values(),
                ];
            })->values();
    }

    /**
     * Calculate team ranking percentile for the last 30 days.
     */
    public function getComparativeRanking(Employee $employee): array
    {
        $start = now()->subDays(30);
        
        $teamHours = MirrorLabor::where('date', '>=', $start)
            ->selectRaw('employee_id, SUM(hours) as total_hours')
            ->groupBy('employee_id')
            ->pluck('total_hours', 'employee_id');

        if ($teamHours->isEmpty()) {
            return ['percentile' => 0, 'label' => 'No Data', 'rank' => 'N/A'];
        }

        $myHours = $teamHours->get($employee->id, 0);
        $count = $teamHours->count();
        $beaten = $teamHours->filter(fn($h) => $h < $myHours)->count();

        $percentile = ($count > 1) ? ($beaten / ($count - 1)) * 100 : 100;

        return [
            'percentile' => round($percentile),
            'label' => match(true) {
                $percentile >= 90 => 'Elite (Top 10%)',
                $percentile >= 75 => 'Highly Productive',
                $percentile >= 50 => 'Above Average',
                default => 'Consistent',
            },
            'rank' => ($count - $beaten) . " / $count",
        ];
    }
 
    /**
     * Get the exact team position.
     */
    public function getTeamPosition(Employee $employee): array
    {
        $ranking = $this->getComparativeRanking($employee);
        $total = MirrorLabor::distinct('employee_id')->where('date', '>=', now()->subDays(30))->count();
        
        return [
            'position' => explode('/', $ranking['rank'])[0] ?? 'N/A',
            'total' => $total ?: 1,
            'label' => 'Team Positie',
        ];
    }

    /**
     * Get the simplified percentile.
     */
    public function getPercentile(Employee $employee): int
    {
        return $this->getComparativeRanking($employee)['percentile'] ?? 0;
    }

    /**
     * Calculate burnout risk score (0-100).
     */
    public function getBurnoutRisk(Employee $employee): int
    {
        $stats = $this->getRecentStats($employee);
        $hours = $stats['hours'] ?? 0;
        
        // Simple risk heuristic: 
        // - High hours (> 180h in 30 days)
        // - High mobility (> 20%)
        // - Low achievement but high hours
        $risk = 0;
        if ($hours > 180) $risk += 40;
        if ($hours > 200) $risk += 30;
        
        $mobPerc = ($stats['categories']['Mobiliteit'] ?? 0) / max($hours, 1) * 100;
        if ($mobPerc > 25) $risk += 20;

        return min(100, $risk);
    }

    /**
     * Get AI Profile integration.
     */
    /**
     * Get AI Profile integration.
     */
    public function getPerformanceProfile(Employee $employee): array
    {
        return $this->analysisService->analyzeTechnician($employee->id, $employee->name);
    }

    /**
     * Get a streamlined trend of hours worked for a short period.
     * Ideal for Sparklines. Reads from MySQL mirror to avoid sqlsrv latency.
     */
    public function getShortTrend(Employee $employee, int $periods = 6, string $type = 'monthly'): array
    {
        $end = now()->subMonth()->endOfMonth();
        $start = $type === 'monthly'
            ? $end->copy()->subMonths($periods - 1)->startOfMonth()
            : $end->copy()->subWeeks($periods - 1)->startOfWeek();

        $groupExpr = $type === 'monthly' ? "DATE_FORMAT(date, '%Y-%m')" : 'date';

        $rows = MirrorLabor::where('employee_id', $employee->id)
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->selectRaw("{$groupExpr} as period, SUM(hours) as total")
            ->groupBy('period')
            ->orderBy('period')
            ->pluck('total', 'period');

        $distribution = [];
        $cursor = $start->copy();
        if ($type === 'monthly') {
            $endLimit = $end->copy()->startOfMonth();
            while ($cursor->lte($endLimit)) {
                $key = $cursor->format('Y-m');
                $distribution[$key] = (float) ($rows[$key] ?? 0.0);
                $cursor->addMonth();
            }
        } else {
            while ($cursor->lte($end)) {
                $key = $cursor->toDateString();
                $distribution[$key] = (float) ($rows[$key] ?? 0.0);
                $cursor->addDay();
            }
        }

        $values = array_values($distribution);
        $lastMonthName = now()->subMonth()->translatedFormat('M');
        $prevMonthName = now()->subMonths(2)->translatedFormat('M');

        return [
            'values' => $values,
            'labels' => array_keys($distribution),
            'total' => array_sum($values),
            'average' => count($values) > 0 ? array_sum($values) / count($values) : 0,
            'momentum' => $this->calculateMomentum($values),
            'period_label' => "{$lastMonthName} vs {$prevMonthName}",
        ];
    }

    /**
     * Calculate trend momentum (percentage change between last two periods).
     */
    protected function calculateMomentum(array $values): float
    {
        $count = count($values);
        if ($count < 2) return 0;
        
        $current = $values[$count - 1];
        $previous = $values[$count - 2];
        
        if ($previous <= 0) return $current > 0 ? 100 : 0;
        
        return (($current - $previous) / $previous) * 100;
    }
}
