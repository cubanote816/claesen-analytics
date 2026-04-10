<?php

namespace Modules\Performance\Console\Commands;

use Illuminate\Console\Command;
use Modules\Cafca\Models\Project;
use Modules\Cafca\Models\Labor;
use Modules\Cafca\Models\FollowupCost;
use Modules\Cafca\Models\Invoice;
use Modules\Performance\Models\ProjectInsight;
use Modules\Performance\Emails\ImmediateRiskAlertMail;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\DB;

class PopulateProjectInsightsCommand extends Command
{
    protected $signature = 'performance:populate-insights';
    protected $description = 'ETL command to sync all active project insights from legacy SQL Server.';

    public function handle()
    {
        $this->info('Starting Full ETL process...');

        $yearsBack = env('WATCHDOG_SYNC_YEARS_BACK', 5);
        $minDate = now()->subYears($yearsBack);

        // Fetch active projects from the last X years using chunking (100 at a time)
        Project::where('fl_active', 1)
            ->where('date', '>=', $minDate)
            ->chunk(100, function ($projects) {
                foreach ($projects as $project) {
                    $projectId = trim($project->id);
                    $this->info("Processing project: {$projectId}");

                    // Aggregate hours from Analytical Labor table
                    $totalHours = Labor::where('project_id', $projectId)->sum('hours') ?? 0;
                    
                    // Real cost calculation from FollowupCost table
                    $totalCost = FollowupCost::where('project_id', $projectId)
                        ->sum(DB::raw('costprice * (CASE WHEN quantity = 0 THEN 1 ELSE quantity END)')) ?? 0;

                    // Real invoicing calculation from Invoice table
                    $invoicedAmount = Invoice::where('project_id', $projectId)
                        ->sum('total_price') ?? 0;

                    $margin = $invoicedAmount - $totalCost;
                    $deviation = $totalHours > 0 ? ($margin / $totalHours) : 0;
                    
                    $criticalLeak = null;
                    if ($margin < 0) {
                        $criticalLeak = "Costos (€" . number_format($totalCost, 2) . ") exceden facturación (€" . number_format($invoicedAmount, 2) . ").";
                    } elseif ($invoicedAmount == 0 && $totalCost > 1000) {
                        $criticalLeak = "Detección de Vacío de Facturación (> €1.000).";
                    }

                    // Simple efficiency score logic
                    $efficiencyScore = $margin > 0 ? min(100, max(0, 50 + ($deviation * 5))) : rand(10, 40);

                    $fullDna = [
                        'project_id' => $projectId,
                        'name' => trim($project->name ?? 'Unknown'),
                        'zipcode' => trim($project->zip ?? '0000'),
                        'city' => trim($project->city ?? 'Unknown'),
                        'category' => trim($project->aard ?? 'General'),
                        'financials' => [
                            'total_hours' => round($totalHours, 2),
                            'total_costs' => round($totalCost, 2),
                            'invoiced_amount' => round($invoicedAmount, 2),
                            'margin' => round($margin, 2),
                            'margin_deviation' => round($deviation, 2),
                        ]
                    ];
                    
                    $dataHash = md5(json_encode($fullDna));

                    $insight = ProjectInsight::updateOrCreate(
                        ['project_id' => $projectId],
                        [
                            'insight_type' => 'audit_budget',
                            'efficiency_score' => $efficiencyScore,
                            'critical_leak' => $criticalLeak,
                            'full_dna' => $fullDna,
                            'last_data_hash' => $dataHash,
                            'last_audited_at' => now(),
                        ]
                    );

                    // VANGUARD: Immediate high-risk alert
                    $wipAmount = abs(min(0, $margin));
                    $threshold = env('WATCHDOG_IMMEDIATE_THRESHOLD', 20000);

                    if ($wipAmount >= $threshold && $insight->last_immediate_alert_at === null) {
                        $recipient = env('WATCHDOG_REPORT_EMAIL', 'gerencia@claesen.be');
                        Mail::to($recipient)->send(new ImmediateRiskAlertMail($insight, $wipAmount));
                        
                        $insight->update(['last_immediate_alert_at' => now()]);
                        $this->warn("!!! HIGH RISK DETECTED !!! Alert sent for project: {$projectId}");
                    }
                }
            });

        $this->info('Full ETL process finished.');
    }
}
