<?php

namespace Modules\Employee\Filament\Pages;

use Carbon\Carbon;
use Filament\Pages\Page;
use Modules\Cafca\Models\Employee;
use Modules\Employee\Services\EmployeeTimeService;

class EmployeeDayStats extends Page
{
    protected static bool $shouldRegisterNavigation = false;
    protected string $view = 'employee::filament.pages.employee-day-stats';

    public string $employeeId = '';
    public string $date       = '';

    public ?array $data = null;
    public ?string $employeeName = null;
    public ?string $errorMessage = null;

    public function mount(): void
    {
        $this->employeeId = request()->query('employee_id', '');
        $this->date       = request()->query('date', Carbon::now()->format('Y-m-d'));

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
            $this->data = $service->getSpecificDayStats($this->employeeId, $this->date);
        } catch (\Exception $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    public function getTitle(): string
    {
        $label = app()->getLocale() === 'nl' ? 'Dagoverzicht' : 'Day Overview';
        return $this->employeeName ? "{$label} — {$this->employeeName}" : $label;
    }
}
