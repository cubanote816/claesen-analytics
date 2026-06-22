<?php

namespace Modules\Performance\Services;

use Modules\Cafca\Models\Labor;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class TechnicianAnalysisService
{
    /**
     * Retrieves an Employee Archetype profile using Gemini.
     */
    public function analyzeTechnician(string $employeeId, string $employeeName): array
    {
        $cacheKey = 'technician_archetype_v2_' . md5($employeeId);

        $result = Cache::remember($cacheKey, now()->addDays(7), function () use ($employeeId, $employeeName) {
            $locale = $this->resolveLocale();

            $labors = Labor::where('employee_id', $employeeId)
                ->where('date', '>=', now()->subMonths(6))
                ->get();

            $totalHours    = $labors->sum('hours') ?? 0;
            $totalProjects = $labors->pluck('project_id')->unique()->count();

            $historyData = [
                'total_hours_last_6_months' => $totalHours,
                'unique_projects_assigned'  => $totalProjects,
                'avg_hours_per_month'       => $totalHours / 6,
            ];

            $prompt = $this->buildPrompt($locale, $employeeName, json_encode($historyData));

            $apiUrl = config('services.gemini.url') ?? env('GEMINI_API_URL') ?? 'https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent';
            $apiKey = config('services.gemini.key') ?? env('GEMINI_API_KEY');

            try {
                $response = Http::post($apiUrl . '?key=' . $apiKey, [
                    'contents'         => [['parts' => [['text' => $prompt]]]],
                    'generationConfig' => [
                        'temperature'      => 0.1,
                        'responseMimeType' => 'application/json',
                    ],
                ]);

                if ($response->failed()) {
                    Log::error('Technician Gemini Error: ' . $response->body());
                    throw new \Exception('Analysis failed.');
                }

                $data = $response->json();
                $text = $data['candidates'][0]['content']['parts'][0]['text'] ?? '{}';
                $text = str_replace(['```json', '```'], '', $text);

                return json_decode(trim($text), true) ?? static::fallbackProfile();

            } catch (\Exception $e) {
                Log::error('TechnicianAnalysisService Exception: ' . $e->getMessage());
                return static::fallbackProfile();
            }
        });

        // Persist to database to enable Infolist indicators and reporting.
        // v2 cache key invalidates existing entries on deploy; no automatic backfill
        // of already-persisted EmployeeInsight rows — they are updated on next call.
        \Modules\Performance\Models\EmployeeInsight::updateOrCreate(
            ['employee_id' => $employeeId],
            [
                'archetype_label'  => $result['archetype_label'] ?? 'Unknown',
                'archetype_icon'   => $result['archetype_icon'] ?? '❓',
                'efficiency_trend' => $result['efficiency_trend'] ?? 'STABLE',
                'burnout_risk_score' => (int) ($result['burnout_risk_score'] ?? 0),
                'manager_insight'  => $result['manager_insight'] ?? '',
                'last_audited_at'  => now(),
            ]
        );

        return $result;
    }

    protected function resolveLocale(): string
    {
        $raw = (string) config('performance.ai_insight_locale', 'nl');
        return in_array($raw, ['nl', 'en'], true) ? $raw : 'nl';
    }

    protected function buildPrompt(string $locale, string $employeeName, string $historyJson): string
    {
        $canonical = in_array($locale, ['nl', 'en'], true) ? $locale : 'nl';

        if ($canonical === 'en') {
            return <<<PROMPT
ROLE: Senior Operations & HR Consultant.
TASK: Analyze the 6-month performance history of technician "{$employeeName}".

DATA (JSON): {$historyJson}

ARCHETYPE DEFINITIONS (Business Logic):
- 'The Sprinter' 🏎️: High punctual efficiency, inconsistent over the long term.
- 'The Diesel' 🚜: High efficiency (>90%), constant pace, few trips.
- 'Road Warrior' 🛣️: Trips >15% of total, maintains high efficiency (valuable).
- 'Burnout Risk' 🚑: Hours > 180/month OR sharply declining efficiency.
- 'Need Coaching' 🎓: Efficiency <60% without justification.

STRICT JSON OUTPUT (no additional markdown formatting, only the parseable object):
{
    "archetype_label": "String (e.g.: The Diesel)",
    "archetype_icon": "Emoji",
    "efficiency_trend": "UP|DOWN|STABLE",
    "burnout_risk_score": Integer (0-100),
    "manager_insight": "Direct recommendation in ENGLISH (max 30 words)."
}
PROMPT;
        }

        // nl (default and fallback)
        return <<<PROMPT
ROL: Senior Operations & HR Consultant.
TAAK: Analyseer de prestaties van de afgelopen 6 maanden van technicus "{$employeeName}".

GEGEVENS (JSON): {$historyJson}

ARCHETYPEDEFINITIES (Bedrijfslogica):
- 'The Sprinter' 🏎️: Hoge punctuele efficiëntie, inconsistent op lange termijn.
- 'The Diesel' 🚜: Hoge efficiëntie (>90%), constant ritme, weinig reizen.
- 'Road Warrior' 🛣️: Reizen >15% van totaal, behoudt hoge efficiëntie (waardevol).
- 'Burnout Risk' 🚑: Uren > 180/maand OF sterk dalende efficiëntie.
- 'Need Coaching' 🎓: Efficiëntie <60% zonder rechtvaardiging.

STRIKTE JSON-UITVOER (geen extra markdown-opmaak, alleen het parseerbare object):
{
    "archetype_label": "String (bijv: The Diesel)",
    "archetype_icon": "Emoji",
    "efficiency_trend": "UP|DOWN|STABLE",
    "burnout_risk_score": Integer (0-100),
    "manager_insight": "Directe aanbeveling in het NEDERLANDS (max. 30 woorden)."
}
PROMPT;
    }

    private static function fallbackProfile(): array
    {
        return [
            'archetype_label'    => 'Unknown',
            'archetype_icon'     => '❓',
            'efficiency_trend'   => 'STABLE',
            'burnout_risk_score' => 0,
            'manager_insight'    => 'AI analysis unavailable. Review metrics manually.',
        ];
    }
}
