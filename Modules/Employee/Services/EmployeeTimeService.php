<?php

namespace Modules\Employee\Services;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Modules\Employee\Repositories\EmployeeRepository;
use Modules\Employee\Repositories\ProjectRepository;
use Modules\Employee\Repositories\TimeEntryRepository;
use Modules\Performance\Models\Mirror\MirrorLabor;

class EmployeeTimeService
{
    public function __construct(
        protected EmployeeRepository $employeeRepo,
        protected TimeEntryRepository $timeEntryRepo,
        protected ProjectRepository $projectRepo,
        protected StatsCalculator $statsCalculator,
        protected ProjectService $projectService,
        protected EmployeeRankingService $rankingService,
    ) {}

    public function getEmployeeTimeStats(string $employeeId): array
    {
        $employee = $this->employeeRepo->find($employeeId);
        if (!$employee) {
            throw new \Exception('Empleado no encontrado');
        }

        $targetWeeklyHours = $employee->uren_per_week ?? 40;
        $startDate = Carbon::now()->subMonths(11)->startOfMonth();
        $timeEntries = $this->timeEntryRepo->getTimeEntries($employeeId, $startDate);

        $prevMonthStart = Carbon::now()->subMonth()->startOfMonth();
        $prevMonthEnd   = Carbon::now()->subMonth()->endOfMonth();
        $prevMonthEntries = $timeEntries->filter(fn($e) => Carbon::parse($e->entry_date)->between($prevMonthStart, $prevMonthEnd));

        $projectIds = $prevMonthEntries->pluck('project_id')->unique()->filter()->toArray();
        $projects   = $this->projectRepo->getProjectsByIds($projectIds);

        return [
            'employee' => [
                'id'       => $employee->id,
                'name'     => $employee->name,
                'email'    => $employee->email,
                'contact'  => ['city' => $employee->city, 'mobile' => $employee->mobile],
                'profile'  => [
                    'birth_date' => $employee->birth_date ? Carbon::parse($employee->birth_date)->format('Y-m-d') : null,
                    'age'        => $employee->birth_date ? Carbon::parse($employee->birth_date)->age : null,
                ],
                'status'   => ['is_active' => $employee->fl_active],
                'work_info'=> ['hours_per_week' => $targetWeeklyHours],
            ],
            'time_stats' => [
                'daily'   => $this->statsCalculator->getDailyHours($timeEntries, $targetWeeklyHours),
                'weekly'  => $this->statsCalculator->getWeeklyHours($timeEntries, $targetWeeklyHours),
                'monthly' => $this->getMonthlyHours($timeEntries, $targetWeeklyHours),
                'yearly'  => $this->getYearlyHours($timeEntries, $targetWeeklyHours),
                'trend'   => $this->getYearlyTrend($timeEntries, $targetWeeklyHours),
            ],
            'last_two_weeks'  => $this->getLastTwoWeeksStats($timeEntries, $targetWeeklyHours),
            'previous_month'  => $this->getPreviousMonthStats($timeEntries, $targetWeeklyHours),
            'projects'        => $projects->map(function ($project) use ($prevMonthEntries) {
                $pe = $prevMonthEntries->where('project_id', $project->id);
                $laden  = round($pe->where('labor_descr', 'Laden')->sum('hours'), 2);
                $werf   = round($pe->where('labor_descr', 'Werf')->sum('hours'), 2);
                $trans  = round($pe->where('labor_descr', 'Mobiliteit')->sum('hours'), 2);
                $total  = $laden + $werf + $trans;
                $est    = (float) ($project->estimated_total_hours_to_execute ?? 0);

                return [
                    'id'        => $project->id,
                    'name'      => $project->name,
                    'execution' => [
                        'estimated_hours'      => $est,
                        'actual_hours'         => round($total, 2),
                        'completion_percentage'=> $est > 0 ? round(($total / $est) * 100, 2) : 0,
                    ],
                    'transport' => [
                        'total_distance' => round($pe->sum('distance'), 2),
                    ],
                    'labor_details' => $pe->groupBy('labor_descr')->map(fn($e, $d) => [
                        'description' => $d,
                        'hours'       => round($e->sum('hours'), 2),
                    ])->values(),
                ];
            })->values()->all(),
        ];
    }

