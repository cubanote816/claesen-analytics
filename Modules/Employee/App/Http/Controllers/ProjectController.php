<?php

namespace Modules\Employee\App\Http\Controllers;

use App\Http\Controllers\API\BaseController;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Modules\Employee\Repositories\EmployeeRepository;
use Modules\Employee\Repositories\TimeEntryRepository;
use Modules\Employee\Services\ProjectService;

class ProjectController extends BaseController
{
    public function __construct(
        protected ProjectService $projectService,
        protected TimeEntryRepository $timeEntryRepo,
        protected EmployeeRepository $employeeRepo,
    ) {}

    public function getProjectWithWorkers(string $projectId, Request $request): JsonResponse
    {
        try {
            $result = ($request->has('start_date') && $request->has('end_date'))
                ? $this->projectService->getProjectDetailsWithWorkersForPeriod($projectId, $request->input('start_date'), $request->input('end_date'))
                : $this->projectService->getProjectDetailsWithWorkersCurrentAndPreviousMonth($projectId);

            if (!$result['success']) {
                return response()->json(['error' => $result['message']], 404);
            }

            return response()->json($this->formatProjectWithDetailedWorkers($result['data']));
        } catch (\Exception $e) {
            Log::error('Error retrieving project details', ['project_id' => $projectId, 'error' => $e->getMessage()]);
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function getProjectDetailsWithWorkers(string $projectId): JsonResponse
    {
        try {
            $result = $this->projectService->getProjectDetailsWithWorkersCurrentAndPreviousMonth($projectId);

            if (!$result['success']) {
                return $this->sendError($result['message'] ?? 'Project not found', [], 404);
            }

            return $this->sendResponse(
                $this->formatProjectWithDetailedWorkers($result['data']),
                $result['message'] ?? 'Project details retrieved successfully'
            );
        } catch (\Exception $e) {
            Log::error('Error retrieving project details', ['project_id' => $projectId, 'error' => $e->getMessage()]);
            return $this->sendError('Error retrieving project details: ' . $e->getMessage(), [], 500);
        }
    }

    public function getProjectsWithWorkedHours(Request $request): JsonResponse
    {
        try {
            $startDate   = $request->input('start_date', Carbon::now()->subMonth()->startOfMonth()->format('Y-m-d'));
            $endDate     = $request->input('end_date', Carbon::now()->endOfMonth()->format('Y-m-d'));
            $timeEntries = $this->timeEntryRepo->getTimeEntriesInDateRange($startDate, $endDate);

            if ($timeEntries->isEmpty()) {
                return response()->json(['summary' => ['period' => ['start_date' => $startDate, 'end_date' => $endDate], 'total_projects' => 0, 'total_invoices' => 0, 'total_clients' => 0, 'total_employees' => 0, 'total_hours' => 0], 'projects' => []]);
            }

            $projectIds  = $timeEntries->pluck('project_id')->unique()->filter()->values()->all();
            $projects    = [];
            $allInvoices = [];
            $allClients  = [];
            $allEmployees= [];
            $totalHours  = 0;

            foreach ($projectIds as $projectId) {
                if (empty($projectId)) continue;

                $projectEntries  = $timeEntries->where('project_id', $projectId);
                $employeeEntries = $projectEntries->groupBy('employee_id');
                $employees       = [];

                foreach ($employeeEntries as $empId => $empEntries) {
                    $empHours = round($empEntries->sum('hours'), 2);
                    if ($empHours <= 0) continue;

                    $employee = $this->employeeRepo->find((string) $empId);
                    if (!$employee) continue;

                    $allEmployees[(string) $empId] = $employee->name;
                    $totalHours  += $empHours;

                    $laden   = round($empEntries->where('labor_descr', 'Laden')->sum('hours'), 2);
                    $werf    = round($empEntries->where('labor_descr', 'Werf')->sum('hours'), 2);
                    $mob     = round($empEntries->where('labor_descr', 'Mobiliteit')->sum('hours'), 2);
                    $dWorked = $empEntries->pluck('entry_date')->unique()->count();

                    $employees[] = [
                        'id'                   => (string) $empId,
                        'name'                 => $employee->name,
                        'total_hours'          => $empHours,
                        'days_worked'          => $dWorked,
                        'hours_average_per_day'=> $dWorked > 0 ? round($empHours / $dWorked, 2) : 0,
                        'labor_hours'          => ['laden_hours' => $laden, 'werf_hours' => $werf, 'transport_hours' => $mob],
                        'avg_daily_hours'      => [
                            'avg_daily_laden_hours'     => $dWorked > 0 ? round($laden / $dWorked, 2) : 0,
                            'avg_daily_werf_hours'      => $dWorked > 0 ? round($werf / $dWorked, 2) : 0,
                            'avg_daily_transport_hours' => $dWorked > 0 ? round($mob / $dWorked, 2) : 0,
                        ],
                        'distance'             => round($empEntries->sum('distance'), 2),
                    ];
                }

                if (empty($employees)) continue;

                usort($employees, fn($a, $b) => $b['total_hours'] <=> $a['total_hours']);

                $project     = $this->projectService->getProjectDetailsWithWorkersForPeriod($projectId, $startDate, $endDate);
                $projectData = $project['data'] ?? null;
                if (!$projectData) continue;

                $invoices = [];
                if (isset($projectData['invoices']) && is_array($projectData['invoices'])) {
                    foreach ($projectData['invoices'] as $inv) {
                        $iid = $inv['id'] ?? '';
                        if (!empty($iid)) $allInvoices[$iid] = true;
                        $invoices[] = ['id' => $iid, 'date' => $inv['date'] ?? null, 'total_price' => (float) ($inv['total_price'] ?? 0), 'is_pending' => (bool) ($inv['is_pending'] ?? true)];
                    }
                }

                $projects[] = [
                    'project'   => ['id' => $projectData['id'] ?? '', 'name' => $projectData['name'] ?? '', 'descr' => $projectData['descr'] ?? '', 'date_start' => $projectData['date_start'] ?? null, 'date_end' => $projectData['date_end'] ?? null, 'active' => $projectData['fl_active'] ?? null, 'has_invoices' => !empty($invoices), 'contract_price' => (float) ($projectData['contract_price'] ?? 0)],
                    'invoices'  => $invoices,
                    'employees' => $employees,
                ];
            }

            usort($projects, fn($a, $b) => count($b['employees']) <=> count($a['employees']));

            return response()->json([
                'summary'  => ['period' => ['start_date' => $startDate, 'end_date' => $endDate], 'total_projects' => count($projects), 'total_invoices' => count($allInvoices), 'total_clients' => 0, 'total_employees' => count($allEmployees), 'total_hours' => round($totalHours, 2)],
                'projects' => $projects,
            ]);
        } catch (\Exception $e) {
            Log::error('Error retrieving projects with worked hours', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Failed to retrieve projects with worked hours', 'message' => $e->getMessage()], 500);
        }
    }

    public function getProjectBasicDetails(string $projectId): JsonResponse
    {
        try {
            $project = $this->projectService->getProjectById($projectId);

            if (!$project) {
                return $this->sendError('Project not found', [], 404);
            }

            $invoices = $project->invoices()
                ->where('fl_active', true)
                ->get()
                ->map(fn($i) => ['id' => $i->id, 'date' => $i->date, 'total_price' => (float) $i->total_price, 'is_pending' => $i->is_pending])
                ->values()->all();

            return response()->json([
                'project'   => ['id' => $project->id, 'name' => $project->name, 'descr' => $project->descr, 'date_start' => $project->date_start, 'date_end' => $project->date_end, 'state' => $project->state, 'active' => (bool) $project->fl_active, 'has_invoices' => count($invoices) > 0, 'contract_price' => (float) $project->contract_price],
                'invoices'  => $invoices,
                'employees' => [],
            ]);
        } catch (\Exception $e) {
            Log::error('Error retrieving basic project details', ['project_id' => $projectId, 'error' => $e->getMessage()]);
            return response()->json(['error' => 'Failed to retrieve project details', 'message' => $e->getMessage()], 500);
        }
    }

    private function formatProjectWithDetailedWorkers($project): array
    {
        if ($project === null) {
            return ['project' => ['id' => '', 'name' => '', 'descr' => '', 'date_start' => null, 'date_end' => null, 'state' => null, 'contract_price' => 0, 'total_invoiced' => 0, 'total_paid' => 0, 'total_pending' => 0], 'invoices' => [], 'employees' => []];
        }

        $projectData = ['id' => $project->id ?? '', 'name' => $project->name ?? '', 'descr' => $project->descr ?? '', 'date_start' => $project->date_start ?? null, 'date_end' => $project->date_end ?? null, 'state' => $project->state ?? null, 'contract_price' => (float) ($project->contract_price ?? 0), 'total_invoiced' => (float) ($project->total_invoiced ?? 0), 'total_paid' => (float) ($project->total_paid ?? 0), 'total_pending' => (float) ($project->total_pending ?? 0)];

        $invoices = collect(isset($project->invoices) ? (is_array($project->invoices) ? $project->invoices : $project->invoices->toArray()) : []);
        $formattedInvoices = $invoices->filter()->map(fn($i) => ['id' => $i['id'] ?? $i->id ?? '', 'date' => $i['date'] ?? $i->date ?? null, 'total_price' => (float) ($i['total_price'] ?? $i->total_price ?? 0), 'is_pending' => (bool) ($i['is_pending'] ?? $i->is_pending ?? true)])->values()->all();

        $workers = collect(isset($project->workers) ? $project->workers : (isset($project->employees) ? $project->employees : []));
        $byId    = [];

        foreach ($workers as $w) {
            $empId   = (string) ($w['employee_id'] ?? $w->employee_id ?? $w['id'] ?? $w->id ?? '');
            $empName = $w['employee_name'] ?? $w->employee_name ?? $w['name'] ?? $w->name ?? '';

            if (!isset($byId[$empId])) {
                $wd = ['id' => $empId, 'name' => $empName, 'total_hours' => (float) ($w['total_hours'] ?? $w->total_hours ?? 0)];
                if (isset($w['days_worked']))          $wd['days_worked']            = (int) ($w['days_worked'] ?? 0);
                if (isset($w['hours_average_per_day'])) $wd['hours_average_per_day'] = (float) ($w['hours_average_per_day'] ?? 0);
                if (isset($w['labor_hours']))          $wd['labor_hours']            = $w['labor_hours'];
                if (isset($w['avg_daily_hours']))      $wd['avg_daily_hours']        = $w['avg_daily_hours'];
                if (isset($w['distance']))             $wd['distance']               = (float) ($w['distance'] ?? 0);
                $byId[$empId] = $wd;
            }
        }

        $formattedWorkers = array_values($byId);
        usort($formattedWorkers, fn($a, $b) => $b['total_hours'] <=> $a['total_hours']);

        return ['project' => $projectData, 'invoices' => $formattedInvoices, 'employees' => $formattedWorkers];
    }
}
