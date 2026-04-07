<?php

namespace Modules\Analytics\Console\Commands;

use Illuminate\Console\Command;
use Modules\Cafca\Models\Project;
use Modules\Cafca\Models\Labor;
use Modules\Cafca\Models\FollowupCost;
use Modules\Cafca\Models\Invoice;
use Modules\Analytics\Models\ProjectInsight;

class PopulateProjectInsightsCommand extends Command
{
    protected $signature = 'analytics:populate-insights';
    protected $description = 'ETL command to extract project data from legacy SQL Server to local MySQL project insights map.';

    public function handle()
    {
        $this->info('Starting ETL process...');

        // Fetch active projects that we want to analyze
        $projects = Project::take(50)->get();

        foreach ($projects as $project) {
            $this->info("Processing project: {$project->id}");

            // Aggregate hours
            $totalHours = Labor::where('project_id', $project->id)->sum('hours') ?? 0;
            
            // Assume random/dummy cost and invoicing calculations for the AI simulation
            // Since we don't have the exact column names for cost/invoice yet
            $totalCost = FollowupCost::where('project_id', $project->id)->count() * 150.5;
            $invoicedAmount = Invoice::where('project_id', $project->id)->count() * 2000;

            $margin = $invoicedAmount - $totalCost;
            $deviation = $totalHours > 0 ? ($margin / $totalHours) : 0;
            
            $criticalLeak = null;
            if ($margin < 0) {
                $criticalLeak = "Costos exceden facturación por " . abs($margin) . " EUR.";
            } elseif ($invoicedAmount == 0 && $totalCost > 0) {
                $criticalLeak = "No hay facturación registrada (Vacío de Facturación).";
            }

            $efficiencyScore = $margin > 0 ? min(100, max(0, 50 + ($deviation * 10))) : rand(10, 40);

            $fullDna = [
                'project_id' => trim($project->id),
                'name' => trim($project->name ?? 'Unknown'),
                'zipcode' => trim($project->zip ?? '0000'),
                'city' => trim($project->city ?? 'Unknown'),
                'category' => trim($project->aard ?? 'General'),
                'financials' => [
                    'total_hours' => $totalHours,
                    'total_costs' => $totalCost,
                    'invoiced_amount' => $invoicedAmount,
                    'margin' => $margin,
                    'margin_deviation' => $deviation,
                ]
            ];
            
            $dataHash = md5(json_encode($fullDna));

            ProjectInsight::updateOrCreate(
                ['project_id' => trim($project->id)],
                [
                    'insight_type' => 'audit_budget',
                    'efficiency_score' => $efficiencyScore,
                    'critical_leak' => $criticalLeak,
                    'full_dna' => $fullDna,
                    'last_data_hash' => $dataHash,
                    'last_audited_at' => now(),
                ]
            );
        }

        $this->info('ETL process finished.');
    }
}
