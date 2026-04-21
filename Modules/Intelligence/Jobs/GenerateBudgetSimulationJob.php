<?php

namespace Modules\Intelligence\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Modules\Performance\Models\ProjectInsight;
use Modules\Cafca\Models\Employee;
use Illuminate\Support\Facades\Http;

class GenerateBudgetSimulationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 120;

    protected array $requestData;
    protected string $locale;
    protected string $cacheKey;

    public function __construct(array $requestData, string $locale, string $cacheKey)
    {
        $this->requestData = $requestData;
        $this->locale = $locale;
        $this->cacheKey = $cacheKey;
    }

    public function handle()
    {
        try {
            $zipcode = $this->requestData['zipcode'] ?? '0000';
            $category = $this->requestData['category'] ?? '';

            // 1. RAG Retrieval Step: Find nearest neighbors
            $zipPrefix = substr($zipcode, 0, 2);
            
            $historicalProjects = ProjectInsight::whereJsonContains('full_dna->category', $category)
                ->where('full_dna->zipcode', 'LIKE', $zipPrefix . '%')
                ->orderByDesc('last_audited_at')
                ->take(5)
                ->get();

            // Serialize historical financial data
            $historicalContext = $historicalProjects->map(function ($insight) {
                $dna = $insight->full_dna;
                return sprintf(
                    "Project ID: %s. Margin: %s EUR. Leak: %s. Efficiency: %s",
                    $dna['project_id'] ?? 'Unknown',
                    $dna['financials']['margin'] ?? 'N/A',
                    $insight->critical_leak ?? 'None',
                    $insight->efficiency_score ?? 'N/A'
                );
            })->implode("\n");

            if (empty($historicalContext)) {
                $historicalContext = "No historical data found for category: {$category} and zipcode prefix: {$zipPrefix}.";
            }

            // 2. Fetch technicians by logic (Employees via zipcode proximity)
            $techs = Employee::where('zip', 'LIKE', $zipPrefix . '%')
                ->take(3)
                ->pluck('name')
                ->implode(', ');

            if (empty($techs)) {
                $techs = "No available technicians in this region.";
            }

            // 3. Prompt Construction
            $prompt = <<<PROMPT
You are a factual, strict backend auditor for Claesen Verlichting. You tolerate zero complacency.
You are evaluating a NEW PROPOSED OFFER with the following parameters:
Category: {$category}
Target Zipcode: {$zipcode}

HISTORICAL CONTEXT (RAG):
Here are similar historical projects from the database:
{$historicalContext}

TECHNICIAN AVAILABILITY:
Suggested technicians near this zipcode: {$techs}

INSTRUCTIONS:
1. Briefly evaluate the risk of this new offer category based on historical margins and leaks.
2. Provide a strict, factual recommendation in the third person. Do not use conversational filler.
3. If historical margin is negative, warn strongly.
4. Output your analysis securely and professionally. 
Language: Translate your final response to exactly this locale: {$this->locale}
PROMPT;

            $result = $this->callAiNative($prompt);
            
            // Store result in cache for 48 hours (48 * 60 * 60 seconds)
            Cache::put($this->cacheKey, $result, now()->addHours(48));

        } catch (\Exception $e) {
            Log::error("GenerateBudgetSimulationJob Failed: " . $e->getMessage());
            Cache::put($this->cacheKey, "Error during analysis execution: " . $e->getMessage(), now()->addMinutes(5));
        }
    }

    private function callAiNative(string $prompt): string
    {
        $apiUrl = config('services.gemini.url') ?? env('GEMINI_API_URL') ?? 'https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent';
        $apiKey = config('services.gemini.key') ?? env('GEMINI_API_KEY');

        $response = Http::post($apiUrl . "?key=" . $apiKey, [
            'contents' => [
                [
                    'parts' => [
                        ['text' => $prompt]
                    ]
                ]
            ],
            'generationConfig' => [
                'temperature' => 0.1,
                'topK' => 10,
            ]
        ]);

        if ($response->failed()) {
            throw new \Exception("Gemini API Error: " . $response->body());
        }

        $data = $response->json();
        return $data['candidates'][0]['content']['parts'][0]['text'] ?? 'No analysis returned.';
    }
}
