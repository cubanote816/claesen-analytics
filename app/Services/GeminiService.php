<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class GeminiService
{
    protected ?string $apiKey;
    protected string $apiUrl;

    public function __construct()
    {
        $this->apiKey = config('services.gemini.key') ?? env('GEMINI_API_KEY');
        $this->apiUrl = config('services.gemini.url') ?? env('GEMINI_API_URL') ?? 'https://generativelanguage.googleapis.com/v1beta/models/gemini-pro:generateContent';
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

        if (empty($this->apiKey)) {
            Log::warning("Gemini API Key is missing. Skipping translation.");
            return $this->fallbackResponse($text, $targetLocales);
        }

        $localesList = implode(', ', $targetLocales);
        $prompt = <<<PROMPT
You are a professional translator and native speaker of these languages: {$localesList}.

Task:
1. Detect the source language of: "{$text}".
2. Translate it accurately into: {$localesList}.

RULES:
- If the source text is already in the target language, keep it as is.
- If the source text is in a DIFFERENT language, you MUST translate it.
- Do NOT just copy the source text for all languages.
- Ensure the tone is professional and suitable for a business context.

Return JSON:
{
  "detected_locale": "ISO 639-1 code",
  "translations": {
    "code": "translated text"
  }
}
PROMPT;

        try {
            $response = \Illuminate\Support\Facades\Http::post($this->apiUrl . "?key=" . $this->apiKey, [
                'contents' => [
                    [
                        'parts' => [
                            ['text' => $prompt]
                        ]
                    ]
                ],
                'generationConfig' => [
                    'response_mime_type' => 'application/json',
                ]
            ]);

            if ($response->failed()) {
                Log::error("Gemini API Error: " . $response->body());
                return $this->fallbackResponse($text, $targetLocales);
            }

            $data = $response->json();
            $content = $data['candidates'][0]['content']['parts'][0]['text'] ?? '{}';
            $result = json_decode($content, true);

            return [
                'detected_locale' => $result['detected_locale'] ?? 'nl',
                'translations' => $result['translations'] ?? [],
            ];
        } catch (\Exception $e) {
            Log::error("Gemini Service Exception: " . $e->getMessage());
            return $this->fallbackResponse($text, $targetLocales);
        }
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
     * 
     * @param array $payload The sanitized employee data.
     * @param string $locale The locale for the response.
     * @return array {'archetype_label': string, 'archetype_icon': string, 'manager_insight': string, 'analysis': string}
     */
    public function analyzeEmployee(array $payload, string $locale = 'nl'): array
    {
        Log::info("Gemini Employee Analysis Request: " . ($payload['employee_id'] ?? 'Unknown'));

        // Mock response based on the "Cerebro" archetypes
        $archetypes = [
            [
                'label' => 'The Diesel',
                'icon' => '🚜',
                'insight' => $locale === 'nl' ? 'Constante prestaties, weinig risico.' : 'Steady performance, low risk.',
            ],
            [
                'label' => 'The Sprinter',
                'icon' => '🏎️',
                'insight' => $locale === 'nl' ? 'Hoge snelheid op korte termijn, let op consistentie.' : 'High short-term speed, watch for consistency.',
            ],
            [
                'label' => 'Road Warrior',
                'icon' => '🛣️',
                'insight' => $locale === 'nl' ? 'Veel onderweg, houdt efficiëntie hoog.' : 'Traveling a lot, keeps efficiency high.',
            ],
        ];

        $selected = $archetypes[array_rand($archetypes)];

        $activeProjects = $payload['performance_data']['active_projects'] ?? [];
        $projectsText = !empty($activeProjects) ? implode(', ', $activeProjects) : 'Unknown';

        return [
            'archetype_label' => $selected['label'],
            'archetype_icon' => $selected['icon'],
            'efficiency_trend' => collect(['UP', 'DOWN', 'STABLE'])->random(),
            'burnout_risk_score' => rand(5, 45),
            'manager_insight' => $selected['insight'],
            'analysis' => $locale === 'nl'
                ? "Gedetailleerde analyse van prestaties over de afgelopen 6 maanden. Actieve projecten: {$projectsText}."
                : "Detailed performance analysis over the last 6 months. Active projects: {$projectsText}.",
        ];
    }
}