    private function getMonthlyHours(Collection $entries, float $targetWeeklyHours): array
    {
        $now      = Carbon::now()->startOfMonth();
        $monthly  = $entries->filter(fn($e) => Carbon::parse($e->entry_date)->isSameMonth($now));
        $workDays = $this->workDaysInMonth($now);
        $target   = ($targetWeeklyHours / 5) * $workDays;
        $total    = $monthly->sum('hours');
        $days     = $monthly->pluck('entry_date')->map(fn($d) => (string)$d)->unique()->count();

        return [
            'month'                  => $now->format('m'),
            'year'                   => $now->format('Y'),
            'hours'                  => round($total, 2),
            'target_hours'           => round($target, 2),
            'achievement_percentage' => $target > 0 ? round(($total / $target) * 100, 2) : 0,
            'approved_hours'         => round($monthly->where('fl_approved', true)->sum('hours'), 2),
            'working_days'           => $workDays,
            'days_worked'            => $days,
            'daily_average'          => $workDays > 0 ? round($total / $workDays, 2) : 0,
        ];
    }

    private function getYearlyHours(Collection $entries, float $targetWeeklyHours): array
    {
        $now      = Carbon::now();
        $yearly   = $entries->filter(fn($e) => Carbon::parse($e->entry_date)->isSameYear($now));
        $workDays = $this->workDaysInYear($now);
        $target   = ($targetWeeklyHours / 5) * $workDays;
        $total    = $yearly->sum('hours');

        return [
            'year'                   => $now->format('Y'),
            'hours'                  => round($total, 2),
            'target_hours'           => round($target, 2),
            'achievement_percentage' => $target > 0 ? round(($total / $target) * 100, 2) : 0,
            'approved_hours'         => round($yearly->where('fl_approved', true)->sum('hours'), 2),
            'working_days'           => $workDays,
            'days_worked'            => $yearly->pluck('entry_date')->map(fn($d) => (string)$d)->unique()->count(),
            'monthly_average'        => round($total / 12, 2),
        ];
    }

    /**
     * Trend de horas totales por mes (últimos 12 meses), para gráficos de tendencia.
     */
    public function getYearlyHoursTrend(string $employeeId): array
    {
        $employee = $this->employeeRepo->find($employeeId);
        if (!$employee) {
            throw new \Exception('Empleado no encontrado');
        }

        $targetWeeklyHours = $employee->uren_per_week ?? 40;
        $startDate   = Carbon::now()->subMonths(11)->startOfMonth();
        $timeEntries = $this->timeEntryRepo->getTimeEntries($employeeId, $startDate);

        return $this->getYearlyTrend($timeEntries, $targetWeeklyHours);
    }

    private function getYearlyTrend(Collection $entries, float $targetWeeklyHours): array
    {
        $base = Carbon::now()->startOfMonth();
        return collect(range(0, 11))->map(function ($ago) use ($entries, $base) {
            $date    = $base->copy()->subMonths($ago);
            $monthly = $entries->filter(fn($e) => Carbon::parse($e->entry_date)->isSameMonth($date));
            return [
                'month'          => $date->format('Y-m'),
                'hours'          => round($monthly->sum('hours'), 2),
                'approved_hours' => round($monthly->where('fl_approved', true)->sum('hours'), 2),
                'days_worked'    => $monthly->pluck('entry_date')->map(fn($d) => (string)$d)->unique()->count(),
            ];
        })->sortByDesc('month')->values()->all();
    }

