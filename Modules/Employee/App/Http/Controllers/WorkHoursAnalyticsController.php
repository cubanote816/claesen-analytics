<?php

namespace Modules\Employee\App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Employee\Services\EmployeeTimeService;

class WorkHoursAnalyticsController extends Controller
{
    public function __construct(protected EmployeeTimeService $timeService) {}

    public function getProjectEfficiency(Request $request): JsonResponse
    {
        try {
            $data = $this->timeService->getProjectEfficiency($request->input('project_id'));
            return response()->json(['success' => true, 'data' => $data]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error al obtener la eficiencia del proyecto', 'error' => $e->getMessage()], 500);
        }
    }

    public function getEmployeeProjectProductivity(Request $request): JsonResponse
    {
        try {
            $data = $this->timeService->getEmployeeProjectProductivity(
                $request->input('employee_id'),
                $request->input('project_id')
            );
            return response()->json(['success' => true, 'data' => $data]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error al obtener la productividad del empleado', 'error' => $e->getMessage()], 500);
        }
    }

    public function getDashboardData(Request $request): JsonResponse
    {
        try {
            $data = $this->timeService->getDashboardData($request->input('year'));
            return response()->json(['success' => true, 'data' => $data]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error al obtener los datos del dashboard', 'error' => $e->getMessage()], 500);
        }
    }

    public function getScheduleCompliance(Request $request): JsonResponse
    {
        try {
            $data = $this->timeService->getScheduleCompliance(
                $request->input('employee_id'),
                $request->input('start_date'),
                $request->input('end_date')
            );
            return response()->json(['success' => true, 'data' => $data]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error al obtener el cumplimiento del horario', 'error' => $e->getMessage()], 500);
        }
    }
}
