<?php

namespace Modules\Employee\Filament\Pages;

use Carbon\Carbon;
use Filament\Pages\Page;
use Modules\Cafca\Models\Employee;
use Modules\Employee\Services\EmployeeTimeService;

class EmployeeWeekStats extends Page
{
    protected static bool $shouldRegisterNavigation = false;
    protected string $view = 'employee::filament.pages.employee-week-stats';

    public string $employeeId = '';
    public string $startDate  = '';
    public string $endDate    = '';

    public ?array $data = null;
    public ?string $employeeName = null;
    public ?string $errorMessage = null;

    public function mount(): void
    {
        $this->employeeId = request()->query('employee_id', '');
        $this->startDate  = request()->query('start_date', Carbon::now()->startOfWeek()->format('Y-m-d'));
        $this->endDate    = request()->query('end_date', Carbon::now()->endOfWeek()->format('Y-m-d'));

        if (!$this->employeeId) {
            $this->errorMessage = app()->getLocale() === 'nl'
                ? 'Geen medewerker opgegeven.'
                : 'No employee specified.';
            return;
        }

        $employee = Employee::find($this->employeeId);
        $this->employeeName = $employee?->name ?? $this->employeeId;

        try {
            $service    = app(EmployeeTimeService::class);
            $this->data = $service->getSpecificWeekStats($this->employeeId, $this->startDate, $this->endDate);
        } catch (\Exception $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    public function getTitle(): string
    {
        $label = app()->getLocale() === 'nl' ? 'Weekoverzicht' : 'Week Overview';
        return $this->employeeName ? "{$label} — {$this->employeeName}" : $label;
    }

    public function getBreadcrumbs(): array
    {
        $isNl = app()->getLocale() === 'nl';

        $start = Carbon::parse($this->startDate);
        $monthLabel = $start->copy()->locale($isNl ? 'nl' : 'en')->isoFormat('MMMM Y');
        $weekLabel  = $start->format('d/m') . ' – ' . Carbon::parse($this->endDate)->format('d/m/Y');

        return [
            EmployeeHoursDashboard::getUrl() => $isNl ? 'Uren Dashboard' : 'Hours Dashboard',
            EmployeeMonthStats::getUrl(['employee_id' => $this->employeeId, 'month' => $start->format('Y-m')])
                => ($this->employeeName ?: $this->employeeId) . ' — ' . $monthLabel,
            $weekLabel,
        ];
    }
}
