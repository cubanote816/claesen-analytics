<?php

namespace Modules\Intelligence\Services;

use Illuminate\Support\Facades\Log;

class GeminiService
{
    protected ?string $apiKey;
    protected string $apiUrl;

    public function __construct()
    {
        $this->apiKey = config('services.gemini.key');
        $this->apiUrl = config('services.gemini.url') ?: 'https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash-latest:generateContent';
    }

    /**
     * Generic method to call Gemini API with JSON output.
     */
    public function generateStructuredResponse(string $prompt): array
    {
        if (empty($this->apiKey)) {
            Log::error("Gemini API Key is missing.");
            return [];
        }

        try {
            $response = \Illuminate\Support\Facades\Http::post($this->apiUrl . "?key=" . $this->apiKey, [
                'contents' => [['parts' => [['text' => $prompt]]]],
                'generationConfig' => [
                    'response_mime_type' => 'application/json',
                    'temperature' => 0.2,
                ]
            ]);

            if ($response->failed()) {
                Log::error("Gemini API Error: " . $response->body());
                return [];
            }

            $data = $response->json();
            $content = $data['candidates'][0]['content']['parts'][0]['text'] ?? '{}';
            return json_decode($content, true) ?: [];
        } catch (\Exception $e) {
            Log::error("Gemini Exception: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Translate multi-language content and detect source language.
     * 
     * @param string $text The text to translate.
     * @param array $targetLocales List of locales to translate to (e.g. ['nl', 'en']).
     * @return array {'detected_locale': string, 'translations': array<string, string>}
     */
    public function translateAndDetect(string $text, array $targetLocales): array
    {
        if (empty(trim($text))) {
            return [
                'detected_locale' => app()->getLocale(),
                'translations' => array_combine($targetLocales, array_fill(0, count($targetLocales), '')),
            ];
        }

        $localesList = implode(', ', $targetLocales);
        $prompt = <<<PROMPT
Task: Detect source language of "{$text}" and translate to: {$localesList}.
Return JSON: {"detected_locale": "ISO", "translations": {"code": "text"}}
PROMPT;

        $result = $this->generateStructuredResponse($prompt);

        return [
            'detected_locale' => $result['detected_locale'] ?? 'nl',
            'translations' => $result['translations'] ?? [],
        ];
    }

    protected function fallbackResponse(string $text, array $targetLocales): array
    {
        return [
            'detected_locale' => app()->getLocale(),
            'translations' => array_combine($targetLocales, array_fill(0, count($targetLocales), $text)),
        ];
    }

    /**
     * Analyze technician behavior and return archetypes.
     */
    public function analyzeEmployee(array $payload, string $locale = 'nl'): array
    {
        $prompt = "Analyze this employee data: " . json_encode($payload) . ". Return JSON with archetype_label, archetype_icon, manager_insight, analysis in {$locale}.";
        return $this->generateStructuredResponse($prompt);
    }

    /**
     * Analyze project financial data.
     */
    public function analyzeProject(array $payload, $context): array
    {
        $locale = $context->locale ?? 'nl';
        $prompt = "Analyze this project data: " . json_encode($payload) . ". Return JSON: {efficiency_score: int, ai_summary: string, critical_leak: string, golden_rule: string} in {$locale}.";
        
        $result = $this->generateStructuredResponse($prompt);
        $result['full_dna'] = $payload;
        
        return $result;
    }
}
