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

        // 1. Fetch Performance Data (Last 6 months for general trends, 3 months for projects)
        $history = Labor::with('project')
            ->where('employee_id', $this->employeeId)
            ->where('date', '>=', now()->subMonths(6))
            ->orderBy('date', 'desc')
            ->get();

        // 1b. Analyze Project History (Last 3 months focus)
        $recentHistory = $history->where('date', '>=', now()->subMonths(3));

        $projectHistory = $recentHistory->groupBy('project_id')
            ->map(function ($entries) {
                $project = $entries->first()->project;
                return [
                    'project_id' => $entries->first()->project_id,
                    'project_name' => $project ? $project->name : 'Unknown Project',
                    'hours_spent' => $entries->sum('hours'),
                    'last_worked' => $entries->max('date'),
                ];
            })
            ->sortByDesc('last_worked')
            ->values()
            ->toArray();

        // Determine "Current Project" based on most recent entry
        $currentProjectEntry = $history->first(); // Since it's ordered by date desc
        // 3. Identify Active Projects (Last 30 days)
        $activeProjects = $history->where('date', '>=', now()->subDays(30))
            ->pluck('project_name') // Assuming Labor model has project_name or join
            ->unique()
            ->values()
            ->toArray();

        // Fallback if no project_name in Labor, load relationship
        if (empty($activeProjects) && $history->isNotEmpty()) {
            $activeProjects = $history->where('date', '>=', now()->subDays(30))
                ->load('project')
                ->pluck('project.name')
                ->unique()
                ->filter()
                ->values()
                ->toArray();
        }

        $performanceData = [
            'total_hours' => $history->sum('hours'),
            'projects_count' => $history->pluck('project_id')->unique()->count(),
            'active_projects' => $activeProjects,
            'recent_projects_3m' => $projectHistory,
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
