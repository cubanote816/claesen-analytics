<?php

namespace Modules\Employee\Services;

use Carbon\Carbon;
use Modules\Employee\Repositories\EmployeeRepository;
use Modules\Employee\Repositories\TimeEntryRepository;

class EmployeeRankingService
{
    public function __construct(
        protected EmployeeRepository $employeeRepo,
        protected TimeEntryRepository $timeEntryRepo,
    ) {}

    public function getEmployeeHoursRanking(): array
    {
        $startDate = Carbon::now()->subMonths(11)->startOfMonth();
        $employees = $this->employeeRepo->getActiveEmployees(tracksHours: true);

        return $employees->map(function ($employee) use ($startDate) {
            $entries    = $this->timeEntryRepo->getTimeEntries($employee->id, $startDate);
            $totalHours = $entries->sum('hours');

            return [
                'employee_id'   => $employee->id,
                'employee_name' => $employee->name,
                'total_hours'   => round($totalHours, 2),
            ];
        })->sortByDesc('total_hours')->values()->all();
    }
}
