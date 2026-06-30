<?php

namespace Modules\Employee\Filament\Pages;

use Carbon\Carbon;
use Filament\Pages\Page;
use Modules\Employee\Repositories\EmployeeRepository;
use Modules\Employee\Services\EmployeeDashboardRankingService;

class EmployeeHoursDashboard extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-clock';
    protected static ?int $navigationSort = 10;
    protected string $view = 'employee::filament.pages.employee-hours-dashboard';

    public string $selectedYear = '';

    public array $summary = [];
    public array $chartLabels = [];
    public array $chartHoursData = [];

    public string $rankStartDate = '';
    public string $rankEndDate = '';
    public array $selectedEmployeeIds = [];
    public array $allEmployees = [];
    public array $rankings = [];

    public function mount(): void
    {
        $this->selectedYear  = Carbon::now()->format('Y');
        $this->rankStartDate = Carbon::now()->subMonth()->startOfMonth()->format('Y-m-d');
        $this->rankEndDate   = Carbon::now()->subMonth()->endOfMonth()->format('Y-m-d');

        $this->loadDashboardData();
        $this->loadRankings();
        $this->loadEmployees();
    }

    public function updatedSelectedYear(): void
    {
        $this->loadDashboardData();
    }

    public function filterRankings(): void
    {
        $this->loadRankings();
    }

    private function loadDashboardData(): void
    {
        $service = app(EmployeeDashboardRankingService::class);
        $data    = $service->getDashboardData($this->selectedYear);

        $this->summary = $data['summary'] ?? [];

        $trend = $data['monthly_hours_trend'] ?? [];
        $this->chartLabels    = array_column($trend, 'month');
        $this->chartHoursData = array_column($trend, 'total_hours');

        $this->dispatch('hours-chart-updated',
            labels: $this->chartLabels,
            hoursData: $this->chartHoursData,
        );
    }

    private function loadRankings(): void
    {
        try {
            $service = app(EmployeeDashboardRankingService::class);
            $ids     = !empty($this->selectedEmployeeIds) ? $this->selectedEmployeeIds : null;
            $result  = $service->getTopEmployees($ids, $this->rankStartDate, $this->rankEndDate);
            $this->rankings = $result['rankings']?->toArray() ?? [];
        } catch (\Exception) {
            $this->rankings = [];
        }
    }

    private function loadEmployees(): void
    {
        $this->allEmployees = app(EmployeeRepository::class)
            ->getActiveEmployees(tracksHours: true)
            ->map(fn($e) => ['id' => $e->id, 'name' => $e->name])
            ->toArray();
    }

    public static function getNavigationGroup(): ?string
    {
        return __('navigation.groups.workforce_performance');
    }

    public static function getNavigationLabel(): string
    {
        return app()->getLocale() === 'nl' ? 'Uren Dashboard' : 'Hours Dashboard';
    }

    public function getTitle(): string
    {
        return app()->getLocale() === 'nl' ? 'Uren Dashboard' : 'Hours Dashboard';
    }
}
