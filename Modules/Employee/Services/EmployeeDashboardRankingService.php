<?php

namespace Modules\Employee\Services;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Modules\Employee\Contracts\EmployeeRankingContract;
use Modules\Employee\Repositories\EmployeeRepository;
use Modules\Employee\Repositories\TimeEntryRepository;

class EmployeeDashboardRankingService implements EmployeeRankingContract
{
    public function __construct(
        protected TimeEntryRepository $timeEntryRepo,
        protected EmployeeRepository $employeeRepo,
        protected EmployeeTimeService $timeService,
    ) {}

    public function getTopEmployees(?array $employeeIds = null, ?string $startDate = null, ?string $endDate = null): Collection
    {
        try {
            [$startDate, $endDate] = $this->resolveDateRange($startDate, $endDate);

            // Skip cache for filtered subsets — too many key permutations
            if ($employeeIds !== null) {
                return $this->computeTopEmployees($employeeIds, $startDate, $endDate);
            }

            $ttl     = $this->cacheTtl($endDate);
            $cacheKey = 'employee.rankings.' . md5($startDate . $endDate);

            return Cache::remember($cacheKey, $ttl, fn () => $this->computeTopEmployees(null, $startDate, $endDate));
        } catch (\Exception $e) {
            Log::error('EmployeeDashboardRankingService: getTopEmployees failed', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    private function computeTopEmployees(?array $employeeIds, string $startDate, string $endDate): Collection
    {
        $employees = $employeeIds
            ? $this->employeeRepo->findMany($employeeIds)
            : $this->employeeRepo->getActiveEmployees(tracksHours: true);

        if ($employees->isEmpty()) {
            return new Collection();
        }

        $allEmpIds  = $employees->pluck('id')->toArray();
        $allEntries = $this->timeEntryRepo->getTimeEntriesForMultipleEmployees(
            $allEmpIds,
            Carbon::parse($startDate),
            Carbon::parse($endDate)
        );

        $rankings = new Collection();
        foreach ($employees as $employee) {
            $entries = $allEntries->where('employee_id', $employee->id);

            $ladenHours     = round($entries->where('labor_descr', 'Laden')->sum('hours'), 2);
            $werfHours      = round($entries->where('labor_descr', 'Werf')->sum('hours'), 2);
            $transportHours = round($entries->where('labor_descr', 'Mobiliteit')->sum('hours'), 2);
            $totalHours     = round($ladenHours + $werfHours + $transportHours, 2);

            $rankings->push([
                'id'    => $employee->id,
                'name'  => $employee->name,
                'email' => $employee->email,
                'labor_hours' => [
                    'laden_hours'     => $ladenHours,
                    'werf_hours'      => $werfHours,
                    'transport_hours' => $transportHours,
                ],
                'total_hours' => $totalHours,
            ]);
        }

        $sorted = $rankings->sortByDesc('total_hours')->take(10)->values();

        return new Collection([
            'period'   => ['start_date' => $startDate, 'end_date' => $endDate],
            'rankings' => $sorted,
        ]);
    }

    // Historical ranges (end before current month) are stable data → 6h.
    // Current or future ranges may change as employees log hours → 30 min.
    private function cacheTtl(string $endDate): \DateTimeInterface
    {
        $isHistorical = Carbon::parse($endDate)->isBefore(Carbon::now()->startOfMonth());

        return $isHistorical ? now()->addHours(6) : now()->addMinutes(30);
    }

    public function getDashboardData(?string $year = null): array
    {
        $targetYear = $year ? Carbon::createFromFormat('Y', $year) : Carbon::now();
        $endDate    = Carbon::now()->endOfMonth();
        $startDate  = Carbon::now()->subMonths(11)->startOfMonth();

        $employees = $this->employeeRepo->getActiveEmployees(tracksHours: true);

        if ($employees->isEmpty()) {
            return $this->emptyDashboard($targetYear->format('Y'));
        }

        $allEntries = $this->timeEntryRepo->getTimeEntriesForMultipleEmployees(
            $employees->pluck('id')->toArray(),
            $startDate,
            $endDate
        );

        $dashboardData = [];
        foreach ($employees as $employee) {
            $entries = $allEntries->where('employee_id', $employee->id);

            $ladenHours     = round($entries->where('labor_descr', 'Laden')->sum('hours'), 2);
            $werfHours      = round($entries->where('labor_descr', 'Werf')->sum('hours'), 2);
            $transportHours = round($entries->where('labor_descr', 'Mobiliteit')->sum('hours'), 2);
            $totalHours     = round($ladenHours + $werfHours + $transportHours, 2);
            $approvedHours  = round($entries->where('fl_approved', true)->sum('hours'), 2);
            $daysWorked     = $entries->pluck('entry_date')->map(fn($d) => (string) $d)->unique()->count();

            $dashboardData[] = [
                'id'           => $employee->id,
                'name'         => $employee->name,
                'email'        => $employee->email,
                'labor_hours'  => ['laden_hours' => $ladenHours, 'werf_hours' => $werfHours, 'transport_hours' => $transportHours],
                'total_hours'  => $totalHours,
                'approved_hours' => $approvedHours,
                'days_worked'  => $daysWorked,
            ];
        }

        $totalHours     = collect($dashboardData)->sum('total_hours');
        $totalApproved  = collect($dashboardData)->sum('approved_hours');
        $totalWorkDays  = $this->getWorkingDaysInYear($targetYear);
        $avgPerEmployee = $employees->count() > 0 ? round($totalHours / $employees->count(), 2) : 0;

        return [
            'year'    => $targetYear->format('Y'),
            'summary' => [
                'total_hours'                => round($totalHours, 2),
                'total_approved_hours'       => round($totalApproved, 2),
                'total_working_days'         => $totalWorkDays,
                'average_hours_per_employee' => $avgPerEmployee,
                'total_employees'            => $employees->count(),
            ],
            'monthly_hours_trend' => $this->getMonthlyHoursTrend($allEntries),
            'employees'           => $dashboardData,
        ];
    }

    private function getMonthlyHoursTrend(Collection $entries): array
    {
        $months = collect();
        $cursor = Carbon::now()->endOfMonth();
        for ($i = 0; $i < 12; $i++) {
            $months->push($cursor->copy());
            $cursor->subMonth();
        }

        return $months->map(function (Carbon $monthDate) use ($entries) {
            $key          = $monthDate->format('Y-m');
            $monthEntries = $entries->filter(fn($e) => Carbon::parse($e->entry_date)->format('Y-m') === $key);

            $laden     = round($monthEntries->where('labor_descr', 'Laden')->sum('hours'), 2);
            $werf      = round($monthEntries->where('labor_descr', 'Werf')->sum('hours'), 2);
            $transport = round($monthEntries->where('labor_descr', 'Mobiliteit')->sum('hours'), 2);
            $total     = round($laden + $werf + $transport, 2);
            $days      = $monthEntries->pluck('entry_date')->map(fn($d) => (string) $d)->unique()->count();
            $emps      = $monthEntries->pluck('employee_id')->unique()->count();

            return [
                'month'                  => $monthDate->format('M Y'),
                'total_hours'            => $total,
                'days_worked'            => $days,
                'employees_with_hours'   => $emps,
                'average_hours_per_day'  => ($days > 0 && $emps > 0) ? round($total / ($days * $emps), 2) : 0,
            ];
        })->reverse()->values()->all();
    }

    private function resolveDateRange(?string $startDate, ?string $endDate): array
    {
        if (!$startDate || !$endDate) {
            $start = Carbon::now()->subMonth()->startOfMonth()->toDateString();
            $end   = Carbon::now()->subMonth()->endOfMonth()->toDateString();
            return [$start, $end];
        }

        $start = Carbon::parse($startDate);
        $end   = Carbon::parse($endDate);
        if ($end->lessThan($start)) {
            throw new \InvalidArgumentException('end_date must be after start_date');
        }
        return [$start->toDateString(), $end->toDateString()];
    }

    private function getWorkingDaysInYear(Carbon $date): int
    {
        $start = $date->copy()->startOfYear();
        $end   = $date->copy()->endOfYear();
        $days  = 0;
        while ($start <= $end) {
            if (!$start->isWeekend()) {
                $days++;
            }
            $start->addDay();
        }
        return $days;
    }

    private function emptyDashboard(string $year): array
    {
        return [
            'year'    => $year,
            'summary' => ['total_hours' => 0, 'total_approved_hours' => 0, 'total_working_days' => 0, 'average_hours_per_employee' => 0, 'total_employees' => 0],
            'monthly_hours_trend' => array_fill(0, 12, ['month' => '', 'total_hours' => 0, 'days_worked' => 0, 'employees_with_hours' => 0, 'average_hours_per_day' => 0]),
            'employees' => [],
        ];
    }
}
