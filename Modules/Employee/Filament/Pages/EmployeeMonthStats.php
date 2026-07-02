<?php

namespace Modules\Employee\Filament\Pages;

use Carbon\Carbon;
use Filament\Pages\Page;
use Modules\Cafca\Models\Employee;
use Modules\Employee\Services\EmployeeTimeService;

class EmployeeMonthStats extends Page
{
    protected static bool $shouldRegisterNavigation = false;
    protected string $view = 'employee::filament.pages.employee-month-stats';

    public string $employeeId = '';
    public string $month = '';

    public ?array $data = null;
    public ?string $employeeName = null;
    public ?string $errorMessage = null;

    public function mount(): void
    {
        $this->employeeId = request()->query('employee_id', '');
        $this->month      = request()->query('month', Carbon::now()->format('Y-m'));

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
            $this->data = $service->getMonthWeeksStats($this->employeeId, $this->month);
        } catch (\Exception $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    public function getTitle(): string
    {
        $label = app()->getLocale() === 'nl' ? 'Maandoverzicht' : 'Month Overview';
        return $this->employeeName ? "{$label} — {$this->employeeName}" : $label;
    }

    public function getBreadcrumbs(): array
    {
        $isNl = app()->getLocale() === 'nl';

        $monthLabel = Carbon::createFromFormat('Y-m', $this->month)
            ->locale($isNl ? 'nl' : 'en')
            ->isoFormat('MMMM Y');

        return [
            EmployeeHoursDashboard::getUrl() => $isNl ? 'Uren Dashboard' : 'Hours Dashboard',
            ($this->employeeName ?: $this->employeeId) . ' — ' . $monthLabel,
        ];
    }
}
