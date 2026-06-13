<?php

namespace Modules\Intelligence\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Modules\Intelligence\DTOs\BillingGuardianReport;
use Modules\Intelligence\Models\BillingAlert;
use Modules\Performance\Models\Mirror\MirrorCost;
use Modules\Performance\Models\Mirror\MirrorInvoice;
use Modules\Performance\Models\Mirror\MirrorLabor;
use Modules\Performance\Models\Mirror\MirrorProject;
use Modules\Performance\Models\Mirror\MirrorProjectLink;
use Modules\Performance\Models\Mirror\MirrorWorkdoc;

/**
 * Monthly Billing Guardian — detects billing anomalies on mirror data only.
 *
 * READ-ONLY by design: this service never touches the sqlsrv connection and
 * never creates invoices. It detects, recommends and documents; the manager
 * validates and generates invoices in CAFCA.
 *
 * Detection rules (BI-052 → BI-055) are protected stubs here; each rule must
 * pass the Auditor Gate (5 real examples) before being marked Done.
 */
class MonthlyBillingGuardianService
{
    public function __construct(
        private readonly BiConfigService $config,
    ) {
    }

    /**
     * Run all detection rules for a period and persist alerts per the
     * §4.4.1 rerun policy. With $dryRun the alerts are detected and counted
     * but nothing is written.
     */
    public function analyzeMonth(int $year, int $month, bool $dryRun = false): BillingGuardianReport
    {
        if ($month < 1 || $month > 12) {
            throw new InvalidArgumentException("Invalid month: {$month}");
        }
        if ($year < 2000 || $year > (int) date('Y') + 1) {
            throw new InvalidArgumentException("Invalid year: {$year}");
        }

        $alerts = array_merge(
            $this->detectMissingCustomerInvoices($year, $month),   // BI-052
            $this->detectOverdueReceivables($year, $month),        // BI-053
            $this->detectPartialPayments($year, $month),           // BI-053
            $this->detectUnbilledFollowupCosts($year, $month),     // BI-054
            $this->detectProjectBillingGaps($year, $month),        // BI-055
            $this->detectCreditNotes($year, $month),               // BI-055
            $this->detectClosedProjectsWithBalance($year, $month), // BI-055
            $this->detectClientPaymentPatterns($year, $month),     // backlog
        );

        $byType = [];
        foreach ($alerts as $alert) {
            $byType[$alert['alert_type']] = ($byType[$alert['alert_type']] ?? 0) + 1;
        }

        $stats = ['created' => 0, 'updated' => 0, 'skipped' => 0];

        if (!$dryRun) {
            $stats = $this->upsertAlerts($alerts, $year, $month);
            $this->generateMonthlyCloseBlocker($year, $month);
        }

        Log::info('BillingGuardian: analyzeMonth completed.', [
            'period'  => sprintf('%d-%02d', $year, $month),
            'dry_run' => $dryRun,
            'detected' => count($alerts),
        ] + $stats);

        return new BillingGuardianReport(
            year: $year,
            month: $month,
            totalDetected: count($alerts),
            created: $stats['created'],
            updated: $stats['updated'],
            skipped: $stats['skipped'],
            byType: $byType,
            dryRun: $dryRun,
        );
    }

    // -------------------------------------------------------------------------
    // Detection rules — implemented in BI-052 → BI-055 (Auditor Gate applies).
    // Each returns an array of alert payloads:
    //   [alert_type, severity, project_id?, relation_id?, invoice_id?,
    //    amount_activity_cost?, amount_estimated?, amount_open?,
    //    evidence_json, recommendation]
    // -------------------------------------------------------------------------

