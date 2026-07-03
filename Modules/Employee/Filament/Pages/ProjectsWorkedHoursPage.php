<?php

namespace Modules\Employee\Filament\Pages;

use Carbon\Carbon;
use Filament\Pages\Page;
use Modules\Employee\Services\ProjectService;

class ProjectsWorkedHoursPage extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-briefcase';
    protected static ?int $navigationSort = 11;
    protected string $view = 'employee::filament.pages.projects-worked-hours';

    public string $startDate = '';
    public string $endDate   = '';
    public array $projects   = [];
    public ?string $errorMessage = null;
    public string $sortColumn    = 'name';
    public string $sortDirection = 'asc';

    private const SORTABLE_COLUMNS = ['name', 'date_start', 'total_invoiced', 'total_paid', 'total_pending'];

    public function mount(): void
    {
        $this->startDate = Carbon::now()->subMonth()->startOfMonth()->format('Y-m-d');
        $this->endDate   = Carbon::now()->endOfMonth()->format('Y-m-d');
        $this->loadProjects();
    }

    public function filter(): void
    {
        $this->loadProjects();
    }

    public function sortBy(string $column): void
    {
        if (!in_array($column, self::SORTABLE_COLUMNS, true)) {
            return;
        }

        if ($this->sortColumn === $column) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortColumn    = $column;
            $this->sortDirection = 'asc';
        }

        $this->applySort();
    }

    private function loadProjects(): void
    {
        try {
            $service        = app(ProjectService::class);
            $this->projects = $service->getProjectsWithWorkedHoursForPeriod($this->startDate, $this->endDate)->toArray();
            $this->errorMessage = null;
            $this->applySort();
        } catch (\Exception $e) {
            $this->errorMessage = $e->getMessage();
            $this->projects = [];
        }
    }

    private function applySort(): void
    {
        $column    = $this->sortColumn;
        $direction = $this->sortDirection;

        usort($this->projects, function (array $a, array $b) use ($column, $direction) {
            $comparison = ($a[$column] ?? null) <=> ($b[$column] ?? null);
            return $direction === 'asc' ? $comparison : -$comparison;
        });
    }

    public static function getNavigationGroup(): ?string
    {
        return __('navigation.groups.workforce_performance');
    }

    public static function getNavigationLabel(): string
    {
        return app()->getLocale() === 'nl' ? 'Uren per Project' : 'Hours per Project';
    }

    public function getTitle(): string
    {
        return app()->getLocale() === 'nl' ? 'Gewerkte Uren per Project' : 'Worked Hours per Project';
    }
}
