<?php

namespace Modules\Performance\Services;

use Modules\Performance\Models\ProjectInsight;
use Modules\Performance\Models\Mirror\MirrorProject;
use Modules\Performance\Models\Mirror\MirrorInvoice;
use Modules\Performance\Models\Mirror\MirrorCost;
use Modules\Performance\Models\Mirror\MirrorLabor;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\DB;
use Modules\Performance\Emails\WatchdogRiskReportMail;
use Modules\Performance\Emails\VanguardImmediateAlertMail;

class CashFlowWatchdogService
{
    protected GeminiService $gemini;
    protected float $hourlyRate = 55.0; // Standard Claesen rate fallback

    public function __construct(GeminiService $gemini)
    {
        $this->gemini = $gemini;
    }

    /**
     * Executes the Watchdog logic to find WIP Traps and generate the Gerential Report.
     */
    public function generateRiskReport(): array|string
    {
        $cacheKey = 'cashflow_watchdog_report_v21_' . app()->getLocale() . '_' . now()->format('Y-W');
        
        return Cache::remember($cacheKey, now()->addHours(2), function () {
            $activeProjects = MirrorProject::where('fl_active', 1)->get();
            $riskyProjects = collect();
            $vanguardAlerts = collect();
            
            foreach ($activeProjects as $project) {
                $financials = $this->calculateLiveFinancials($project->id);
                $wip = $financials['wip'];
                $staleDays = $financials['days_since_last_invoice'];
                
                $isWipTrap = $wip > 2500;
                $isStale = $staleDays > 30;
                
                if ($isWipTrap || $isStale) {
                    $riskData = [
                        'id' => $project->id,
                        'name' => $project->name,
                        'wip' => $wip,
                        'stale_days' => $staleDays,
                        'total_costs' => $financials['total_costs'],
                        'total_invoiced' => $financials['total_invoiced'],
                        'risk_level' => $wip > 20000 ? 'CRITICAL' : ($wip > 5000 ? 'HIGH' : 'MEDIUM'),
                    ];
                    
                    $riskyProjects->push($riskData);
                    
                    if ($wip > 20000) {
                        $vanguardAlerts->push($riskData);
                    }
                }
            }
            
            // Trigger Vanguard Immediate Alerts
            if ($vanguardAlerts->isNotEmpty()) {
                $this->triggerVanguardAlerts($vanguardAlerts);
            }
            
            if ($riskyProjects->isEmpty()) {
                return app()->getLocale() === 'nl' 
                    ? "Goeiemorgen,\n\nEr zijn momenteel geen 'WIP Traps' of 'Stale Projects' gedetecteerd. De cashflow bij Claesen is stabiel."
                    : "Good morning,\n\nThere are currently no 'WIP Traps' or 'Stale Projects' detected. Cashflow at Claesen is stable.";
            }
            
            return $this->buildAndSendStructuredPrompt($riskyProjects->sortByDesc('wip'));
        });
    }

    private function calculateLiveFinancials(string $projectId): array
    {
        $materialCosts = MirrorCost::where('project_id', $projectId)->sum(DB::raw('cost_price * quantity'));
        
        $laborData = DB::table('intelligence_mirror_labor as l')
            ->leftJoin('intelligence_mirror_employees as e', 'l.employee_id', '=', 'e.id')
            ->where('l.project_id', $projectId)
            ->select(DB::raw('SUM(l.hours * COALESCE(NULLIF(e.hourly_cost, 0), 45)) as total_labor_cost'), DB::raw('SUM(l.hours) as total_hours'))
            ->first();

        $laborCosts = (float) ($laborData->total_labor_cost ?? 0);
        
        $totalCosts = $materialCosts + $laborCosts;
        $totalInvoiced = MirrorInvoice::where('project_id', $projectId)->sum('total_price_vat_excl');
        
        $lastInvoice = MirrorInvoice::where('project_id', $projectId)->orderByDesc('date')->first();
        $daysSinceInvoice = $lastInvoice ? now()->diffInDays($lastInvoice->date) : 999;
        
        return [
            'total_costs' => $totalCosts,
            'total_invoiced' => $totalInvoiced,
            'wip' => $totalCosts - $totalInvoiced,
            'days_since_last_invoice' => $daysSinceInvoice,
            'total_hours' => (float) ($laborData->total_hours ?? 0),
        ];
    }

    private function triggerVanguardAlerts(Collection $alerts): void
    {
        $recipient = env('WATCHDOG_VANGUARD_EMAIL', 'gerencia@claesen.be');

        foreach ($alerts as $alert) {
            Log::warning("VANGUARD ALERT: Critical risk on project {$alert['id']} ({$alert['name']}). WIP: €{$alert['wip']}");
            
            try {
                Mail::to($recipient)->send(new VanguardImmediateAlertMail($alert));
            } catch (\Exception $e) {
                Log::error("Failed to send Vanguard Alert for project {$alert['id']}: " . $e->getMessage());
            }
        }
    }

    private function buildAndSendStructuredPrompt(Collection $riskyProjects): array
    {
        $projectList = $riskyProjects->take(10)->map(function($p) {
            return "ID: {$p['id']}, Project: {$p['name']}, WIP: €".number_format($p['wip'], 2).", Stale Days: {$p['stale_days']}, Risk: {$p['risk_level']}";
        })->implode("\n");

        $language = app()->getLocale() === 'nl' ? 'DUTCH (NL)' : 'ENGLISH (EN)';
        $greeting = app()->getLocale() === 'nl' ? 'Goeiemorgen,' : 'Good morning,';
        $intro = app()->getLocale() === 'nl' ? 'Dringende update over onderhanden werk (WIP) en facturatie-fouten.' : 'Urgent update on Work in Progress (WIP) and billing errors.';
        $footer = app()->getLocale() === 'nl' ? 'Met vriendelijke groet, Uw Vanguard Auditor.' : 'Best regards, Your Vanguard Auditor.';
        $actionExample = app()->getLocale() === 'nl' ? "'Onmiddellijk factureren', 'Nabeoordeling vereist'" : "'Invoice immediately', 'Review required'";

        $prompt = <<<PROMPT
ROLE: Claesen Lead Financial Auditor.
GOAL: Create a structured JSON 'Monday Morning Risk Report' in {$language}.
CONTEXT: These projects have high WIP (Costs > Invoiced) or are stale (no billing > 30 days).

DATA:
{$projectList}

INSTRUCTIONS:
1. Return ONLY JSON.
2. Use professional {$language}.
3. Structure:
{
  "greeting": "{$greeting}",
  "intro": "{$intro}",
  "risky_projects": [
     {"id": "String", "name": "String", "wip": "€ formatted", "action": "{$language} action recommendation (e.g. {$actionExample})"}
  ],
  "footer": "{$footer}"
}
PROMPT;

        return $this->gemini->generateStructuredResponse($prompt);
    }
}
