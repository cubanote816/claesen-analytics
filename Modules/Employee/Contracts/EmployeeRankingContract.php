<?php

namespace Modules\Employee\Contracts;

use Illuminate\Support\Collection;

interface EmployeeRankingContract
{
    public function getTopEmployees(?array $employeeIds = null, ?string $startDate = null, ?string $endDate = null): Collection;
}
