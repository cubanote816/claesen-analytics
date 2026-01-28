<?php

namespace App\Jobs;

use App\DTOs\ProjectAiPayload;
use App\Models\Cafca\Project;
use App\Models\ProjectInsight;
use App\Services\GeminiService;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class AuditProjectJob implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public string $projectId;

    /**
     * Create a new job instance.
     */
    public function __construct(string $projectId)
    {
        $this->projectId = $projectId;
    }

    /**
     * Execute the job.
     */
    public function handle(GeminiService $geminiService): void
    {
        if ($this->batch() && $this->batch()->cancelled()) {
            return;
        }

        $project = Project::find($this->projectId);
        if (!$project) {
            Log::warning("AuditProjectJob: Project not found: {$this->projectId}");
            return;
        }

        // 1. Prepare Data & DTO
        // In a real scenario, we would load relationships and structure this data better.
        $rawData = $project->toArray();
        $payload = new ProjectAiPayload($this->projectId, $rawData);

        // 2. Semantic Caching Check
        $insight = ProjectInsight::firstOrNew(['project_id' => $this->projectId]);

        if ($insight->exists && $insight->last_data_hash === $payload->hash) {
            Log::info("AuditProjectJob: Accessor hash match. Skipping API call for {$this->projectId}");
            $insight->touch(); // Update updated_at to show we checked
            $insight->save();
            return;
        }

        // 3. Call Gemini API
        // Determine locale - passing 'nl' context via DTO.
        $context = new \App\DTOs\GeminiContextDTO('nl');
        $result = $geminiService->analyzeProject($payload->toArray(), $context);

        // 4. Update Insight Model
        $insight->efficiency_score = $result['efficiency_score'];
        $insight->ai_summary = $result['summary'];
        $insight->last_data_hash = $payload->hash;
        $insight->last_audited_at = now();
        $insight->save();
    }
}
