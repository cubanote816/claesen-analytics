<?php

namespace Modules\Website\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Modules\Website\Models\Project;
use Spatie\MediaLibrary\Conversions\ConversionCollection;
use Spatie\MediaLibrary\Conversions\FileManipulator;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class RegenerateProjectMediaCommand extends Command
{
    protected $signature = 'website:regenerate-media
                            {--collection= : Only regenerate a specific collection (featured_image|gallery)}
                            {--project= : Only regenerate media for this project ID}';

    protected $description = 'Regenerate WebP/AVIF conversions for all existing project media';

    private const LOCK_KEY = 'website:regenerate-media:lock';

    private const LOCK_SECONDS = 3600;

    public function handle(): int
    {
        // F3/CLA-467 "regeneración idempotente y sin solapamiento": two
        // concurrent runs (an admin re-running it manually while a
        // scheduled/previous invocation is still churning through media)
        // would otherwise both write the same conversion files at once.
        $lock = Cache::lock(self::LOCK_KEY, self::LOCK_SECONDS);

        if (! $lock->get()) {
            $this->warn('Another website:regenerate-media run is already in progress. Skipping.');

            return self::SUCCESS;
        }

        try {
            return $this->regenerate();
        } finally {
            $lock->release();
        }
    }

    private function regenerate(): int
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
                    $conversions = ConversionCollection::createForMedia($media);
                    app(FileManipulator::class)->performConversions($conversions, $media, false);
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
