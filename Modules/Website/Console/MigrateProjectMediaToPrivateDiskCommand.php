<?php

namespace Modules\Website\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Modules\Website\Models\Project;
use Spatie\MediaLibrary\Conversions\ConversionCollection;
use Spatie\MediaLibrary\Conversions\FileManipulator;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * F3/CLA-467 of the multi-organization program — docs/ai/adr-multi-organization.md.
 *
 * One-time migration for media rows created before this ticket, when
 * Project::registerMediaCollections() still put originals on the public
 * disk. Moves the physical original file to the 'local' (private) disk,
 * flips the media row's own `disk` column (Spatie stores this per-row, not
 * derived from the current collection config — old rows keep pointing at
 * 'public' forever unless updated here), then regenerates every conversion
 * so the derived files land on 'public' per the new collection config.
 *
 * Idempotent: a row already on 'local' is skipped outright, so running
 * this twice (or after some rows were already migrated one deploy ago) is
 * always safe. Never run against production from this repository — this
 * command exists for CLA-531/525's staging rollout runbook to reference,
 * documented and dry-run-tested here, not executed against real data.
 */
class MigrateProjectMediaToPrivateDiskCommand extends Command
{
    protected $signature = 'website:migrate-media-to-private-disk {--dry-run : Preview without moving files}';

    protected $description = 'Move existing Project media originals from the public disk to the private local disk';

    private const LOCK_KEY = 'website:migrate-media-to-private-disk:lock';

    private const LOCK_SECONDS = 3600;

    public function handle(): int
    {
        $lock = Cache::lock(self::LOCK_KEY, self::LOCK_SECONDS);

        if (! $lock->get()) {
            $this->warn('Another website:migrate-media-to-private-disk run is already in progress. Skipping.');

            return self::SUCCESS;
        }

        try {
            return $this->migrate();
        } finally {
            $lock->release();
        }
    }

    private function migrate(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $query = Media::where('model_type', Project::class)->where('disk', 'public');
        $total = $query->count();

        if ($total === 0) {
            $this->info('No media on the public disk — nothing to migrate.');

            return self::SUCCESS;
        }

        $this->info(($dryRun ? '[DRY RUN] ' : '')."Migrating {$total} media items to the private disk...");
        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $failed = 0;
        $query->chunkById(50, function ($mediaItems) use ($bar, &$failed, $dryRun) {
            foreach ($mediaItems as $media) {
                try {
                    if (! $dryRun) {
                        $this->moveOriginalToPrivateDisk($media);
                    }
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
        $this->info('Done: '.($dryRun ? "{$done}/{$total} would be migrated." : "{$done}/{$total} migrated.").($failed > 0 ? " {$failed} failed." : ''));

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function moveOriginalToPrivateDisk(Media $media): void
    {
        $sourceDisk = Storage::disk('public');
        $targetDisk = Storage::disk('local');
        $path = $media->getPathRelativeToRoot();

        if ($sourceDisk->exists($path)) {
            $targetDisk->put($path, $sourceDisk->readStream($path));
        }

        $media->disk = 'local';
        $media->save();

        // Old conversions were written under the public disk's own layout;
        // regenerate them now so they land under the collection's
        // configured conversions disk ('public', per registerMediaCollections()).
        $conversions = ConversionCollection::createForMedia($media);
        app(FileManipulator::class)->performConversions($conversions, $media, false);

        if ($sourceDisk->exists($path)) {
            $sourceDisk->delete($path);
        }
    }
}
