<?php

namespace App\Jobs;

use App\DTOs\EmployeeAiPayload;
use App\Models\Cafca\Employee as LegacyEmployee;
use App\Models\Cafca\Labor;
use App\Models\EmployeeInsight;
use App\Services\GeminiService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class AnalyzeEmployeeJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public string $employeeId;

    /**
     * Create a new job instance.
     */
    public function __construct(string $employeeId)
    {
        $this->employeeId = $employeeId;
    }

    /**
     * Execute the job.
     */
    public function handle(GeminiService $geminiService): void
    {
        Log::info("AnalyzeEmployeeJob: Starting analysis for {$this->employeeId}");

        $legacyEmployee = LegacyEmployee::find($this->employeeId);
        if (!$legacyEmployee) {
            Log::warning("AnalyzeEmployeeJob: Employee not found in legacy DB: {$this->employeeId}");
            return;
        }

        // 1. Fetch Performance Data (Last 6 months)
        $history = Labor::where('employee_id', $this->employeeId)
            ->where('date', '>=', now()->subMonths(6))
            ->orderBy('date', 'desc')
            ->get();

        $performanceData = [
            'total_hours' => $history->sum('hours'),
            'projects_count' => $history->pluck('project_id')->unique()->count(),
            'recent_history' => $history->take(50)->toArray(), // Limit to save on tokens
        ];

        // 2. Prepare Payload & Check Hash
        $payload = new EmployeeAiPayload($this->employeeId, $performanceData);
        $insight = EmployeeInsight::firstOrNew(['employee_id' => $this->employeeId]);

        if ($insight->exists && $insight->last_data_hash === $payload->hash) {
            Log::info("AnalyzeEmployeeJob: Semantic cache hit for {$this->employeeId}");
            $insight->touch();
            return;
        }

        // 3. Request AI Analysis
        // We use Dutch by default as per PROJECT_CONTEXT
        $result = $geminiService->analyzeEmployee($payload->toArray(), 'nl');

        // 4. Update Database
        $insight->fill([
            'archetype_label' => $result['archetype_label'],
            'archetype_icon' => $result['archetype_icon'],
            'efficiency_trend' => $result['efficiency_trend'],
            'burnout_risk_score' => $result['burnout_risk_score'],
            'manager_insight' => $result['manager_insight'],
            'ai_analysis' => $result['analysis'],
            'full_performance_snapshot' => $performanceData,
            'last_data_hash' => $payload->hash,
            'last_audited_at' => now(),
        ]);

        $insight->save();

        Log::info("AnalyzeEmployeeJob: Analysis completed for {$this->employeeId}");
    }
}
