<?php

namespace Modules\Employee\App\Http\Controllers;

use App\Http\Controllers\API\BaseController;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Modules\Employee\Services\EmployeeTimeService;

class EmployeeTimeController extends BaseController
{
    public function __construct(protected EmployeeTimeService $timeService) {}

    public function getTimeStats(string $employeeId): JsonResponse
    {
        try {
            $timeStats = $this->timeService->getEmployeeTimeStats($employeeId);
            return $this->sendResponse($timeStats, 'Estadísticas de tiempo obtenidas correctamente');
        } catch (\Exception $e) {
            Log::error('Error getting employee time stats', ['employee_id' => $employeeId, 'error' => $e->getMessage()]);
            return $this->sendError('Error al obtener las estadísticas de tiempo', ['error' => $e->getMessage()], 500);
        }
    }

    public function getDayStats(string $employeeId, string $date): JsonResponse
    {
        try {
            $stats = $this->timeService->getSpecificDayStats($employeeId, $date);
            return $this->sendResponse($stats, 'Estadísticas del día obtenidas correctamente');
        } catch (\Exception $e) {
            Log::error('Error getting day stats', ['employee_id' => $employeeId, 'date' => $date, 'error' => $e->getMessage()]);
            return $this->sendError('Error al obtener las estadísticas del día', ['error' => $e->getMessage()], 500);
        }
    }

    public function getWeekStats(string $employeeId, string $startDate, ?string $endDate = null): JsonResponse
    {
        try {
            $stats = $this->timeService->getSpecificWeekStats($employeeId, $startDate, $endDate);
            return $this->sendResponse($stats, 'Estadísticas de la semana obtenidas correctamente');
        } catch (\Exception $e) {
            Log::error('Error getting week stats', ['employee_id' => $employeeId, 'start_date' => $startDate, 'error' => $e->getMessage()]);
            return $this->sendError('Error al obtener las estadísticas de la semana', ['error' => $e->getMessage()], 500);
        }
    }

    public function getMonthWeeks(string $employeeId, string $yearMonth): JsonResponse
    {
        try {
            $stats = $this->timeService->getMonthWeeksStats($employeeId, $yearMonth);
            return $this->sendResponse($stats, 'Estadísticas de las semanas del mes obtenidas correctamente');
        } catch (\Exception $e) {
            Log::error('Error getting month weeks stats', ['employee_id' => $employeeId, 'year_month' => $yearMonth, 'error' => $e->getMessage()]);
            return $this->sendError('Error al obtener las estadísticas de las semanas del mes', ['error' => $e->getMessage()], 500);
        }
    }
}