    private function getLastTwoWeeksStats(Collection $entries, float $targetWeeklyHours): array
    {
        $cwStart = Carbon::now()->startOfWeek();
        $cwEnd   = Carbon::now()->endOfWeek();
        $pwStart = Carbon::now()->subWeek()->startOfWeek();
        $pwEnd   = Carbon::now()->subWeek()->endOfWeek();
        $all     = $entries->filter(fn($e) => Carbon::parse($e->entry_date)->between($pwStart, $cwEnd));
        $cw      = $all->filter(fn($e) => Carbon::parse($e->entry_date)->between($cwStart, $cwEnd));
        $pw      = $all->filter(fn($e) => Carbon::parse($e->entry_date)->between($pwStart, $pwEnd));

        $fmt = function (Collection $w, Carbon $s, Carbon $e) use ($targetWeeklyHours) {
            $total = $w->sum('hours');
            $days  = $w->pluck('entry_date')->map(fn($d) => (string)$d)->unique()->count();
            return [
                'start_date'             => $s->format('Y-m-d'),
                'end_date'               => $e->format('Y-m-d'),
                'hours'                  => round($total, 2),
                'target_hours'           => round($targetWeeklyHours, 2),
                'achievement_percentage' => $targetWeeklyHours > 0 ? round(($total / $targetWeeklyHours) * 100, 2) : 0,
                'approved_hours'         => round($w->where('fl_approved', true)->sum('hours'), 2),
                'days_worked'            => $days,
                'daily_average'          => $days > 0 ? round($total / $days, 2) : 0,
            ];
        };

        return [
            'current_week'        => $fmt($cw, $cwStart, $cwEnd),
            'previous_week'       => $fmt($pw, $pwStart, $pwEnd),
            'total_hours'         => round($all->sum('hours'), 2),
            'total_approved_hours'=> round($all->where('fl_approved', true)->sum('hours'), 2),
            'total_days_worked'   => $all->pluck('entry_date')->map(fn($d) => (string)$d)->unique()->count(),
        ];
    }

    private function getPreviousMonthStats(Collection $entries, float $targetWeeklyHours): array
    {
        $prev     = Carbon::now()->startOfMonth()->subMonth();
        $monthly  = $entries->filter(fn($e) => Carbon::parse($e->entry_date)->isSameMonth($prev));
        $workDays = $this->workDaysInMonth($prev);
        $target   = ($targetWeeklyHours / 5) * $workDays;
        $total    = $monthly->sum('hours');
        $days     = $monthly->pluck('entry_date')->map(fn($d) => (string)$d)->unique()->count();

        return [
            'month'                  => $prev->format('m'),
            'year'                   => $prev->format('Y'),
            'hours'                  => round($total, 2),
            'target_hours'           => round($target, 2),
            'achievement_percentage' => $target > 0 ? round(($total / $target) * 100, 2) : 0,
            'approved_hours'         => round($monthly->where('fl_approved', true)->sum('hours'), 2),
            'working_days'           => $workDays,
            'days_worked'            => $days,
            'daily_average'          => $workDays > 0 ? round($total / $workDays, 2) : 0,
            'weeks'                  => $this->weeklyBreakdown($monthly, $prev, $targetWeeklyHours),
        ];
    }

    private function weeklyBreakdown(Collection $entries, Carbon $month, float $targetWeeklyHours): array
    {
        $weeks   = [];
        $cursor  = $month->copy()->startOfMonth()->startOfWeek();
        $monthEnd = $month->copy()->endOfMonth();

        while ($cursor <= $monthEnd) {
            $wEnd = $cursor->copy()->endOfWeek();
            $we   = $entries->filter(fn($e) => Carbon::parse($e->entry_date)->between($cursor, $wEnd));
            if ($we->isNotEmpty()) {
                $weeks[] = [
                    'start_date'     => $cursor->format('Y-m-d'),
                    'end_date'       => $wEnd->format('Y-m-d'),
                    'hours'          => round($we->sum('hours'), 2),
                    'target_hours'   => round($targetWeeklyHours, 2),
                    'approved_hours' => round($we->where('fl_approved', true)->sum('hours'), 2),
                    'days_worked'    => $we->pluck('entry_date')->map(fn($d) => (string)$d)->unique()->count(),
                ];
            }
            $cursor->addWeek();
        }
        return $weeks;
    }

