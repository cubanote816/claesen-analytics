<?php

namespace Modules\Website\Console;

use Illuminate\Console\Command;
use Modules\Website\Models\Project;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class RegenerateProjectMediaCommand extends Command
{
    protected $signature = 'website:regenerate-media
                            {--collection= : Only regenerate a specific collection (featured_image|gallery)}
                            {--project= : Only regenerate media for this project ID}';

    protected $description = 'Regenerate WebP conversions for all existing project media';

    public function handle(): int
    {
        $query = Media::where('model_type', Project::class);

        if ($collection = $this->option('collection')) {
            $query->where('collection_name', $collection);
        }

        if ($projectId = $this->option('project')) {
            $query->where('model_id', $projectId);
        }

        $total = $query->count();

        if ($total === 0) {
            $this->info('No media found matching the criteria.');
            return self::SUCCESS;
        }

        $this->info("Regenerating conversions for {$total} media items...");
        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $failed = 0;
        $query->chunkById(50, function ($mediaItems) use ($bar, &$failed) {
            foreach ($mediaItems as $media) {
                try {
                    $media->model->regenerateMediaConversions($media);
                } catch (\Throwable $e) {
                    $failed++;
                    $this->newLine();
                    $this->warn("Failed media ID {$media->id}: {$e->getMessage()}");
                }
                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine();

        $done = $total - $failed;
        $this->info("Done: {$done}/{$total} conversions regenerated." . ($failed > 0 ? " {$failed} failed." : ''));

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
