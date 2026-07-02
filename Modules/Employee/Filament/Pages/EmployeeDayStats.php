<?php

namespace Modules\Employee\Filament\Pages;

use Carbon\Carbon;
use Filament\Pages\Page;
use Modules\Cafca\Models\Employee;
use Modules\Employee\Filament\Resources\EmployeeResource;
use Modules\Employee\Filament\Resources\Employees\Pages\EmployeeHoursPage;
use Modules\Employee\Services\EmployeeTimeService;

class EmployeeDayStats extends Page
{
    public const FROM_EMPLOYEE = 'employee';
    public const FROM_DASHBOARD = 'dashboard';

    protected static bool $shouldRegisterNavigation = false;
    protected string $view = 'employee::filament.pages.employee-day-stats';

    public string $employeeId = '';
    public string $date       = '';
    public string $from       = self::FROM_DASHBOARD;

    public ?array $data = null;
    public ?string $employeeName = null;
    public ?string $errorMessage = null;

    public function mount(): void
    {
        $this->employeeId = request()->query('employee_id', '');
        $this->date       = request()->query('date', Carbon::now()->format('Y-m-d'));
        $this->from       = request()->query('from', self::FROM_DASHBOARD);

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

    public function getBreadcrumbs(): array
    {
        $isNl = app()->getLocale() === 'nl';

        $day = Carbon::parse($this->date);
        $monthLabel = $day->copy()->locale($isNl ? 'nl' : 'en')->isoFormat('MMMM Y');
        $weekStart  = $day->copy()->startOfWeek();
        $weekEnd    = $day->copy()->endOfWeek();
        $weekLabel  = $weekStart->format('d/m') . ' – ' . $weekEnd->format('d/m/Y');
        $dayLabel   = $day->locale($isNl ? 'nl' : 'en')->isoFormat('ddd D/MM');
        $employeeLabel = $this->employeeName ?: $this->employeeId;

        $weekUrl = EmployeeWeekStats::getUrl([
            'employee_id' => $this->employeeId,
            'start_date'  => $weekStart->format('Y-m-d'),
            'end_date'    => $weekEnd->format('Y-m-d'),
            'from'        => $this->from,
        ]);

        if ($this->from === self::FROM_EMPLOYEE) {
            return [
                EmployeeResource::getUrl() => $isNl ? 'Medewerkers' : 'Employees',
                EmployeeResource::getUrl('view', ['record' => $this->employeeId]) => $employeeLabel,
                EmployeeHoursPage::getUrl(['record' => $this->employeeId, 'month' => $day->format('Y-m')])
                    => $monthLabel,
                $weekUrl => $weekLabel,
                $dayLabel,
            ];
        }

        return [
            EmployeeHoursDashboard::getUrl() => $isNl ? 'Uren Dashboard' : 'Hours Dashboard',
            EmployeeMonthStats::getUrl(['employee_id' => $this->employeeId, 'month' => $day->format('Y-m')])
                => $employeeLabel . ' — ' . $monthLabel,
            $weekUrl => $weekLabel,
            $dayLabel,
        ];
    }
}