    public function getMonthWeeksStats(string $employeeId, string $yearMonth): array
    {
        $date = Carbon::createFromFormat('Y-m', $yearMonth);
        if (!$date) {
            throw new \Exception('Formato inválido. Use YYYY-MM');
        }

        $employee = $this->employeeRepo->find($employeeId);
        if (!$employee) {
            throw new \Exception('Empleado no encontrado');
        }

        $targetWeeklyHours = $employee->uren_per_week ?? 40;
        $startOfMonth = $date->copy()->startOfMonth();
        $endOfMonth   = $date->copy()->endOfMonth();
        $monthEntries = $this->timeEntryRepo->getTimeEntries($employeeId, $startOfMonth, $endOfMonth);

        $weeks  = [];
        $cursor = $startOfMonth->copy()->startOfWeek();

        while ($cursor <= $endOfMonth) {
            $wEnd    = $cursor->copy()->endOfWeek();
            $wEnd    = $wEnd->month !== $date->month ? $endOfMonth->copy() : $wEnd;
            $we      = $monthEntries->filter(fn($e) => Carbon::parse($e->entry_date)->between($cursor, $wEnd));

            $daysInfo = [];
            $workDays = 0;
            $tmp = $cursor->copy();
            while ($tmp <= $wEnd) {
                if ($tmp->month === $date->month) {
                    $daysInfo[] = $tmp->format('Y-m-d');
                    if (!$tmp->isWeekend()) {
                        $workDays++;
                    }
                }
                $tmp->addDay();
            }

            if (!empty($daysInfo)) {
                $laden  = round($we->where('labor_descr', 'Laden')->sum('hours'), 2);
                $werf   = round($we->where('labor_descr', 'Werf')->sum('hours'), 2);
                $trans  = round($we->where('labor_descr', 'Mobiliteit')->sum('hours'), 2);
                $total  = round($laden + $werf + $trans, 2);
                $wDays  = $we->pluck('entry_date')->map(fn($d) => (string)$d)->unique()->count();

                // Boundary weeks (first/last week of the month) only have some of
                // their weekdays inside this month — prorate the target to those
                // days instead of the full weekly target, matching getMonthlyHours()
                // and getSpecificWeekStats() elsewhere in this service.
                $target = ($targetWeeklyHours / 5) * $workDays;

                $weeks[] = [
                    'start_date'            => $cursor->format('Y-m-d'),
                    'end_date'              => $wEnd->format('Y-m-d'),
                    'working_days'          => $workDays,
                    'days_worked'           => $wDays,
                    'total_hours'           => $total,
                    'hours_average_per_day' => $wDays > 0 ? round($total / $wDays, 2) : 0,
                    'labor_hours'           => ['laden_hours' => $laden, 'werf_hours' => $werf, 'transport_hours' => $trans],
                    'total_distance'        => round($we->sum('distance'), 2),
                    'target_hours'          => round($target, 2),
                    'achievement_percentage'=> $target > 0 ? round(($total / $target) * 100, 2) : 0,
                ];
            }
            $cursor->addWeek();
        }

        return [
            'period'      => ['month' => $date->format('m'), 'year' => $date->format('Y')],
            'employee_id' => $employeeId,
            'weeks'       => $weeks,
        ];
    }

