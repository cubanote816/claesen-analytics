<?php

namespace App\Services;

use App\Jobs\AuditProjectJob;
use App\Models\Cafca\Project;
use Illuminate\Bus\Batch;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;
use Throwable;

class CafcaSyncService
{
    /**
     * Dispatch an audit batch for all or specific projects.
     *
     * @param array $projectIds Optional list of project IDs. If empty, audits all.
     * @return Batch|null
     */
    public function auditProjects(array $projectIds = []): ?Batch
    {
        $query = Project::query();

        if (!empty($projectIds)) {
            $query->whereIn('project_id', $projectIds);
        }

        // Get IDs to process
        // Chunking for memory efficiency if processing ALL
        $jobs = [];
        $query->chunk(100, function ($projects) use (&$jobs) {
            foreach ($projects as $project) {
                $jobs[] = new AuditProjectJob($project->project_id);
            }
        });

        if (empty($jobs)) {
            Log::info("CafcaSyncService: No projects found to audit.");
            return null;
        }

        $batch = Bus::batch($jobs)
            ->then(function (Batch $batch) {
                // All jobs completed successfully...
                Log::info("Audit Batch {$batch->id} completed successfully.");
            })
            ->catch(function (Batch $batch, Throwable $e) {
                // First batch job failure detected...
                Log::error("Audit Batch {$batch->id} failed: " . $e->getMessage());
            })
            ->finally(function (Batch $batch) {
                // The batch has finished executing...
                Log::info("Audit Batch {$batch->id} finished.");
            })
            ->name('Project Intelligence Audit')
            ->onQueue('analysis') // Make sure this queue exists/is processed
            ->dispatch();

        return $batch;
    }
}
