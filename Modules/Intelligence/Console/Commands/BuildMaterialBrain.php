<?php

namespace Modules\Intelligence\Console\Commands;

use Illuminate\Console\Command;
use Modules\Performance\Models\Mirror\MirrorMaterial;
use Modules\Intelligence\Services\MaterialIntelligenceService;

class BuildMaterialBrain extends Command
{
    /**
     * @var string
     */
    protected $signature = 'intelligence:build-material-brain {--limit=20} {--all}';

    /**
     * @var string
     */
    protected $description = 'Analyze historical usage of materials and classify them using AI.';

    public function handle(MaterialIntelligenceService $intelligenceService)
    {
        $this->info('Starting Material Intelligence Learning Process...');

        $query = MirrorMaterial::query();

        if (!$this->option('all')) {
            $query->whereNull('last_learned_at');
        }

        $limit = $this->option('limit');
        $materials = $query->limit($limit)->get();

        if ($materials->isEmpty()) {
            $this->info('No materials found to learn from.');
            return;
        }

        $bar = $this->output->createProgressBar(count($materials));
        $bar->start();

        foreach ($materials as $material) {
            $success = $intelligenceService->learn($material);
            
            if ($success) {
                // $this->info(" Learned: {$material->description}");
            } else {
                $this->error(" Failed: {$material->description}");
            }
            
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info('Learning process finished.');
    }
}
