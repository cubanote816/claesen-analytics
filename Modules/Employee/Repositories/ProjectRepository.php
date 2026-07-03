<?php

namespace Modules\Employee\Repositories;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;
use Modules\Intelligence\Services\BiConfigService;
use Modules\Performance\Models\Mirror\MirrorInvoice;
use Modules\Performance\Models\Mirror\MirrorProject;
use Modules\Performance\Models\Mirror\MirrorProjectLink;

class ProjectRepository
{
    public function __construct(
        protected BiConfigService $biConfig,
    ) {}

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

            // Last non-credit-note invoice per project, regardless of the selected
            // period — same "billing gap" semantics as MonthlyBillingGuardianService
            // (BI-055 detectProjectBillingGaps), so both surfaces agree.
            $lastInvoiceDates = MirrorInvoice::whereIn('project_id', $projectIds)
                ->where('id', 'NOT LIKE', 'CN%')
                ->selectRaw('project_id, MAX(date) AS last_date')
                ->groupBy('project_id')
                ->pluck('last_date', 'project_id');

            $daysWithoutInvoice = (int) $this->biConfig->get('billing_guardian_rules.days_without_invoice', 30);
            $gapCutoff          = Carbon::now('Europe/Brussels')->subDays($daysWithoutInvoice)->startOfDay();

            // Same non-billable exclusion as MonthlyBillingGuardianService (BI-052
            // detectMissingCustomerInvoices): internal/bucket projects (no real
            // contract, no estimate linked) never get a customer invoice, so they
            // must not be flagged as a billing gap.
            $estimateLinkedProjectIds = MirrorProjectLink::whereIn('project_id', $projectIds)
                ->pluck('project_id')
                ->unique()
                ->flip();

            return $projects->map(function ($project) use ($invoicesByProject, $lastInvoiceDates, $gapCutoff, $daysWithoutInvoice, $estimateLinkedProjectIds) {
                $invoices = $invoicesByProject->get($project->id, collect());

                $hasInvoices   = $invoices->isNotEmpty();
                $totalInvoiced = $invoices->sum('total_price');
                $totalPaid     = $invoices->sum('total_paid');
                $totalPending  = $invoices->sum(fn($i) => $i->total_price - $i->total_paid);

                $hasContract = $project->contract_price !== null && (float) $project->contract_price > 0;
                $hasEstimate = $estimateLinkedProjectIds->has($project->id);
                $isBillable  = $hasContract || $hasEstimate;

                $lastInvoiceDate = $lastInvoiceDates->get($project->id);
                $billingGap      = $isBillable
                    && ($lastInvoiceDate === null || Carbon::parse($lastInvoiceDate)->lt($gapCutoff));

                // EMP-029: projects worked without a formal contract yet (billable
                // only via a linked estimate) are a more urgent risk than a normal
                // invoicing gap — unrecoverable cost if the scope is never confirmed.
                $billingGapReason = match (true) {
                    !$billingGap  => null,
                    !$hasContract => 'no_contract',
                    default       => 'overdue',
                };

                $project->has_invoices_in_period  = $hasInvoices;
                $project->total_invoiced          = $totalInvoiced;
                $project->total_paid              = $totalPaid;
                $project->total_pending           = $totalPending;
                $project->date_start_formatted    = $project->date_start ? Carbon::parse($project->date_start)->format('Y-m-d') : null;
                $project->date_end_formatted      = $project->date_end   ? Carbon::parse($project->date_end)->format('Y-m-d')   : null;
                $project->billing_gap             = $billingGap;
                $project->billing_gap_reason      = $billingGapReason;
                $project->last_invoice_date        = $lastInvoiceDate ? Carbon::parse($lastInvoiceDate)->format('Y-m-d') : null;
                $project->days_since_last_invoice = $lastInvoiceDate ? (int) Carbon::parse($lastInvoiceDate)->diffInDays(Carbon::now('Europe/Brussels')) : null;
                $project->billing_gap_threshold_days = $daysWithoutInvoice;

                return $project;
            });
        } catch (\Exception $e) {
            Log::error('ProjectRepository: getProjectsWithInvoiceInfo failed', ['error' => $e->getMessage()]);
            return new Collection();
        }
    }
}
