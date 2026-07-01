<?php

namespace Modules\Employee\Filament\Widgets;

use Carbon\Carbon;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Log;
use Modules\Employee\Services\EmployeeDashboardRankingService;

class EmployeeHoursSummaryWidget extends Widget
{
    protected static bool $isLazy = true;
    protected int|string|array $columnSpan = 'full';
    protected string $view = 'employee::filament.widgets.employee-hours-summary-widget';

    public static function canView(): bool
    {
        return request()->routeIs('filament.admin.pages.dashboard');
    }

    public string $selectedMonth = '';

    public array $summary        = [];
    public array $topThree       = [];
    public string $period        = '';
    public bool $hasHoursLogged  = false;

    public function mount(): void
    {
        $this->selectedMonth = Carbon::now()->subMonth()->format('Y-m');
        $this->loadData();
    }

    public function updatedSelectedMonth(): void
    {
        $this->loadData();
    }

    private function loadData(): void
    {
        try {
            $month = $this->selectedMonth
                ? Carbon::createFromFormat('Y-m', $this->selectedMonth)
                : Carbon::now()->subMonth();

            $start = $month->copy()->startOfMonth();
            $end   = $month->copy()->endOfMonth();

            $service = app(EmployeeDashboardRankingService::class);
            $result  = $service->getTopEmployees(
                null,
                $start->toDateString(),
                $end->toDateString(),
            );

            $rankings = $result['rankings'] ?? collect();

            $totalHours = round($rankings->sum('total_hours'), 1);
            $empCount   = $rankings->count();
            $avgHours   = $empCount > 0 ? round($totalHours / $empCount, 1) : 0;

            $this->summary = [
                'total_hours' => $totalHours,
                'emp_count'   => $empCount,
                'avg_hours'   => $avgHours,
            ];

            $this->hasHoursLogged = $totalHours > 0;
            $this->topThree       = $this->hasHoursLogged ? $rankings->take(3)->toArray() : [];
            $this->period         = $start->translatedFormat('F Y');
        } catch (\Exception $e) {
            Log::warning('EmployeeHoursSummaryWidget: failed to load', ['error' => $e->getMessage()]);
            $this->summary       = [];
            $this->topThree      = [];
            $this->hasHoursLogged = false;
        }
    }
}
