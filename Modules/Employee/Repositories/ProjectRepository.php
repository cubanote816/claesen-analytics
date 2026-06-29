<?php

namespace Modules\Employee\Repositories;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;
use Modules\Cafca\Models\Project;

class ProjectRepository
{
    public function find(string $projectId): ?Project
    {
        return Project::where('id', trim($projectId))->first();
    }

    public function getProjectsByIds(array $projectIds): Collection
    {
        return Project::whereIn('id', $projectIds)->get();
    }

    public function getProjectsWithInvoiceInfo(array $projectIds, string $startDate, string $endDate): Collection
    {
        try {
            $projects = Project::whereIn('id', $projectIds)
                ->where('fl_active', true)
                ->with([
                    'invoices' => function ($query) use ($startDate, $endDate) {
                        $query->whereBetween('date', [$startDate, $endDate]);
                    },
                ])
                ->get();

            return $projects->map(function ($project) {
                $hasInvoices = $project->invoices->isNotEmpty();
                $totalInvoiced = $project->invoices->sum('total_price');
                $totalPaid     = $project->invoices->sum('total_paid');
                $totalPending  = $project->invoices->sum(fn($i) => $i->total_price - $i->total_paid);

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