    public function getSpecificWeekStats(string $employeeId, string $startDate, ?string $endDate = null): array
    {
        $start = Carbon::parse($startDate);
        $end   = $endDate ? Carbon::parse($endDate) : $start->copy()->addDays(6);

        if ($start->diffInDays($end) > 6) {
            throw new \Exception('El rango no puede exceder 7 días');
        }

        $employee = $this->employeeRepo->find($employeeId);
        if (!$employee) {
            throw new \Exception('Empleado no encontrado');
        }

        $targetWeeklyHours = $employee->uren_per_week ?? 40;
        $weekEntries = $this->timeEntryRepo->getTimeEntries($employeeId, $start, $end);

        $laden  = round($weekEntries->where('labor_descr', 'Laden')->sum('hours'), 2);
        $werf   = round($weekEntries->where('labor_descr', 'Werf')->sum('hours'), 2);
        $trans  = round($weekEntries->where('labor_descr', 'Mobiliteit')->sum('hours'), 2);
        $total  = round($laden + $werf + $trans, 2);

        $workDays = 0;
        $daysInfo = [];
        $cursor   = $start->copy();
        while ($cursor <= $end) {
            $isWe = $cursor->isWeekend();
            if (!$isWe) {
                $workDays++;
            }
            $daysInfo[] = ['date' => $cursor->format('Y-m-d'), 'is_weekend' => $isWe, 'is_working_day' => !$isWe];
            $cursor->addDay();
        }

        $daysWorked    = $weekEntries->pluck('entry_date')->map(fn($d) => (string)$d)->unique()->count();
        $adjustedTarget = ($targetWeeklyHours / 5) * $workDays;

        $projectIds = $weekEntries->pluck('project_id')->unique()->filter();
        $projects   = $this->projectRepo->getProjectsByIds($projectIds->toArray());
        $projectMap = $projects->pluck('name', 'id')->toArray();

        $dailyStats = $weekEntries->groupBy(fn($e) => (string) $e->entry_date)
            ->map(function ($entries, $date) use ($projectMap) {
                $laden  = round($entries->where('labor_descr', 'Laden')->sum('hours'), 2);
                $werf   = round($entries->where('labor_descr', 'Werf')->sum('hours'), 2);
                $trans  = round($entries->where('labor_descr', 'Mobiliteit')->sum('hours'), 2);
                $dtotal = round($laden + $werf + $trans, 2);
                $byProj = $entries->groupBy('project_id')->map(function ($pe, $pid) use ($projectMap) {
                    $pl = round($pe->where('labor_descr', 'Laden')->sum('hours'), 2);
                    $pw = round($pe->where('labor_descr', 'Werf')->sum('hours'), 2);
                    $pt = round($pe->where('labor_descr', 'Mobiliteit')->sum('hours'), 2);
                    return ['id' => $pid, 'name' => $projectMap[$pid] ?? 'Unknown', 'total_hours' => round($pl+$pw+$pt, 2), 'labor_hours' => ['laden_hours'=>$pl,'werf_hours'=>$pw,'transport_hours'=>$pt]];
                })->values();

                return [
                    'date'           => $date,
                    'hours'          => $dtotal,
                    'approved_hours' => round($entries->where('fl_approved', true)->sum('hours'), 2),
                    'labor_hours'    => ['laden_hours' => $laden, 'werf_hours' => $werf, 'transport_hours' => $trans],
                    'costs'          => round($entries->sum('total_costprice'), 2),
                    'revenue'        => round($entries->sum('total_salesprice'), 2),
                    'distance'       => round($entries->sum('distance'), 2),
                    'transport'      => ['total_distance' => round($entries->sum('distance'), 2), 'transport_cost' => round($entries->sum('transport_costprice'), 2), 'transport_revenue' => round($entries->sum('transport_salesprice'), 2)],
                    'projects'       => $byProj,
                ];
            })->values()->all();

        return [
            'period'         => ['start_date' => $start->format('Y-m-d'), 'end_date' => $end->format('Y-m-d'), 'working_days' => $workDays],
            'summary'        => ['total_hours' => $total, 'target_hours' => round($adjustedTarget, 2), 'achievement_percentage' => $adjustedTarget > 0 ? round(($total / $adjustedTarget) * 100, 2) : 0, 'days_worked' => $daysWorked, 'average_daily_hours' => $daysWorked > 0 ? round($total / $daysWorked, 2) : 0, 'total_projects' => $projectIds->count()],
            'labor_hours'    => ['laden_hours' => $laden, 'werf_hours' => $werf, 'transport_hours' => $trans],
            'financial'      => ['costs' => round($weekEntries->sum('total_costprice'), 2), 'revenue' => round($weekEntries->sum('total_salesprice'), 2), 'profit' => round($weekEntries->sum('total_salesprice') - $weekEntries->sum('total_costprice'), 2)],
            'transport'      => ['total_distance' => round($weekEntries->sum('distance'), 2), 'transport_cost' => round($weekEntries->sum('transport_costprice'), 2), 'transport_revenue' => round($weekEntries->sum('transport_salesprice'), 2)],
            'daily_breakdown'=> $dailyStats,
            'projects'       => $projects->map(fn($p) => ['id' => $p->id, 'name' => $p->name, 'hours' => round($weekEntries->where('project_id', $p->id)->sum('hours'), 2)])->values()->all(),
        ];
    }