    /**
     * BI-052 — projects with monthly cost activity but no invoice issued.
     *
     * Trigger condition: costs_in_month > min_cost_amount (strict >) AND no
     * non-credit-note invoice dated within the month for the project.
     * Hours/workdocs enrich evidence but do not trigger on their own — labor
     * costs already flow into followup_cost, so the cost threshold covers them.
     *
     * Exclusion: contract_price IS NULL AND no estimate linked → internal /
     * non-billable project, skipped unless include_projects_without_contract.
     */
    protected function detectMissingCustomerInvoices(int $year, int $month): array
    {
        $start = Carbon::create($year, $month, 1)->startOfDay();
        $end   = $start->copy()->endOfMonth()->endOfDay();

        $rules             = $this->config->get('billing_guardian_rules', []);
        // min_activity_amount is this rule's threshold (auditor-approved name);
        // min_cost_amount fallback covers configs seeded before the rename.
        $minActivity       = (float) ($rules['min_activity_amount'] ?? $rules['min_cost_amount'] ?? 500);
        $includeNoContract = (bool) ($rules['include_projects_without_contract'] ?? false);

        // Cost activity per project in the period
        $costsByProject = MirrorCost::whereBetween('date', [$start, $end])
            ->selectRaw('project_id, SUM(cost_price * quantity) AS total')
            ->groupBy('project_id')
            ->pluck('total', 'project_id');

        // Supporting evidence: hours and workdocs in the period
        $hoursByProject = MirrorLabor::whereBetween('date', [$start, $end])
            ->selectRaw('project_id, SUM(hours) AS total')
            ->groupBy('project_id')
            ->pluck('total', 'project_id');

        $workdocsByProject = MirrorWorkdoc::whereBetween('date', [$start, $end])
            ->selectRaw('project_id, COUNT(*) AS cnt')
            ->groupBy('project_id')
            ->pluck('cnt', 'project_id');

        // Projects already invoiced in the period (credit notes don't count)
        $invoicedProjects = MirrorInvoice::whereBetween('date', [$start, $end])
            ->where('id', 'NOT LIKE', 'CN%')
            ->pluck('project_id')
            ->unique()
            ->flip();

        $alerts = [];

        foreach ($costsByProject as $projectId => $costTotal) {
            $costTotal = (float) $costTotal;

            if ($costTotal <= $minActivity) {
                continue; // strict >: exactly at threshold does NOT trigger
            }

            if ($invoicedProjects->has($projectId)) {
                continue; // invoice exists this month
            }

            $project = MirrorProject::find($projectId);
            if (!$project) {
                continue;
            }

            $hasContract = $project->contract_price !== null;
            $hasEstimate = MirrorProjectLink::where('project_id', $projectId)->exists();

            if (!$hasContract && !$hasEstimate && !$includeNoContract) {
                continue; // internal / non-billable project
            }

            $lastInvoiceDate = MirrorInvoice::where('project_id', $projectId)
                ->where('id', 'NOT LIKE', 'CN%')
                ->max('date');

            $daysSinceLastInvoice = $lastInvoiceDate
                ? (int) Carbon::parse($lastInvoiceDate)->diffInDays($end)
                : null;

            $alerts[] = [
                'alert_type'           => BillingAlert::TYPE_MISSING_CUSTOMER_INVOICE,
                'severity'             => 'high',
                'project_id'           => $projectId,
                'relation_id'          => $project->relation_id,
                'amount_activity_cost' => round($costTotal, 2),
                'amount_estimated'     => $hasContract ? (float) $project->contract_price : null,
                'evidence_json'        => [
                    'costs_in_month'          => round($costTotal, 2),
                    'hours_in_month'          => round((float) ($hoursByProject[$projectId] ?? 0), 2),
                    'workdocs_in_month'       => (int) ($workdocsByProject[$projectId] ?? 0),
                    'last_invoice_date'       => $lastInvoiceDate ? Carbon::parse($lastInvoiceDate)->toDateString() : null,
                    'days_since_last_invoice' => $daysSinceLastInvoice,
                    'has_contract'            => $hasContract,
                    'has_estimate_link'       => $hasEstimate,
                ],
                'recommendation'       => sprintf(
                    'Project %s (%s) heeft €%s aan kosten in %d-%02d zonder uitgereikte factuur. %s '
                    . 'Controleer of een (deel)factuur moet worden opgemaakt in CAFCA.',
                    $projectId,
                    trim($project->name ?? ''),
                    number_format($costTotal, 2, ',', '.'),
                    $year,
                    $month,
                    $lastInvoiceDate
                        ? sprintf('Laatste factuur: %s (%d dagen geleden).', Carbon::parse($lastInvoiceDate)->toDateString(), $daysSinceLastInvoice)
                        : 'Nog geen enkele factuur voor dit project.'
                ),
            ];
        }

        return $alerts;
    }

    /** BI-053 — invoices past date_expiration with open balance. */
    protected function detectOverdueReceivables(int $year, int $month): array
    {
        return []; // implemented in BI-053
    }

    /** BI-053 — invoices partially paid (total_paid > 0 but < total_price). */
    protected function detectPartialPayments(int $year, int $month): array
    {
        return []; // implemented in BI-053
    }

    /** BI-054 — followup costs in period not flagged invoiced. */
    protected function detectUnbilledFollowupCosts(int $year, int $month): array
    {
        return []; // implemented in BI-054
    }

    /** BI-055 — active projects with no invoice for N+ days. */
    protected function detectProjectBillingGaps(int $year, int $month): array
    {
        return []; // implemented in BI-055
    }

    /** BI-055 — credit notes (CN%) in period for management visibility. */
    protected function detectCreditNotes(int $year, int $month): array
    {
        return []; // implemented in BI-055
    }

    /** BI-055 — projects marked inactive/closed with open balance. */
    protected function detectClosedProjectsWithBalance(int $year, int $month): array
    {
        return []; // implemented in BI-055
    }

    /** Backlog — clients with recurring late-payment patterns. */
    protected function detectClientPaymentPatterns(int $year, int $month): array
    {
        return []; // backlog (not in Sprint 2B scope)
    }

    // -------------------------------------------------------------------------
    // Persistence — §4.4.1 rerun/upsert policy by status
    // -------------------------------------------------------------------------

