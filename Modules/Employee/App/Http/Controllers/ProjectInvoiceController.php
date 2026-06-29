<?php

namespace Modules\Employee\App\Http\Controllers;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Modules\Employee\App\Http\Resources\ProjectResource;
use Modules\Employee\Services\ProjectInvoiceService;

class ProjectInvoiceController extends Controller
{
    public function __construct(protected ProjectInvoiceService $projectInvoiceService) {}

    public function getActiveProjects(Request $request)
    {
        $year  = $request->input('year', now()->year);
        $month = $request->input('month', now()->month);
        return ProjectResource::collection($this->projectInvoiceService->getActiveProjectsInMonth($year, $month))
            ->additional(['success' => true]);
    }

    public function getPendingInvoices(Request $request)
    {
        $invoices = $this->projectInvoiceService->getPendingInvoices(
            $request->input('project_id'),
            $request->boolean('only_overdue', false)
        );
        return response()->json(['success' => true, 'data' => $invoices]);
    }

    public function getActiveProjectsWithPendingInvoices(Request $request)
    {
        $projects = $this->projectInvoiceService->getActiveProjectsWithPendingInvoices(
            $request->input('year', now()->year),
            $request->input('month', now()->month),
            $request->boolean('only_overdue', false)
        );

        $projects->each(function ($project) {
            $unique = $project->employees->unique('id');
            $project->total_unique_employees = $unique->count();
            $project->unique_employees       = $unique->values();
        });

        return ProjectResource::collection($projects)->additional(['success' => true]);
    }

    public function getProjectsOverview(Request $request, $year = null, $month = null)
    {
        try {
            if ($year === null) $year = $request->input('year');
            if ($month === null) $month = $request->input('month');

            if (($year === null || $month === null) && $request->has('date')) {
                $date  = Carbon::parse($request->input('date'));
                $year  = $year  ?? $date->year;
                $month = $month ?? $date->month;
            }

            $year  = (int) ($year ?? now()->year);
            $month = (int) ($month ?? now()->month);

            if ($month < 1 || $month > 12) {
                return response()->json(['success' => false, 'message' => 'El mes debe estar entre 1 y 12'], 400);
            }

            $onlyOverdue       = $request->boolean('only_overdue', false);
            $targetDate        = Carbon::create($year, $month, 1);
            $allActive         = $this->projectInvoiceService->getActiveProjectsInMonth($year, $month);
            $withPending       = $this->projectInvoiceService->getActiveProjectsWithPendingInvoices($year, $month, $onlyOverdue);

            $withPending->each(function ($p) {
                $u = $p->employees->unique('id');
                $p->total_unique_employees = $u->count();
                $p->unique_employees       = $u->values();
            });

            $allActive->each(function ($p) {
                if (!isset($p->total_pending)) $p->total_pending = 0;
                if (!isset($p->invoices))      $p->invoices      = collect();
                if (!isset($p->unique_employees)) {
                    $u = isset($p->employees) ? $p->employees->unique('id') : collect();
                    $p->total_unique_employees = $u->count();
                    $p->unique_employees       = $u->values();
                    $p->employees              = $p->employees ?? collect();
                }
            });

            return response()->json([
                'success' => true,
                'data'    => [
                    'all_active_projects'              => ProjectResource::collection($allActive),
                    'projects_with_pending_invoices'   => ProjectResource::collection($withPending),
                ],
                'meta' => [
                    'date'                                  => sprintf('%04d-%02d', $year, $month),
                    'year'                                  => $year,
                    'month'                                 => $month,
                    'month_name'                            => $targetDate->translatedFormat('F'),
                    'only_overdue'                          => $onlyOverdue,
                    'total_active_projects'                 => $allActive->count(),
                    'total_projects_with_pending_invoices'  => $withPending->count(),
                    'pending_project_ids'                   => $withPending->pluck('id')->toArray(),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error al obtener la información de proyectos', 'error' => $e->getMessage()], 500);
        }
    }
}
