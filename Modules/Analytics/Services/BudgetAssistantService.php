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

        // 1. Check for existing identical simulation (Fingerprint lookup with v20 Cache Buster)
        // Note: Zipcode is EXCLUDED from hash to ensure technical DNA parity across locations.
        $cacheVersion = 'v20';
        $simulationHash = md5($cacheVersion . trim($description) . $category . $complexity);
        $existing = OfferSimulation::where('simulation_hash', $simulationHash)
            ->where('created_at', '>=', now()->subHours(48))
            ->first();

        $km = $this->calculateDistance($zipcode);
        $result = null;

        if ($existing) {
            $result = $existing->results;
        } else {
            // 2. Fetch Context (Similarity, Catalog, Memory)
            $similarProjects = $this->similarityService->findSimilar($category, $zipcode, 3, $description);
            
            if ($similarProjects->isEmpty()) {
                Log::warning("BudgetAssistant: No similar projects found for '{$category}'. Proceeding with Catalog-only mode.");
            }

            // 2b. FETCH CATEGORIZED STOCK (The "Ground Truth" for the IA)
            $standardCategory = match($category) {
                'Industrial' => 'Industrial',
                'Public Spaces' => 'Public Spaces',
                default => 'Sport',
            };

            $terms = collect(explode(' ', strtolower(trim($description))))
                ->filter(fn($t) => strlen($t) > 3)
                ->take(5)
                ->values();

            $activeCatalog = MirrorMaterial::query()
                ->where('fl_active', true)
                ->where(function($q) use ($terms, $standardCategory) {
                    $q->where('category_ai', $standardCategory);
                    foreach ($terms as $term) {
                        $q->orWhere('description', 'LIKE', "%{$term}%");
                        $q->orWhere('tags', 'LIKE', "%{$term}%");
                    }
                })
                ->orderByRaw("CASE WHEN category_ai = ? THEN 0 ELSE 1 END", [$standardCategory])
                ->limit(35)
                ->get(['id', 'ref', 'description', 'cost_price', 'usage_summary'])
                ->map(fn($m) => "[REF: {$m->ref}] ID: {$m->id} | {$m->description} | Prijs: €{$m->cost_price}")
                ->implode("\n");

            // 3. Extract Context & Run AI
            $lessonsContext = collect($similarProjects)->map(function($p) {
                $l = $p['lessons'] ?? null;
                if (!$l) return null;
                return "- Project [{$p['id']}]: Eficiëntie {$l['efficiency']}%. " . 
                       ($l['pitfall'] ? "Valkuil (te vermijden): {$l['pitfall']}. " : "") . 
                       ($l['golden_rule'] ? "Succesfactor (te volgen): {$l['golden_rule']}." : "");
            })->filter()->implode("\n");

            $projectNames = collect($similarProjects)->map(fn($p) => "- {$p['name']} ({$p['city']}, {$p['year']})")->implode("\n");

            // ... Prepare and Call AI ...
            $apiKey = config('services.gemini.key');
            $baseUrl = config('services.gemini.url', 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent');
            $url = "{$baseUrl}?key={$apiKey}";

            if (!$apiKey) {
                return $this->generateFallbackSimulation(['description' => $description, 'category' => $category, 'zipcode' => $zipcode, 'complexity' => $complexity, 'similar_projects' => $similarProjects]);
            }

            $sanitizedDescription = $this->sanitizeDescriptionForAi($description);
            $userInputText = "--- TECHNISCH ADN ---\nProjectomschrijving: \"{$sanitizedDescription}\"\nCategorie: \"{$category}\"\nComplexiteitsfactor: {$complexity}\n\nOPMERKING: Voer een generieke nationale engineering-raming uit. Locatie-specifieke logistiek wordt door un PHP systeem afgehandeld.";
            
            // Re-use systemText from context or build it
            $systemInstruction = $locale === 'nl' 
                ? 'U bent de "Lead Architect" van Claesen Verlichting. Gebruik uw expertise om een CAFCA-simulatie te maken die fouten uit het verleden vermijdt.'
                : 'You are the "Lead Architect" of Claesen Verlichting. Create a CAFCA simulation that avoids past mistakes.';

            $systemText = <<<EOT
{$systemInstruction}

HIERARCHIE VOOR MATERIAALSELECTIE:
1. WAREHOUSE FIRST: Gebruik EXACTE producten uit de onderstaande lijst als ze passen.
2. MODERNISERING: Als een product in de lijst verouderd is, stel een "Updated Version" voor.
3. EXTERNAL: Als er GEEN match is in de lijst, zoek via internet/AI raming.

HUIDIG MAGAZIJN (STOCK):
{$activeCatalog}

REFERENTIEPROJECTEN UIT CAFCA:
{$projectNames}

LESSONS FROM THE PAST:
{$lessonsContext}

TECHNICAL STANDARDS:
- SPORT: 4-6 masten (18m), LED. Budget: €45k - €95k.
- INDUSTRIAL: High-bay verlichting elke 6m. Focus op magazijn/loods.
- PUBLIC SPACES: Parkverlichting, straatverlichting, pleinen.

INSTRUCTIONS:
1. PRIJSSTABILITEIT: Gebruik de "Price" uit de stocklijst voor Warehouse items.
2. SOURCE ATTRIBUTION: Voor ELK item moet je aangeven waar het vandaan komt (source_location & source_type).
3. De SWOT (DAFO) MOET direct verwijzen naar de 'Lessons from the Past'.
4. Alle teksten in het Nederlands (NL).

OUTPUT SCHEMA (JSON):
{
  "is_off_topic": boolean, "is_incomplete": boolean, "is_gibberish": boolean,
  "missing_info_request": "string",
  "assumptions_made": "string",
  "projected_cost": float,
  "breakdown": { "MATERIAAL (M)": float, "ARBEID (A)": float, "MATERIEEL (E)": float, "ONDERAANNEMING (S)": float },
  "budget_sections": [
    { "title": "string", "items": [ { "omschrijving": "...", "ref": "id", "eenheid": "stk|m|h", "hoeveelheid": 0, "eenheidsprijs": 0, "arb_per_eenheid": 0, "source_type": "warehouse|modernized|external", "source_location": "string" } ] }
  ],
  "intro_text": "...", "outro_text": "...", "swot": { "strengths": [], "weaknesses": [], "opportunities": [], "threats": [] }, "swot_detailed": "...", "came_strategy": "...", "ai_insights": "..."
}
EOT;

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

                    // VALIDATION SAFETY NET
                    if (($result['projected_cost'] ?? 0) < 1000 && !$isNonsense && $hasTechnicalIntent) {
                        $search = strtolower($description . ' ' . $category);
                        $result['projected_cost'] = str_contains($search, 'stadion') ? 850000 : 25000;
                        $result['ai_insights'] = "Safety fallback triggered.";
                    }

                    $result = array_merge([
                        'is_off_topic' => false, 'is_incomplete' => false, 'is_gibberish' => false, 'is_fallback' => false,
                        'missing_info_request' => null, 'assumptions_made' => null, 'projected_cost' => 0, 'budget_sections' => [], 'ai_insights' => 'Done.',
                    ], $result);

                    // 5. Enrichment (Materials & MAMO)
                    if (!$result['is_off_topic'] && !$result['is_gibberish'] && !empty($result['budget_sections'])) {
                        foreach ($result['budget_sections'] as &$section) {
                            $section['items'] = $this->getThreeTierMaterialSuggestions($section['items'] ?? []);
                        }
                    }

                    $profile = $this->getMamoProfile($description, $category, $zipcode);
                    $result['mamo_summary'] = $profile; 
                    unset($result['mamo_summary']['label']);
                    $result['mamo_profile_used'] = $profile['label'];
                    
                    $result['historical_references'] = collect($similarProjects)->map(fn($p) => [
                        'id' => $p['id'] ?? 'UNK',
                        'name' => $p['name'] ?? 'Onbekend project',
                        'year' => $p['year'] ?? '-',
                        'city' => $p['city'] ?? '-'
                    ])->toArray();

                    // SAVE BASE TO DB
                    OfferSimulation::create([
                        'simulation_hash' => $simulationHash, 'description' => $description, 'category' => $category,
                        'zipcode' => $zipcode, 'complexity' => (float) $complexity, 'results' => $result,
                        'historical_context_ids' => collect($similarProjects)->pluck('id')->toArray(),
                    ]);
                }
            } catch (\Exception $e) {
                Log::error("Gemini Error: " . $e->getMessage());
                return $this->generateFallbackSimulation(['description' => $description]);
            }
        }

        // 6. LIVE LOGISTICAL SURCHARGE (Applied to both Cache Hits and New Simulations)
        if ($result && $km >= 15 && !$isNonsense) {
            $baseFee = ($result['projected_cost'] ?? 0) > 500000 ? 5000 : 1200;
            $kmRate = ($result['projected_cost'] ?? 0) > 500000 ? 50 : 15;
            $surcharge = $baseFee + ($kmRate * $km);

            $result['projected_cost'] += $surcharge;
            $result['breakdown']['MATERIEEL (E)'] = ($result['breakdown']['MATERIEEL (E)'] ?? 0) + $surcharge;
            $result['ai_insights'] .= " [Logistieke toeslag van €" . number_format($surcharge, 0, ',', '.') . " voor de afstand van {$km}km live toegepast]";
        }

        return $result ?? $this->generateFallbackSimulation(['description' => $description]);
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
            $ref = $p['ref'] ?? null;
            $type = $p['source_type'] ?? 'external';
            $location = $p['source_location'] ?? ($type === 'warehouse' ? 'Magazijn Balen' : 'Internet');

            // Cross-check with DB if it claims to be Warehouse or has a Ref
            $inCatalog = $ref ? MirrorMaterial::where('id', $ref)->orWhere('ref', $ref)->first() : null;
            
            if ($inCatalog) {
                $type = 'warehouse';
                $location = 'Magazijn Balen';
                $ref = $inCatalog->id;
            }

            $actionMap = [
                'warehouse' => 'Direct uit magazijn (Stock)',
                'modernized' => 'Modernisering (Aanbevolen)',
                'external' => 'Marktonderzoek (Internet)',
            ];

            return [
                'ref' => $ref ?? 'INC-' . strtoupper(substr(md5($p['omschrijving'] ?? 'unk'), 0, 4)),
                'name' => $p['omschrijving'] ?? 'Onbekend',
                'quantity' => (float) ($p['hoeveelheid'] ?? 1),
                'unit' => $p['eenheid'] ?? 'stk',
                'unit_price' => (float) ($p['eenheidsprijs'] ?? 0),
                'arb_per_eenheid' => (float) ($p['arb_per_eenheid'] ?? 0),
                'line_total' => (float) (($p['hoeveelheid'] ?? 1) * ($p['eenheidsprijs'] ?? 0)),
                'source_type' => $type,
                'source_location' => $location,
                'action' => $actionMap[$type] ?? 'Onbekend',
                'reason' => $p['reason'] ?? 'Algemeen',
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

    private function calculateDistance(?string $zipcode): int
    {
        if (!$zipcode) return 50; // Default fallback
        $prefix = (int) substr(preg_replace('/[^0-9]/', '', $zipcode), 0, 2);
        
        switch (true) {
            case ($prefix === 24): return 5;  // Balen/Antwerp East
            case ($prefix === 39 || $prefix === 35): return 25; // Limburg
            case ($prefix >= 20 && $prefix <= 29): return 55; // Antwerp / Mechelen
            case ($prefix >= 30 && $prefix <= 34): return 55; // Leuven / Brabant
            case ($prefix >= 10 && $prefix <= 12): return 65; // Brussels
            case ($prefix >= 90 && $prefix <= 99): return 95; // Gent / East Flanders
            case ($prefix >= 80 && $prefix <= 89): return 135; // West Flanders
            case ($prefix >= 40 && $prefix <= 49): return 60; // Liege
            case ($prefix >= 50 && $prefix <= 59): return 80; // Namur
            case ($prefix >= 60 && $prefix <= 69): return 110; // Hainaut
            case ($prefix >= 70 && $prefix <= 79): return 90; // Mons
            default: return 50;
        }
    }

    private function sanitizeDescriptionForAi(string $description): string
    {
        // Redact common city names that trigger bias
        $cities = ['Balen', 'Gent', 'Antwerpen', 'Brussel', 'Brugge', 'Hasselt', 'Leuven', 'Mechelen', 'Ghelamco'];
        $pattern = '/\b(' . implode('|', $cities) . ')\b/i';
        $description = preg_replace($pattern, '[LOCATIE_REDACTED]', $description);

        // Redact zipcodes (4 digits)
        $description = preg_replace('/\b[0-9]{4}\b/', '[POSTCODE_REDACTED]', $description);

        return $description;
    }
}
