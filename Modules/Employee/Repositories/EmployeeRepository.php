<?php

namespace Modules\Employee\Repositories;

use Illuminate\Database\Eloquent\Collection;
use Modules\Cafca\Models\Employee;

class EmployeeRepository
{
    public function find(string $employeeId): ?Employee
    {
        return Employee::find($employeeId);
    }

    public function findMany(array $ids): Collection
    {
        return Employee::whereIn('id', $ids)->get();
    }

    public function getActiveEmployees(): Collection
    {
        return Employee::where('fl_active', true)
            ->select(['id', 'name', 'email', 'city', 'mobile', 'birth_date', 'uren_per_week'])
            ->get();
    }
}
