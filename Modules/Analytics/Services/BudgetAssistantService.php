<?php

namespace Modules\Analytics\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Modules\Analytics\Services\ProjectSimilarityService;
use Modules\Analytics\Models\Mirror\MirrorMaterial;
use Modules\Analytics\Models\OfferSimulation;
use Illuminate\Support\Collection;

class BudgetAssistantService
{
    protected ProjectSimilarityService $similarityService;

    public function __construct(ProjectSimilarityService $similarityService)
    {
        $this->similarityService = $similarityService;
    }

    /**
     * Simulate an offer with Zero-Trust protection and Proactive AI.
     */
    public function simulate(string $description, string $category, ?string $zipcode = null, float $complexity = 1.0, string $locale = 'nl'): array
    {
        // 0. Pre-flight Gatekeeper (Semantic Intent Logic)
        $isNonsense = $this->isObviousNonsense($description);
        $hasTechnicalIntent = $this->hasTechnicalIntent($description);

        if ($isNonsense || !$hasTechnicalIntent) {
            $isVague = !$isNonsense;
            return [
                'is_off_topic' => !$hasTechnicalIntent && !$isNonsense,
                'is_incomplete' => $isVague,
                'is_gibberish' => $isNonsense,
                'is_fallback' => false,
                'missing_info_request' => $isNonsense 
                    ? ($locale === 'nl' ? 'De Lead Architect is hier voor engineering, niet voor entertainment.' : 'The Lead Architect is here for engineering, not for entertainment.')
                    : ($locale === 'nl' ? 'De Lead Architect heeft een technische omschrijving nodig om een raming te maken.' : 'The Lead Architect needs a technical description to make a simulation.'),
                'projected_cost' => 0,
                'budget_sections' => [],
                'ai_insights' => $isNonsense ? 'Wartaal gedetecteerd.' : 'Onvoldoende technische intentie.',
            ];
        }

        // 1. Check for existing identical simulation (Fingerprint lookup with v9 Cache Buster)
        $cacheVersion = 'v9';
        $simulationHash = md5($cacheVersion . trim($description) . $category . ($zipcode ?? '') . $complexity);
        $existing = OfferSimulation::where('simulation_hash', $simulationHash)
            ->where('created_at', '>=', now()->subHours(48))
            ->first();

        if ($existing) {
            return $existing->results;
        }

        // 2. Fetch Context (Similarity, Catalog, Memory)
        $similarProjects = $this->similarityService->findSimilar($category, $zipcode, 3, $description);
        
        if ($similarProjects->isEmpty()) {
            return [
                'is_off_topic' => false,
                'is_incomplete' => true,
                'is_gibberish' => false,
                'is_fallback' => false,
                'missing_info_request' => $locale === 'nl' 
                    ? "Er is onvoldoende historie in CAFCA gevonden voor '{$category}' om een betrouwbare raming te genereren."
                    : "Insufficient CAFCA history found for '{$category}' to generate a reliable estimate.",
                'projected_cost' => 0,
                'budget_sections' => [],
                'ai_insights' => 'Geen historische basis gevonden.',
            ];
        }

        // 3. Extract Lessons from the Past (DNA Memory)
        $lessonsContext = collect($similarProjects)->map(function($p) {
            $l = $p['lessons'] ?? null;
            if (!$l) return null;
            return "- Project [{$p['id']}]: Eficiëntie {$l['efficiency']}%. " . 
                   ($l['pitfall'] ? "Valkuil (te vermijden): {$l['pitfall']}. " : "") . 
                   ($l['golden_rule'] ? "Succesfactor (te volgen): {$l['golden_rule']}." : "");
        })->filter()->implode("\n");

        $projectNames = collect($similarProjects)->map(fn($p) => "- {$p['name']} ({$p['city']}, {$p['year']})")->implode("\n");

        $activeCatalog = MirrorMaterial::where('fl_active', 1)
            ->where(function($q) use ($category, $description) {
                $terms = explode(' ', str_replace(['/', '&'], ' ', $category . ' ' . $description));
                foreach ($terms as $term) {
                    if (strlen($term) > 3) $q->orWhere('description', 'like', "%{$term}%");
                }
            })
            ->limit(30)->get(['id', 'ref', 'description', 'cost_price', 'category_ai', 'tags'])->toArray();

        $profile = $this->getMamoProfile($description, $category, $zipcode);

        // 3. Prepare Gemini API Call
        $apiKey = config('services.gemini.key');
        $baseUrl = config('services.gemini.url', 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent');
        $url = "{$baseUrl}?key={$apiKey}";

        if (!$apiKey) {
            return $this->generateFallbackSimulation(['description' => $description, 'category' => $category, 'zipcode' => $zipcode, 'complexity' => $complexity, 'similar_projects' => $similarProjects]);
        }

        $systemInstruction = $locale === 'nl' 
            ? 'U bent de "Lead Architect" van Claesen Verlichting. Gebruik uw expertise om een CAFCA-simulatie te maken die fouten uit het verleden vermijdt.'
            : 'You are the "Lead Architect" of Claesen Verlichting. Create a CAFCA simulation that avoids past mistakes.';

        $systemText = <<<EOT
{$systemInstruction}

REFERENTIEPROJECTEN UIT CAFCA:
{$projectNames}

LESSONS FROM THE PAST (DO'S AND DON'TS):
{$lessonsContext}

TECHNISCHE STANDAARDEN:
- SPORT: 4-6 masten (18m), LED. Budget: €45.000 - €95.000.
- INDUSTRIE: High-bays elke 6m. Budget: €15.000 - €40.000 per 1000m2.
- WEGEN: Masten elke 25m. Budget: €2.500 - €4.500 per mast.

INSTRUCTIONS:
1. De SWOT (DAFO) MOET direct verwijzen naar de 'Lessons from the Past'. Als we in een vorig project marge verloren op 'hoogtewerkers', vermeld dit dan als AMENAZA/BEDREIGING.
2. De Strategische CAME moet concrete acties bevatten om historische valkuilen te vermijden.
3. Vul "assumptions_made" in met technische aannames.
4. Alle teksten in het Nederlands (NL).

OUTPUT SCHEMA (JSON):
{
  "is_off_topic": boolean, "is_incomplete": boolean, "is_gibberish": boolean,
  "missing_info_request": "string",
  "assumptions_made": "string",
  "projected_cost": float,
  "breakdown": { "MATERIAAL (M)": float, "ARBEID (A)": float, "MATERIEEL (E)": float, "ONDERAANNEMING (S)": float },
  "budget_sections": [
    { "title": "string", "items": [ { "omschrijving": "...", "ref": "id", "eenheid": "stk|m|h", "hoeveelheid": 0, "eenheidsprijs": 0, "arb_per_eenheid": 0, "source_type": "db_verified|internet" } ] }
  ],
  "intro_text": "...", "outro_text": "...", "swot_table": "...", "came_strategy": "...", "ai_insights": "..."
}
EOT;

        $userInputText = "Projectomschrijving: \"{$description}\"\nCategorie: \"{$category}\"\nPostcode: \"{$zipcode}\"\nComplexiteitsfactor: {$complexity}";

        try {
            $response = Http::post($url, [
                'system_instruction' => ['parts' => [['text' => $systemText]]],
                'contents' => [['parts' => [['text' => $userInputText]]]],
                'generationConfig' => ['response_mime_type' => 'application/json', 'temperature' => 0.1]
            ]);

            if ($response->successful()) {
                $rawText = $response->json()['candidates'][0]['content']['parts'][0]['text'];
                $result = json_decode($rawText, true) ?? [];
                if (array_is_list($result) && !empty($result)) $result = $result[0];

                // VALIDATION: If cost is zero on a technical project, force a logical estimate
                if (($result['projected_cost'] ?? 0) < 1000 && !$isNonsense && $hasTechnicalIntent) {
                    $result['projected_cost'] = str_contains(strtolower($description), 'sport') ? 65000 : 25000;
                    $result['ai_insights'] = "AI generated a minimal safety estimate due to low data granularity.";
                }

                $result = array_merge([
                    'is_off_topic' => false, 'is_incomplete' => false, 'is_gibberish' => false, 'is_fallback' => false,
                    'missing_info_request' => null, 'assumptions_made' => null, 'projected_cost' => 0, 'budget_sections' => [], 'ai_insights' => 'Done.',
                ], $result);

                // 4. 3-Tier Enrichment
                if (!$result['is_off_topic'] && !$result['is_gibberish'] && !empty($result['budget_sections'])) {
                    foreach ($result['budget_sections'] as &$section) {
                        $section['items'] = $this->getThreeTierMaterialSuggestions($section['items'] ?? []);
                    }
                }

                $result['historical_references'] = collect($similarProjects)->map(fn($p) => [
                    'id' => $p['id'] ?? 'UNK',
                    'name' => $p['name'] ?? 'Onbekend project',
                    'year' => $p['year'] ?? '-',
                    'city' => $p['city'] ?? '-'
                ])->toArray();

                // FORCE REAL MAMO FROM PHP (Anti-Hallucination)
                $result['mamo_summary'] = $profile; 
                unset($result['mamo_summary']['label']); // Clean label for UI
                $result['mamo_profile_used'] = $profile['label'];

                // 5. Store in DB
                OfferSimulation::create([
                    'simulation_hash' => $simulationHash, 'description' => $description, 'category' => $category,
                    'zipcode' => $zipcode, 'complexity' => (float) $complexity, 'results' => $result,
                    'historical_context_ids' => collect($similarProjects)->pluck('id')->toArray(),
                ]);

                return $result;
            }
        } catch (\Exception $e) {
            Log::error("Gemini Error: " . $e->getMessage());
        }

        return $this->generateFallbackSimulation(['description' => $description, 'category' => $category, 'zipcode' => $zipcode, 'complexity' => $complexity, 'similar_projects' => $similarProjects]);
    }

    private function getMamoProfile(string $description, string $category, ?string $zipcode = null): array
    {
        $text = strtolower($description . ' ' . $category);
        if (preg_match('/(export|international|dubai|london|germany|france)/i', $text)) return ['M' => 30, 'A' => 100, 'E' => 10, 'S' => 30, 'label' => 'Export'];
        if (str_contains($text, 'sport') || str_contains($text, 'hockey')) return ['M' => 20, 'A' => 80, 'E' => 20, 'S' => 0, 'label' => 'Sport'];
        return ['M' => 30, 'A' => 80, 'E' => 20, 'S' => 0, 'label' => 'SME'];
    }

    private function getThreeTierMaterialSuggestions(array $proposals): array
    {
        return collect($proposals)->map(function ($p) {
            $ref = $p['ref'] ?? null; $source = $p['source_type'] ?? 'internet'; $action = 'Inkoop (Internet)';
            $inCatalog = $ref ? MirrorMaterial::where('id', $ref)->orWhere('ref', $ref)->first() : null;
            if ($inCatalog) { $action = 'Direct uit magazijn'; $source = 'db_verified'; $ref = $inCatalog->id; }
            return [
                'ref' => $ref ?? 'INC-' . strtoupper(substr(md5($p['omschrijving'] ?? 'unk'), 0, 4)),
                'name' => $p['omschrijving'] ?? 'Onbekend', 'quantity' => (float) ($p['hoeveelheid'] ?? 1),
                'unit' => $p['eenheid'] ?? 'stk', 'unit_price' => (float) ($p['eenheidsprijs'] ?? 0),
                'arb_per_eenheid' => (float) ($p['arb_per_eenheid'] ?? 0),
                'line_total' => (float) (($p['hoeveelheid'] ?? 1) * ($p['eenheidsprijs'] ?? 0)),
                'source_type' => $source, 'action' => $action, 'reason' => $p['reason'] ?? 'Algemeen',
            ];
        })->toArray();
    }

    private function generateFallbackSimulation(array $context): array
    {
        return ['is_fallback' => true, 'projected_cost' => 5000, 'budget_sections' => [], 'ai_insights' => 'Fallback mode.'];
    }

    private function isObviousNonsense(string $text): bool
    {
        $text = strtolower(trim($text));
        if (preg_match('/(chiste|joke|funny|haha|asdff|qwer)/i', $text)) return true;
        return strlen($text) < 10;
    }

    private function hasTechnicalIntent(string $text): bool
    {
        $text = strtolower($text);
        $keywords = ['led', 'mast', 'lamp', 'elektra', 'infra', 'hockey', 'sport', 'kabel'];
        foreach ($keywords as $k) { if (str_contains($text, $k)) return true; }
        return strlen($text) > 100;
    }
}
