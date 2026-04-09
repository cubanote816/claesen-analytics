<?php

namespace Modules\Analytics\Services;

use Modules\Analytics\Models\Mirror\MirrorMaterial;
use Modules\Analytics\Models\Mirror\MirrorCost;
use Modules\Analytics\Models\Mirror\MirrorProject;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MaterialIntelligenceService
{
    /**
     * Build a usage summary from historical projects and use AI to classify the material.
     */
    public function learn(MirrorMaterial $material): bool
    {
        $usageSummary = $this->buildUsageSummary($material);
        $material->usage_summary = $usageSummary;

        $apiKey = config('services.gemini.key');
        $baseUrl = config('services.gemini.url', 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent');
        
        if (!$apiKey) {
            Log::warning("Gemini API key not found. Skipping AI learning for material {$material->id}");
            return false;
        }

        $prompt = <<<EOT
U bent een "Senior Warehouse Architect" bij Claesen Verlichting. Uw taak is om een catalogusartikel te classificeren op basis van zijn HISTORISCHE GEBRUIK.

ARTIKEL:
- Naam/Descr: {$material->description}
- Prijs: €{$material->cost_price}

HISTORISCH GEBRUIK (Projecten waar dit werd gebruikt):
{$usageSummary}

OPDRACHT:
Geef een JSON-antwoord met:
1. "category_ai": De hoofdcategorie (bijv. "Sportverlichting", "Industrie", "Kabels", "Masten", "Schakelmateriaal").
2. "tags": Een lijst van 3-5 technische tags (bijv. "high-power", "LED", "IP65", "beton", "buiten").
3. "is_modern": false als dit een verouderd artikel is (HPI/SON/etc), true als het modern is (LED/IoT).
4. "modern_id_advice": Indien verouderd, welke algemene moderne ref/type zou dit kunnen vervangen?

JSON FORMAAT:
{
  "category_ai": "string",
  "tags": ["tag1", "tag2"],
  "is_modern": boolean,
  "modern_id_advice": "string"
}
EOT;

        try {
            $response = Http::post("{$baseUrl}?key={$apiKey}", [
                'contents' => [['parts' => [['text' => $prompt]]]],
                'generationConfig' => ['response_mime_type' => 'application/json']
            ]);

            if ($response->successful()) {
                $result = json_decode($response->json()['candidates'][0]['content']['parts'][0]['text'], true);
                
                $material->category_ai = $result['category_ai'] ?? 'Onbekend';
                $material->tags = $result['tags'] ?? [];
                // We could use is_modern to update a flag if we add it, for now we just use the tags
                $material->last_learned_at = now();
                $material->save();
                
                return true;
            }
        } catch (\Exception $e) {
            Log::error("Material Learning Error: " . $e->getMessage());
        }

        return false;
    }

    /**
     * Cross-reference costs and projects to see where this material was used.
     */
    public function buildUsageSummary(MirrorMaterial $material): string
    {
        $costs = MirrorCost::where('art_id', $material->id)
            ->limit(5)
            ->get();

        if ($costs->isEmpty()) {
            return "Geen historisch gebruik gevonden in de gesynchroniseerde data.";
        }

        $summary = "";
        foreach ($costs as $cost) {
            $project = MirrorProject::find($cost->project_id);
            $projectName = $project ? $project->name : "Onbekend project";
            $projectCat = $project ? $project->category : "Onbekende categorie";
            
            $summary .= "- Project: {$projectName} ({$projectCat})\n";
        }

        return $summary;
    }
}
