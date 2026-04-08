<?php

namespace Modules\Analytics\Services;

use Modules\Analytics\Models\ProjectInsight;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class CashFlowWatchdogService
{
    protected GeminiService $gemini;

    public function __construct(GeminiService $gemini)
    {
        $this->gemini = $gemini;
    }

    /**
     * Executes the Watchdog logic to find WIP Traps and generate the Gerential Report.
     */
    public function generateRiskReport(): array|string
    {
        $cacheKey = 'cashflow_watchdog_report_data_' . now()->format('Y-W'); // Cache weekly
        
        return Cache::remember($cacheKey, now()->addHours(6), function () {
            // Find Risky Projects (WIP Trap)
            $insights = ProjectInsight::get();
            $riskyProjects = collect();
            
            foreach ($insights as $insight) {
                $dna = $insight->full_dna;
                $costs = floatval($dna['financials']['total_costs'] ?? 0);
                $invoiced = floatval($dna['financials']['invoiced_amount'] ?? 0);
                $unbilled = $costs - $invoiced;
                
                if ($unbilled > 2500 || $insight->critical_leak !== null) {
                    $riskyProjects->push([
                        'id' => $insight->project_id,
                        'name' => $dna['name'] ?? 'Unknown',
                        'unbilled_amount' => $unbilled,
                        'critical_leak' => $insight->critical_leak
                    ]);
                }
            }
            
            if ($riskyProjects->isEmpty()) {
                return "Goeiemorgen,\n\nEr zijn momenteel geen kritieke 'WIP Traps' (Onderhanden Werk) gedetecteerd. De cashflow is stabiel.";
            }
            
            $riskyProjects = $riskyProjects->sortByDesc('unbilled_amount')->take(5);
            
            return $this->buildAndSendStructuredPrompt($riskyProjects);
        });
    }

    private function buildAndSendStructuredPrompt(Collection $riskyProjects): array
    {
        $projectList = $riskyProjects->map(function($p) {
            return "ID: {$p['id']}, Name: {$p['name']}, WIP: €".number_format($p['unbilled_amount'], 2).", Leak: ".($p['critical_leak'] ?? 'None');
        })->implode("\n");

        $prompt = <<<PROMPT
ROLE: Financial Controller for Claesen Verlichting.
TASK: Create a structured JSON 'Monday Morning Risk Report'.
CONTEXT: Projects exceeding unbilled labor/materials (WIP) safety thresholds.

DATA:
{$projectList}

INSTRUCTIONS:
1. Return ONLY JSON.
2. Structure:
{
  "greeting": "Goeiemorgen,",
  "intro": "Short Dutch summary (max 2 lines).",
  "risky_projects": [
     {"id": "ID", "name": "Name", "wip": "€ amount", "action": "Short Dutch action label"}
  ],
  "footer": "Short Dutch closing."
}
PROMPT;

        return $this->gemini->generateStructuredResponse($prompt);
    }
}
