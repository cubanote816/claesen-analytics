<?php

namespace Modules\Employee\Filament\Pages;

use Carbon\Carbon;
use Filament\Pages\Page;
use Livewire\Attributes\Url;
use Modules\Employee\Repositories\EmployeeRepository;
use Modules\Employee\Services\EmployeeDashboardRankingService;

class EmployeeHoursDashboard extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-clock';
    protected static ?int $navigationSort = 10;
    protected string $view = 'employee::filament.pages.employee-hours-dashboard';

    public const PRESETS = ['q1', 'q2', 'q3', 'q4', 'h1', 'h2', 'year', 'custom'];

    #[Url]
    public string $periodPreset = 'year';

    #[Url]
    public string $periodYear = '';

    #[Url]
    public string $customStartDate = '';

    #[Url]
    public string $customEndDate = '';

    public array $summary = [];
    public array $chartLabels = [];
    public array $chartHoursData = [];

    public array $selectedEmployeeIds = [];
    public array $allEmployees = [];
    public array $rankings = [];

    public function mount(): void
    {
        if (blank($this->periodYear)) {
            $this->periodYear = Carbon::now()->format('Y');
        }
        if (!in_array($this->periodPreset, self::PRESETS, true)) {
            $this->periodPreset = 'year';
        }
        if (blank($this->customStartDate)) {
            $this->customStartDate = Carbon::now()->subMonth()->startOfMonth()->format('Y-m-d');
        }
        if (blank($this->customEndDate)) {
            $this->customEndDate = Carbon::now()->subMonth()->endOfMonth()->format('Y-m-d');
        }

        $this->applyFilter();
        $this->loadEmployees();
    }

    public function applyFilter(): void
    {
        $this->loadDashboardData();
        $this->loadRankings();
    }

    private function resolvePeriodRange(): array
    {
        if ($this->periodPreset === 'custom') {
            return [$this->customStartDate, $this->customEndDate];
        }

        $year = (int) $this->periodYear;

        return match ($this->periodPreset) {
            'q1' => [Carbon::create($year, 1, 1)->toDateString(), Carbon::create($year, 3, 1)->endOfMonth()->toDateString()],
            'q2' => [Carbon::create($year, 4, 1)->toDateString(), Carbon::create($year, 6, 1)->endOfMonth()->toDateString()],
            'q3' => [Carbon::create($year, 7, 1)->toDateString(), Carbon::create($year, 9, 1)->endOfMonth()->toDateString()],
            'q4' => [Carbon::create($year, 10, 1)->toDateString(), Carbon::create($year, 12, 1)->endOfMonth()->toDateString()],
            'h1' => [Carbon::create($year, 1, 1)->toDateString(), Carbon::create($year, 6, 1)->endOfMonth()->toDateString()],
            'h2' => [Carbon::create($year, 7, 1)->toDateString(), Carbon::create($year, 12, 1)->endOfMonth()->toDateString()],
            default => [Carbon::create($year, 1, 1)->toDateString(), Carbon::create($year, 12, 1)->endOfMonth()->toDateString()],
        };
    }

    private function loadDashboardData(): void
    {
        [$start, $end] = $this->resolvePeriodRange();

        $service = app(EmployeeDashboardRankingService::class);
        $data    = $service->getDashboardData($start, $end);

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
        [$start, $end] = $this->resolvePeriodRange();

        try {
            $service = app(EmployeeDashboardRankingService::class);
            $ids     = !empty($this->selectedEmployeeIds) ? $this->selectedEmployeeIds : null;
            $result  = $service->getTopEmployees($ids, $start, $end);
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
