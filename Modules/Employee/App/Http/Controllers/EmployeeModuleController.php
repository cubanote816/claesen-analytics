<?php

namespace Modules\Employee\App\Http\Controllers;

use App\Http\Controllers\API\BaseController;
use Illuminate\Http\JsonResponse;
use Modules\Employee\Services\EmployeeService;

class EmployeeModuleController extends BaseController
{
    public function __construct(protected EmployeeService $employeeService) {}

    public function getAllEmployees(): JsonResponse
    {
        try {
            $employees = $this->employeeService->getAllActiveEmployees();
            return $this->sendResponse($employees, 'Active employees retrieved successfully');
        } catch (\Exception $e) {
            return $this->sendError('Error fetching active employees', ['error' => $e->getMessage()], 500);
        }
    }
}
