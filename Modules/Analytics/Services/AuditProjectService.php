<?php

namespace Modules\Analytics\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AuditProjectService
{
    /**
     * Post-mortem financial analysis of a project, returning structured JSON.
     */
    public function auditProject(array $data, array $metrics): array
    {
        $prompt = <<<PROMPT
ROLE: Expert Financial Controller for a Technical Installation Company (Claesen Verlichting).
TASK: Analyze project financial health and output JSON in DUTCH (Nederlands).

--- FEW-SHOT EXAMPLES (STRICTLY FOLLOW THIS FORMAT) ---
Input: Margin 2%, High Labor costs compared to budget.
Output JSON:
{
  "user_display": "Kritieke marge door overschrijding arbeidskosten.",
  "system_dna": {
    "critical_leak": "Overtollige Arbeidsuren",
    "golden_rule": "Controleer efficiëntie op de werf bij grote projecten.",
    "detailed_analysis": "Het project heeft een gevaarlijk lage marge van 2%. De hoofdoorzaak is dat de arbeidskosten het budget ver overschrijden, terwijl de materiaalkosten onder controle zijn."
  }
}

--- REAL PROJECT DATA ---
Category: {$data['category']}
Invoiced Revenue: €{$data['invoiced']}

COSTS:
- Total Profit: €{$metrics['total_profit']}
- Margin: {$metrics['margin']}%
- Labor Cost: €{$metrics['meta_labor']}
- Efficiency Score: {$metrics['efficiency_score']}/100

INSTRUCTIONS:
1. Identify if the leak is Labor (Inefficiency) or Material (Pricing).
2. Tone: Professional, stern, direct.
3. Language: DUTCH.
4. Output strict JSON only.
PROMPT;

        $apiUrl = config('services.gemini.url') ?? env('GEMINI_API_URL') ?? 'https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent';
        $apiKey = config('services.gemini.key') ?? env('GEMINI_API_KEY');

        try {
            $response = Http::post($apiUrl . "?key=" . $apiKey, [
                'contents' => [['parts' => [['text' => $prompt]]]],
                'generationConfig' => [
                    'temperature' => 0.1,
                    'responseMimeType' => 'application/json'
                ]
            ]);

            if ($response->failed()) {
                Log::error("AuditProjectService Gemini Error: " . $response->body());
                return $this->fallbackJson("API Fout.");
            }

            $result = $response->json()['candidates'][0]['content']['parts'][0]['text'] ?? '{}';
            $result = str_replace(['```json', '```'], '', $result);

            return json_decode(trim($result), true) ?? $this->fallbackJson("Ongeldig JSON formaat ontvangen.");

        } catch (\Exception $e) {
            Log::error("AuditProjectService Exception: " . $e->getMessage());
            return $this->fallbackJson("Systeemfout tijdens de audit.");
        }
    }

    private function fallbackJson(string $reason): array
    {
        return [
            "user_display" => "Audit mislukt: " . $reason,
            "system_dna" => [
                "critical_leak" => "Onbekend",
                "golden_rule" => "Herstel API-verbinding.",
                "detailed_analysis" => "Geen analyse mogelijk wegens technische storing."
            ]
        ];
    }
}