    /**
     * Upsert detected alerts. Behaviour on rerun depends on current status:
     *   open / in_review → refresh evidence, amounts, recommendation, severity
     *   confirmed        → only refresh amount_open (human decision is final)
     *   dismissed        → no action (never auto-reopen)
     *   resolved         → no action (never auto-reopen)
     *
     * @return array{created: int, updated: int, skipped: int}
     */
    protected function upsertAlerts(array $alerts, int $year, int $month): array
    {
        $stats = ['created' => 0, 'updated' => 0, 'skipped' => 0];

        foreach ($alerts as $alert) {
            $key = $this->computeDeduplicationKey(
                $alert['alert_type'],
                $alert['project_id'] ?? null,
                $alert['invoice_id'] ?? null,
                $year,
                $month,
            );

            $existing = BillingAlert::where('dedup_key', $key)->first();

            if (!$existing) {
                BillingAlert::create([
                    'dedup_key'            => $key,
                    'period_year'          => $year,
                    'period_month'         => $month,
                    'alert_type'           => $alert['alert_type'],
                    'severity'             => $alert['severity'],
                    'project_id'           => $alert['project_id'] ?? null,
                    'relation_id'          => $alert['relation_id'] ?? null,
                    'invoice_id'           => $alert['invoice_id'] ?? null,
                    'amount_activity_cost' => $alert['amount_activity_cost'] ?? null,
                    'amount_estimated'     => $alert['amount_estimated'] ?? null,
                    'amount_open'          => $alert['amount_open'] ?? null,
                    'evidence_json'        => $alert['evidence_json'],
                    'recommendation'       => $alert['recommendation'],
                ]);
                $stats['created']++;
                continue;
            }

            match ($existing->status) {
                BillingAlert::STATUS_OPEN,
                BillingAlert::STATUS_IN_REVIEW => (function () use ($existing, $alert, &$stats) {
                    $existing->update([
                        'evidence_json'        => $alert['evidence_json'],
                        'amount_activity_cost' => $alert['amount_activity_cost'] ?? null,
                        'amount_estimated'     => $alert['amount_estimated'] ?? null,
                        'amount_open'          => $alert['amount_open'] ?? null,
                        'recommendation'       => $alert['recommendation'],
                        'severity'             => $alert['severity'],
                    ]);
                    $stats['updated']++;
                })(),
                BillingAlert::STATUS_CONFIRMED => (function () use ($existing, $alert, &$stats) {
                    $existing->update(['amount_open' => $alert['amount_open'] ?? null]);
                    $stats['updated']++;
                })(),
                default => $stats['skipped']++, // dismissed / resolved: never reopen
            };
        }

        return $stats;
    }

    protected function computeDeduplicationKey(
        string $alertType,
        ?string $projectId,
        ?string $invoiceId,
        int $year,
        int $month,
    ): string {
        return BillingAlert::buildDedupKey($year, $month, $alertType, $projectId, $invoiceId);
    }

    /**
     * Summary blocker alert: while critical/high alerts remain unresolved for
     * the period, a 'monthly_close_blocker' (critical) stays open. When none
     * remain, an open blocker is auto-resolved (system-generated alert — safe
     * to close automatically, unlike human-reviewed ones).
     */
    protected function generateMonthlyCloseBlocker(int $year, int $month): void
    {
        $pending = BillingAlert::where('period_year', $year)
            ->where('period_month', $month)
            ->whereIn('severity', ['critical', 'high'])
            ->whereIn('status', [BillingAlert::STATUS_OPEN, BillingAlert::STATUS_IN_REVIEW])
            ->where('alert_type', '!=', 'monthly_close_blocker')
            ->count();

        $key = $this->computeDeduplicationKey('monthly_close_blocker', null, null, $year, $month);
        $existing = BillingAlert::where('dedup_key', $key)->first();

        if ($pending > 0) {
            $payload = [
                'severity'       => 'critical',
                'evidence_json'  => ['pending_critical_high' => $pending],
                'recommendation' => sprintf(
                    'Er zijn %d kritieke/hoge facturatiealerts onopgelost voor %d-%02d. '
                    . 'Maandafsluiting niet aanbevolen tot deze zijn beoordeeld.',
                    $pending, $year, $month
                ),
            ];

            if (!$existing) {
                BillingAlert::create([
                    'dedup_key'    => $key,
                    'period_year'  => $year,
                    'period_month' => $month,
                    'alert_type'   => 'monthly_close_blocker',
                ] + $payload);
            } elseif (in_array($existing->status, [BillingAlert::STATUS_OPEN, BillingAlert::STATUS_IN_REVIEW], true)) {
                $existing->update($payload);
            }

            return;
        }

        if ($existing && $existing->status === BillingAlert::STATUS_OPEN) {
            $existing->update([
                'status'           => BillingAlert::STATUS_RESOLVED,
                'resolved_at'      => now(),
                'resolution_notes' => 'Auto-resolved: no remaining critical/high alerts for the period.',
            ]);
        }
    }
}
