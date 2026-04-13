<?php

namespace Modules\Performance\Services;

use Modules\Cafca\Models\Project;
use Modules\Performance\Models\Mirror\MirrorProject;
use Modules\Performance\Models\Mirror\MirrorInvoice;
use Modules\Performance\Models\Mirror\MirrorCost;
use Modules\Performance\Models\Mirror\MirrorLabor;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class ProjectStrategyService
{
    protected GeminiService $gemini;

    public function __construct(GeminiService $gemini)
    {
        $this->gemini = $gemini;
    }

    /**
     * Executes a deep strategic SWOT analysis for a finished project.
     * Uses internal financial data for context but output is qualitative and Dutch.
     */
    public function generateSwotAnalysis(string $projectId): array
    {
        Log::info("Starting SWOT Analysis for Project: {$projectId}");

        // 1. Gather Project Metadata
        $project = Project::find($projectId);
        if (!$project) {
            return $this->fallbackResponse("Project niet gevonden.");
        }

        // 2. Gather Financial Context (Invisible to end-user UI)
        $financials = $this->getInternalFinancials($projectId);
        
        // 3. Gather Operational Context (Hours, categories)
        $operational = $this->getOperationalMetrics($projectId);

        // 4. Build Prompt
        $prompt = <<<PROMPT
ROLE: Senior Project Strateeg voor Claesen Verlichting (Technische installaties).
TASK: Genereer een post-mortem SWOT-analyse (DAFO) voor een AFGEROND project.

PROJECT CONFIGURATIE (Geheim/Intern):
- Naam: {$project->name}
- Type: {$project->type}
- Totale Opbrengst: €{$financials['total_invoiced']}
- Totale Kosten: €{$financials['total_costs']}
- Marge: {$financials['margin']}%
- Efficiency: {$operational['efficiency_score']}%
- Gewerkte Uren: {$operational['total_hours']}h

CATEGORIE VERDELING (Uren):
{$operational['hour_distribution']}

INSTRUCTIES:
1. Gebruik de financiële gegevens om de prioriteit van de sterktes en zwaktes te bepalen, maar toon GEEN bedragen in de tekst.
2. Formuleer een 'Golden Lesson' voor het simuleren van toekomstige offertes.
3. Taal: NEDERLANDS (Dutch).
4. Output STRICT JSON format:
{
    "user_summary": "Korte strategische samenvatting (Max 60 woorden).",
    "swot": {
        "strengths": ["Punt 1", "Punt 2"],
        "weaknesses": ["Punt 1", "Punt 2"],
        "opportunities": ["Wat kunnen we ervan leren voor de volgende offerte?"],
        "threats": ["Risico's die we in de toekomst moeten vermijden"]
    },
    "golden_lesson": "De belangrijkste leerervaring voor het Simulatie Bridge systeem."
}
PROMPT;

        try {
            $result = $this->gemini->generateStructuredResponse($prompt);
            
            return [
                'success' => true,
                'data' => $result
            ];
        } catch (\Exception $e) {
            Log::error("ProjectStrategyService SWOT Error: " . $e->getMessage());
            return $this->fallbackResponse("Fout tijdens AI-generatie.");
        }
    }

    private function getInternalFinancials(string $projectId): array
    {
        $materialCosts = MirrorCost::where('project_id', $projectId)->sum(DB::raw('cost_price * quantity'));
        
        $laborData = DB::table('intelligence_mirror_labor as l')
            ->leftJoin('intelligence_mirror_employees as e', 'l.employee_id', '=', 'e.id')
            ->where('l.project_id', $projectId)
            ->select(DB::raw('SUM(l.hours * COALESCE(NULLIF(e.hourly_cost, 0), 45)) as total_labor_cost'))
            ->first();

        $totalCosts = $materialCosts + (float)($laborData->total_labor_cost ?? 0);
        $totalInvoiced = MirrorInvoice::where('project_id', $projectId)->sum('total_price_vat_excl');
        
        $margin = $totalInvoiced > 0 ? (($totalInvoiced - $totalCosts) / $totalInvoiced) * 100 : 0;

        return [
            'total_costs' => round($totalCosts, 2),
            'total_invoiced' => round($totalInvoiced, 2),
            'margin' => round($margin, 2),
        ];
    }

    private function getOperationalMetrics(string $projectId): array
    {
        $labors = MirrorLabor::where('project_id', $projectId)->get();
        $totalHours = $labors->sum('hours');
        
        $distribution = $labors->groupBy('category')
            ->map(fn($group) => $group->sum('hours'))
            ->map(fn($hours, $cat) => "- {$cat}: {$hours}h")
            ->implode("\n");

        return [
            'total_hours' => $totalHours,
            'hour_distribution' => $distribution,
            'efficiency_score' => $totalHours > 0 ? 85 : 0, // Placeholder or basic logic
        ];
    }

    private function fallbackResponse(string $reason): array
    {
        return [
            'success' => false,
            'message' => $reason,
            'data' => [
                'user_summary' => "Geen analyse beschikbaar: " . $reason,
                'swot' => [
                    'strengths' => [],
                    'weaknesses' => [],
                    'opportunities' => [],
                    'threats' => []
                ],
                'golden_lesson' => "N/A"
            ]
        ];
    }
}
