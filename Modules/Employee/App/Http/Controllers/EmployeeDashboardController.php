<?php

namespace Modules\Employee\App\Http\Controllers;

use App\Http\Controllers\API\BaseController;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Modules\Employee\Services\EmployeeDashboardRankingService;

class EmployeeDashboardController extends BaseController
{
    public function __construct(protected EmployeeDashboardRankingService $rankingService) {}

    public function getEmployeeRankings(Request $request)
    {
        try {
            $employeeIds = $request->input('employee_ids');
            $startDate   = $request->input('start_date');
            $endDate     = $request->input('end_date');

            $rankings = $this->rankingService->getTopEmployees(
                $employeeIds ? explode(',', $employeeIds) : null,
                $startDate,
                $endDate
            );

            return $this->sendResponse($rankings, 'Employee rankings retrieved successfully');
        } catch (\Exception $e) {
            Log::error('Error in getEmployeeRankings', ['error' => $e->getMessage()]);
            return $this->sendError('Error retrieving employee rankings', ['error' => $e->getMessage()], 500);
        }
    }

    public function getDashboardData(Request $request)
    {
        try {
            $startDate = $request->input('start_date');
            $endDate   = $request->input('end_date');

            if (!$startDate && !$endDate && $request->filled('year')) {
                $year      = (int) $request->input('year');
                $startDate = Carbon::create($year, 1, 1)->toDateString();
                $endDate   = Carbon::create($year, 12, 31)->toDateString();
            }

            $dashboardData = $this->rankingService->getDashboardData($startDate, $endDate);
            return $this->sendResponse($dashboardData, 'Dashboard data retrieved successfully');
        } catch (\Exception $e) {
            Log::error('Error in getDashboardData', ['error' => $e->getMessage()]);
            return $this->sendError('Error retrieving dashboard data', ['error' => $e->getMessage()], 500);
        }
    }
}
