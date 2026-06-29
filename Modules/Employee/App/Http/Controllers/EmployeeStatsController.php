<?php

namespace Modules\Employee\App\Http\Controllers;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;
use Modules\Cafca\Models\Employee;
use Modules\Employee\App\DataTransferObjects\DailyStatsDTO;
use Modules\Employee\App\DataTransferObjects\MonthlyPeriodStatsDTO;
use Modules\Employee\App\DataTransferObjects\PeriodStatsDTO;
use Modules\Employee\App\DataTransferObjects\WeeklyStatsDTO;
use Modules\Employee\App\Http\Resources\EmployeeStatsCollection;
use Modules\Employee\Repositories\TimeEntryRepository;
use Modules\Employee\Services\EmployeeService;

class EmployeeStatsController extends Controller
{
    private const MAX_DAILY_HOURS = 24;

    public function __construct(
        protected EmployeeService $employeeService,
        protected TimeEntryRepository $timeEntryRepo,
    ) {}

    public function getPeriodStats(string $employeeId, string $periodType): JsonResponse
    {
        try {
            $employee = Employee::findOrFail($employeeId);
            $stats    = match ($periodType) {
                'current-week', 'previous-week'   => $this->getWeeklyStats($employee, $periodType),
                'current-month', 'previous-month' => $this->getMonthlyStats($employee, $periodType),
                default => throw new \InvalidArgumentException('Tipo de período no válido'),
            };

            return response()->json(new EmployeeStatsCollection($stats));
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error al obtener las estadísticas del período', 'error' => $e->getMessage()], 500);
        }
    }

    private function getWeeklyStats(Employee $employee, string $periodType): PeriodStatsDTO
    {
        $dates      = $this->getPeriodDates($periodType);
        $entries    = $this->fetchEntries((string) $employee->id, $dates['start'], $dates['end']);
        $dailyStats = collect();
        $cursor     = $dates['start']->copy();

        while ($cursor <= $dates['end']) {
            $dayEntries = $entries->filter(fn($e) => Carbon::parse($e->entry_date)->isSameDay($cursor));

            if ($dayEntries->isNotEmpty()) {
                $dailyStats->push(new DailyStatsDTO(
                    date: $cursor->format('Y-m-d'),
                    hours: round($this->validateHours($dayEntries), 2),
                    productivity: $this->weightedProductivity($dayEntries),
                    completedTasks: $dayEntries->count(),
                    distance: round($dayEntries->sum('distance'), 2),
                    details: [
                        'startTime' => $this->earliestStart($dayEntries),
                        'endTime'   => $this->latestEnd($dayEntries),
                        'breaks'    => round($dayEntries->where('fl_pauze', true)->sum('pauze'), 2),
                        'totalCost' => round($dayEntries->sum('total_costprice'), 2),
                        'totalSales'=> round($dayEntries->sum('total_salesprice'), 2),
                    ]
                ));
            } else {
                $dailyStats->push(new DailyStatsDTO(
                    date: $cursor->format('Y-m-d'),
                    hours: 0, productivity: 0, completedTasks: 0, distance: 0,
                    details: ['startTime' => null, 'endTime' => null, 'breaks' => 0, 'totalCost' => 0, 'totalSales' => 0]
                ));
            }

            $cursor->addDay();
        }

        return new PeriodStatsDTO(
            periodType: $periodType,
            startDate: $dates['start']->format('Y-m-d'),
            endDate: $dates['end']->format('Y-m-d'),
            summary: [
                'total_hours'          => round($dailyStats->sum('hours'), 2),
                'average_productivity' => $this->weightedProductivity($entries),
                'total_tasks'          => $entries->count(),
                'total_distance'       => round($entries->sum('distance'), 2),
                'total_cost'           => round($entries->sum('total_costprice'), 2),
                'total_sales'          => round($entries->sum('total_salesprice'), 2),
                'total_days'           => $dailyStats->count(),
            ],
            dailyStats: $dailyStats->toArray()
        );
    }

