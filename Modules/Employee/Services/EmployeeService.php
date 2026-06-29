<?php

namespace Modules\Employee\Services;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Modules\Cafca\Models\Employee;
use Modules\Employee\Repositories\EmployeeRepository;

class EmployeeService
{
    public function __construct(protected EmployeeRepository $employeeRepo) {}

    public function getAllActiveEmployees(): Collection
    {
        return $this->employeeRepo->getActiveEmployees()
            ->map(function (Employee $employee) {
                $employee->age = $employee->birth_date
                    ? Carbon::parse($employee->birth_date)->age
                    : null;
                return $employee;
            });
    }

    public function findEmployee(string $employeeId): ?Employee
    {
        return $this->employeeRepo->find($employeeId);
    }
}
