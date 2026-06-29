<?php

namespace Modules\Employee\Services;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Modules\Cafca\Models\Invoice;
use Modules\Cafca\Models\Project;

class ProjectInvoiceService
{
    public function getActiveProjectsInMonth(int $year, int $month): Collection
    {
        $start = Carbon::create($year, $month, 1)->startOfMonth();
        $end   = $start->copy()->endOfMonth();

        return Project::where('fl_active', true)
            ->where('date_start', '<=', $end)
            ->where(fn($q) => $q->where('date_end', '>=', $start)->orWhereNull('date_end'))
            ->get();
    }

    public function getPendingInvoices(?string $projectId = null, bool $onlyOverdue = false): Collection
    {
        // Invoice has no fl_paid — use is_pending accessor from Cafca\Invoice (balance > 0.01)
        $query = Invoice::query();

        if ($projectId) {
            $query->where('project_id', $projectId);
        }

        return $query->get()
            ->filter(function ($invoice) use ($onlyOverdue) {
                if (!$invoice->is_pending) {
                    return false;
                }
                if ($onlyOverdue) {
                    // Legacy invoices may not have date_expiration — treat as not overdue
                    return isset($invoice->date_expiration) && $invoice->date_expiration < now();
                }
                return true;
            })
            ->map(function ($invoice) {
                $invoice->pending_amount = $invoice->balance;
                return $invoice;
            })
            ->values();
    }

    public function getActiveProjectsWithPendingInvoices(int $year, int $month, bool $onlyOverdue = false): Collection
    {
        $projects = $this->getActiveProjectsInMonth($year, $month);

        return $projects->filter(function ($project) use ($onlyOverdue) {
            $pending = $this->getPendingInvoices($project->id, $onlyOverdue);
            if ($pending->isEmpty()) {
                return false;
            }
            $project->pending_invoices        = $pending;
            $project->total_pending_amount    = $pending->sum('pending_amount');
            return true;
        })->values();
    }
}
