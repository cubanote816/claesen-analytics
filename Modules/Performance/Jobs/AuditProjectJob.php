<?php

namespace Modules\Performance\Jobs;

use Modules\Performance\DTOs\ProjectAiPayload;
use Modules\Cafca\Models\Project;
use Modules\Performance\Models\ProjectInsight;
use Modules\Performance\Services\GeminiService;
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
    public function handle(GeminiService $geminiService, \Modules\Performance\Services\ProjectStrategyService $strategyService): void
    {
        if ($this->batch() && $this->batch()->cancelled()) {
            return;
        }

        $project = Project::find($this->projectId);
        if (!$project) {
            Log::warning("AuditProjectJob: Project not found: {$this->projectId}");
            return;
        }

        $insight = ProjectInsight::firstOrNew(['project_id' => $this->projectId]);

        // 1. Determine if we need a Strategic SWOT (Finished) or Standard Audit (Active)
        // Check fl_active from Mirror table if available, or assume from project metadata
        $mirror = \Modules\Performance\Models\Mirror\MirrorProject::find($this->projectId);
        $isFinished = $mirror ? !$mirror->fl_active : false;

        if ($isFinished) {
            // STRATEGIC ANALYSIS (SWOT)
            // Skip if already has a strategic audit in full_dna
            if (isset($insight->full_dna['strategic_audit']) && $insight->full_dna['strategic_audit']) {
                Log::info("AuditProjectJob: Strategic audit already exists for finished project {$this->projectId}");
                return;
            }

            $analysis = $strategyService->generateSwotAnalysis($this->projectId);
            
            if ($analysis['success']) {
                $data = $analysis['data'];
                $insight->ai_summary = $data['user_summary'] ?? '';
                $insight->golden_rule = $data['golden_lesson'] ?? '';
                $insight->full_dna = [
                    'swot' => $data['swot'] ?? [],
                    'strategic_audit' => true,
                    'analyzed_at' => now()->toDateTimeString(),
                ];
                $insight->efficiency_score = 100; // Finalized score
            }
        } else {
            // STANDARD OPERATIONAL AUDIT (Active)
            $rawData = $project->toArray();
            $payload = new \Modules\Performance\DTOs\ProjectAiPayload($this->projectId, $rawData);

            if ($insight->exists && $insight->last_data_hash === $payload->hash) {
                Log::info("AuditProjectJob: Hash match. Skipping active project {$this->projectId}");
                return;
            }

            $context = new \Modules\Performance\DTOs\GeminiContextDTO('nl');
            $result = $geminiService->analyzeProject($payload->toArray(), $context);

            $insight->efficiency_score = $result['efficiency_score'] ?? 0;
            $insight->ai_summary = $result['ai_summary'] ?? '';
            $insight->last_data_hash = $payload->hash;
        }

        $insight->last_audited_at = now();
        $insight->save();
    }
}