    public function getSpecificDayStats(string $employeeId, string $date): array
    {
        try {
            $parsed = Carbon::parse($date)->startOfDay();
        } catch (\Exception) {
            throw new \Exception('Fecha inválida');
        }

        $employee = $this->employeeRepo->find($employeeId);
        if (!$employee) {
            throw new \Exception('Empleado no encontrado');
        }

        $targetWeeklyHours = $employee->uren_per_week ?? 40;
        $dayEntries = $this->timeEntryRepo->getTimeEntries($employeeId, $parsed->copy()->startOfDay(), $parsed->copy()->endOfDay());
        $isWeekend  = $parsed->isWeekend();
        $targetDaily = !$isWeekend ? ($targetWeeklyHours / 5) : 0;

        if ($dayEntries->isEmpty()) {
            return $this->emptyDayResponse($parsed, $isWeekend, $targetDaily);
        }

        $laden  = round($dayEntries->where('labor_descr', 'Laden')->sum('hours'), 2);
        $werf   = round($dayEntries->where('labor_descr', 'Werf')->sum('hours'), 2);
        $trans  = round($dayEntries->where('labor_descr', 'Mobiliteit')->sum('hours'), 2);
        $total  = round($dayEntries->sum('hours'), 2);

        $projects = $this->projectRepo->getProjectsByIds($dayEntries->pluck('project_id')->unique()->filter()->toArray());

        return [
            'date'        => $parsed->format('Y-m-d'),
            'day_info'    => ['is_weekend' => $isWeekend, 'day_name' => $parsed->format('l'), 'is_working_day' => !$isWeekend],
            'summary'     => ['total_hours' => $total, 'approved_hours' => round($dayEntries->where('fl_approved', true)->sum('hours'), 2), 'target_hours' => round($targetDaily, 2), 'achievement_percentage' => $targetDaily > 0 ? round(($total / $targetDaily) * 100, 2) : 0],
            'schedule'    => ['start_time' => $dayEntries->min('h_from_1') ? Carbon::parse($dayEntries->min('h_from_1'))->format('H:i') : null, 'end_time' => $dayEntries->max('h_to_1') ? Carbon::parse($dayEntries->max('h_to_1'))->format('H:i') : null],
            'labor_hours' => ['laden_hours' => $laden, 'werf_hours' => $werf, 'transport_hours' => $trans],
            'financial'   => ['costs' => round($dayEntries->sum('total_costprice'), 2), 'revenue' => round($dayEntries->sum('total_salesprice'), 2), 'profit' => round($dayEntries->sum('total_salesprice') - $dayEntries->sum('total_costprice'), 2)],
            'transport'   => ['total_distance' => round($dayEntries->sum('distance'), 2), 'transport_cost' => round($dayEntries->sum('transport_costprice'), 2), 'transport_revenue' => round($dayEntries->sum('transport_salesprice'), 2)],
            'projects'    => $projects->map(fn($p) => ['id' => $p->id, 'name' => $p->name, 'hours' => round($dayEntries->where('project_id', $p->id)->sum('hours'), 2)])->values()->all(),
        ];
    }

    public function getProjectEfficiency(?string $projectId): array
    {
        if (!$projectId) {
            return ['error' => 'project_id is required'];
        }
        $project = $this->projectRepo->find($projectId);
        if (!$project) {
            return ['error' => "Project '{$projectId}' not found"];
        }
        return [
            'project_id'       => $project->id,
            'project_name'     => $project->name,
            'planned_hours'    => (float) ($project->estimated_total_hours_to_execute ?? 0),
            'total_worked_hours'=> $project->total_worked_hours ?? 0,
            'efficiency'       => $project->time_efficiency ?? 0,
        ];
    }

