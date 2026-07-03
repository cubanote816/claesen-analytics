<?php

namespace Modules\Employee\Repositories;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;
use Modules\Performance\Models\Mirror\MirrorInvoice;
use Modules\Performance\Models\Mirror\MirrorProject;

class ProjectRepository
{
    public function find(string $projectId): ?MirrorProject
    {
        return MirrorProject::where('id', trim($projectId))->first();
    }

    public function getProjectsByIds(array $projectIds): Collection
    {
        return MirrorProject::whereIn('id', $projectIds)->get();
    }

    public function getProjectsWithInvoiceInfo(array $projectIds, string $startDate, string $endDate): Collection
    {
        try {
            $projects = MirrorProject::whereIn('id', $projectIds)
                ->where('fl_active', true)
                ->get();

            $invoicesByProject = MirrorInvoice::whereIn('project_id', $projectIds)
                ->whereBetween('date', [$startDate, $endDate])
                ->get()
                ->groupBy('project_id');

            return $projects->map(function ($project) use ($invoicesByProject) {
                $invoices = $invoicesByProject->get($project->id, collect());

                $hasInvoices   = $invoices->isNotEmpty();
                $totalInvoiced = $invoices->sum('total_price');
                $totalPaid     = $invoices->sum('total_paid');
                $totalPending  = $invoices->sum(fn($i) => $i->total_price - $i->total_paid);

                $project->has_invoices_in_period = $hasInvoices;
                $project->total_invoiced         = $totalInvoiced;
                $project->total_paid             = $totalPaid;
                $project->total_pending          = $totalPending;
                $project->date_start_formatted   = $project->date_start ? Carbon::parse($project->date_start)->format('Y-m-d') : null;
                $project->date_end_formatted     = $project->date_end   ? Carbon::parse($project->date_end)->format('Y-m-d')   : null;

                return $project;
            });
        } catch (\Exception $e) {
            Log::error('ProjectRepository: getProjectsWithInvoiceInfo failed', ['error' => $e->getMessage()]);
            return new Collection();
        }
    }
}