    private function getMonthlyStats(Employee $employee, string $periodType): MonthlyPeriodStatsDTO
    {
        $dates       = $this->getPeriodDates($periodType);
        $entries     = $this->fetchEntries((string) $employee->id, $dates['start'], $dates['end']);
        $weeklyStats = collect();
        $cursor      = $dates['start']->copy()->startOfWeek();

        while ($cursor <= $dates['end']) {
            $wEnd       = min($cursor->copy()->endOfWeek(), $dates['end']);
            $wEntries   = $entries->filter(fn($e) => Carbon::parse($e->entry_date)->between($cursor, $wEnd));

            if ($wEntries->isNotEmpty()) {
                $weeklyStats->push(new WeeklyStatsDTO(
                    weekStart: $cursor->format('Y-m-d'),
                    weekEnd: $wEnd->format('Y-m-d'),
                    hours: round($wEntries->sum('hours'), 2),
                    productivity: $this->weightedProductivity($wEntries),
                    completedTasks: $wEntries->count(),
                    distance: round($wEntries->sum('distance'), 2),
                    weekNumber: (int) $cursor->format('W'),
                    details: [
                        'approved_hours' => round($wEntries->where('fl_approved', true)->sum('hours'), 2),
                        'total_cost'     => round($wEntries->sum('total_costprice'), 2),
                        'total_sales'    => round($wEntries->sum('total_salesprice'), 2),
                        'days_worked'    => $wEntries->pluck('entry_date')->map(fn($d) => (string)$d)->unique()->count(),
                    ]
                ));
            }

            $cursor->addWeek();
        }

        return new MonthlyPeriodStatsDTO(
            periodType: $periodType,
            startDate: $dates['start']->format('Y-m-d'),
            endDate: $dates['end']->format('Y-m-d'),
            summary: [
                'total_hours'          => round($entries->sum('hours'), 2),
                'average_productivity' => $this->weightedProductivity($entries),
                'total_tasks'          => $entries->count(),
                'total_distance'       => round($entries->sum('distance'), 2),
                'total_cost'           => round($entries->sum('total_costprice'), 2),
                'total_sales'          => round($entries->sum('total_salesprice'), 2),
                'total_weeks'          => $weeklyStats->count(),
            ],
            weeklyStats: $weeklyStats->toArray()
        );
    }

    private function getPeriodDates(string $periodType): array
    {
        return match ($periodType) {
            'current-week'   => ['start' => Carbon::now()->startOfWeek(),         'end' => Carbon::now()->endOfWeek()],
            'previous-week'  => ['start' => Carbon::now()->subWeek()->startOfWeek(), 'end' => Carbon::now()->subWeek()->endOfWeek()],
            'current-month'  => ['start' => Carbon::now()->startOfMonth(),         'end' => Carbon::now()->endOfMonth()],
            'previous-month' => ['start' => Carbon::now()->subMonth()->startOfMonth(), 'end' => Carbon::now()->subMonth()->endOfMonth()],
            default          => throw new \InvalidArgumentException('Tipo de período no válido'),
        };
    }

    private function fetchEntries(string $employeeId, Carbon $start, Carbon $end): Collection
    {
        $entries = $this->timeEntryRepo->getTimeEntries($employeeId, $start, $end);

        return $entries->groupBy(fn($e) => (string) $e->entry_date)
            ->map(function ($dayEntries) {
                $total = $dayEntries->sum('hours');
                if ($total > self::MAX_DAILY_HOURS) {
                    $factor = self::MAX_DAILY_HOURS / $total;
                    return $dayEntries->map(function ($e) use ($factor) {
                        $e->hours = $e->hours * $factor;
                        return $e;
                    });
                }
                return $dayEntries;
            })
            ->flatten();
    }

    private function validateHours(Collection $entries): float
    {
        return min($entries->sum('hours'), self::MAX_DAILY_HOURS);
    }

    private function weightedProductivity(Collection $entries): float
    {
        $total = $entries->sum('hours');
        if ($total <= 0) return 0.0;
        return round($entries->sum(fn($e) => $e->productivity * ($e->hours / $total)), 2);
    }

    private function earliestStart(Collection $entries): ?string
    {
        $v = $entries->filter(fn($e) => !empty($e->h_from_1))->min('h_from_1');
        return $v ? Carbon::parse($v)->format('H:i:s') : null;
    }

    private function latestEnd(Collection $entries): ?string
    {
        $v = $entries->filter(fn($e) => !empty($e->h_to_2 ?? $e->h_to_1))->max(fn($e) => $e->h_to_2 ?? $e->h_to_1);
        return $v ? Carbon::parse($v)->format('H:i:s') : null;
    }
}
