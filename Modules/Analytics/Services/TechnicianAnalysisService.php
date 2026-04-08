<?php

namespace Modules\Analytics\Services;

use Modules\Cafca\Models\LegacyEmployee;
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
        $cacheKey = 'technician_archetype_' . md5($employeeId);
        
        return Cache::remember($cacheKey, now()->addDays(7), function() use ($employeeId, $employeeName) {
            
            // Gather last 6 months of data
            $labors = Labor::where('employee_id', $employeeId)
                ->where('date', '>=', now()->subMonths(6))
                ->get();
                
            $totalHours = $labors->sum('hours') ?? 0;
            $totalProjects = $labors->pluck('project_id')->unique()->count();
            
            // Minimal statistical logic for Gemini Context
            $historyData = [
                'total_hours_last_6_months' => $totalHours,
                'unique_projects_assigned' => $totalProjects,
                'avg_hours_per_month' => $totalHours / 6
            ];

            $historyJson = json_encode($historyData);

            $prompt = <<<PROMPT
ROL: Consultor Senior de Operaciones y RRHH.
TAREA: Analizar historial de 6 meses del técnico "{$employeeName}".

DATOS (JSON): {$historyJson}

DEFINICIONES DE ARQUETIPOS (Lógica de Negocio):
- 'The Sprinter' 🏎️: Alta eficiencia puntual, inconsistente a largo plazo.
- 'The Diesel' 🚜: Alta Eficiencia (>90%), ritmo constante, pocos viajes.
- 'Road Warrior' 🛣️: Viajes >15% del total, mantiene alta eficiencia (Valioso).
- 'Burnout Risk' 🚑: Horas > 180/mes O eficiencia cayendo drásticamente.
- 'Need Coaching' 🎓: Eficiencia <60% sin justificación.

SALIDA JSON ESTRICTA (sin formato markdown adicional, unicamente el objeto parsable):
{
    "archetype_label": "String (Ej: The Diesel)",
    "archetype_icon": "Emoji",
    "efficiency_trend": "UP|DOWN|STABLE",
    "burnout_risk_score": Integer (0-100),
    "manager_insight": "Consejo directo en ESPAÑOL (Max 30 palabras)."
}
PROMPT;

            $apiUrl = config('services.gemini.url') ?? env('GEMINI_API_URL') ?? 'https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent';
            $apiKey = config('services.gemini.key') ?? env('GEMINI_API_KEY');

            try {
                $response = Http::post($apiUrl . "?key=" . $apiKey, [
                    'contents' => [['parts' => [['text' => $prompt]]]],
                    'generationConfig' => [
                        'temperature' => 0.1,
                        'responseMimeType' => 'application/json' // Explicit structural guarantee
                    ]
                ]);

                if ($response->failed()) {
                    Log::error("Technician Gemini Error: " . $response->body());
                    throw new \Exception("Analysis failed.");
                }

                $data = $response->json();
                $text = $data['candidates'][0]['content']['parts'][0]['text'] ?? '{}';
                
                // Clean markdown if accidentally returned
                $text = str_replace(['```json', '```'], '', $text);
                
                return json_decode(trim($text), true) ?? static::fallbackProfile();

            } catch (\Exception $e) {
                Log::error("TechnicianAnalysisService Exception: " . $e->getMessage());
                return static::fallbackProfile();
            }
        });
    }
    
    private static function fallbackProfile(): array
    {
        return [
            "archetype_label" => "Unknown",
            "archetype_icon" => "❓",
            "efficiency_trend" => "STABLE",
            "burnout_risk_score" => 0,
            "manager_insight" => "Error generacional IA. Analizar métricas manualmente."
        ];
    }
}
