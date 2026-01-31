<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class GeminiService
{
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