    public function getEmployeeProjectProductivity(?string $employeeId, ?string $projectId): array
    {
        if (!$employeeId || !$projectId) {
            return ['error' => 'employee_id and project_id are required'];
        }
        $start = Carbon::now()->subMonth()->startOfMonth();
        $end   = Carbon::now()->endOfMonth();
        $entries = $this->timeEntryRepo->getTimeEntries($employeeId, $start, $end)
            ->filter(fn($e) => $e->project_id === $projectId);

        return [
            'employee_id' => $employeeId,
            'project_id'  => $projectId,
            'total_hours' => round($entries->sum('hours'), 2),
            'productivity'=> round($entries->avg('productivity'), 2),
            'days_worked' => $entries->pluck('entry_date')->map(fn($d) => (string)$d)->unique()->count(),
        ];
    }

    private function emptyDayResponse(Carbon $parsed, bool $isWeekend, float $targetDaily): array
    {
        return [
            'date'        => $parsed->format('Y-m-d'),
            'day_info'    => ['is_weekend' => $isWeekend, 'day_name' => $parsed->format('l'), 'is_working_day' => !$isWeekend],
            'summary'     => ['total_hours' => 0, 'approved_hours' => 0, 'target_hours' => round($targetDaily, 2), 'achievement_percentage' => 0],
            'schedule'    => ['start_time' => null, 'end_time' => null],
            'labor_hours' => ['laden_hours' => 0, 'werf_hours' => 0, 'transport_hours' => 0],
            'financial'   => ['costs' => 0, 'revenue' => 0, 'profit' => 0],
            'transport'   => ['total_distance' => 0, 'transport_cost' => 0, 'transport_revenue' => 0],
            'projects'    => [],
        ];
    }

    private function workDaysInMonth(Carbon $date): int
    {
        $s = $date->copy()->startOfMonth();
        $e = $date->copy()->endOfMonth();
        $d = 0;
        while ($s <= $e) {
            if (!$s->isWeekend()) $d++;
            $s->addDay();
        }
        return $d;
    }

    private function workDaysInYear(Carbon $date): int
    {
        $s = $date->copy()->startOfYear();
        $e = $date->copy()->endOfYear();
        $d = 0;
        while ($s <= $e) {
            if (!$s->isWeekend()) $d++;
            $s->addDay();
        }
        return $d;
    }

    public function getDashboardData(?string $year = null): array
    {
        $y       = $year ? (int) $year : Carbon::now()->year;
        $start   = Carbon::create($y, 1, 1)->toDateString();
        $end     = Carbon::create($y, 12, 31)->toDateString();
        $entries = MirrorLabor::whereBetween('date', [$start, $end])->get();

        $byMonth = $entries->groupBy(fn($e) => Carbon::parse($e->date)->format('Y-m'))
            ->map(fn($m, $month) => [
                'month'          => $month,
                'total_hours'    => round($m->sum('hours'), 2),
                'employee_count' => $m->pluck('employee_id')->unique()->count(),
                'project_count'  => $m->pluck('project_id')->unique()->filter()->count(),
            ])->values();

        return [
            'year'         => $y,
            'total_hours'  => round($entries->sum('hours'), 2),
            'monthly_data' => $byMonth,
        ];
    }

    public function getScheduleCompliance(?string $employeeId, ?string $startDate, ?string $endDate): array
    {
        if (!$employeeId || !$startDate || !$endDate) {
            return [];
        }
        $start    = Carbon::parse($startDate);
        $end      = Carbon::parse($endDate);
        $employee = $this->employeeRepo->find($employeeId);
        $targetDaily = $employee ? (($employee->uren_per_week ?? 40) / 5) : 8;
        $entries  = $this->timeEntryRepo->getTimeEntries($employeeId, $start, $end);

        $days   = [];
        $cursor = $start->copy();
        while ($cursor <= $end) {
            if (!$cursor->isWeekend()) {
                $dayH = round($entries->filter(fn($e) => Carbon::parse($e->entry_date)->isSameDay($cursor))->sum('hours'), 2);
                $days[] = [
                    'date'            => $cursor->format('Y-m-d'),
                    'scheduled_hours' => round($targetDaily, 2),
                    'actual_hours'    => $dayH,
                    'compliance_rate' => $targetDaily > 0 ? round(min(($dayH / $targetDaily) * 100, 100), 2) : 0,
                ];
            }
            $cursor->addDay();
        }
        return $days;
    }
}
