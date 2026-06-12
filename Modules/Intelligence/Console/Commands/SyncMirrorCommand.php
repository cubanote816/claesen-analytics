<?php

namespace Modules\Intelligence\Console\Commands;

use Illuminate\Console\Command;
use Modules\Intelligence\Services\SyncMirrorDataService;

class SyncMirrorCommand extends Command
{
    protected $signature = 'intelligence:sync-mirror
                            {--full      : Sync all historical data (not just last 6 months)}
                            {--force     : Skip confirmation prompt (use with --full in CI/scripts)}
                            {--materials : Sync the entire material catalog only}
                            {--relations : Sync CAFCA clients/relations only}
                            {--estimates : Sync CAFCA estimate items (offer lines) only}';

    protected $description = 'Synchronize legacy CAFCA data to local MySQL mirror tables.';

    public function handle(SyncMirrorDataService $syncService)
    {
        $full = $this->option('full');

        if ($this->option('materials')) {
            $this->info('Deep-Syncing ALL materials from catalog...');
            $syncService->syncMaterials(true);
            $this->info('Materials sync finished.');
            return;
        }

        if ($this->option('relations')) {
            $this->info('Syncing CAFCA clients (relation table)...');
            $syncService->syncRelations();
            $this->info('Relations sync finished. Count: ' . \Modules\Performance\Models\Mirror\MirrorRelation::count());
            return;
        }

        if ($this->option('estimates')) {
            $this->info('Syncing CAFCA estimate items (offer lines)...');
            $this->warn('AUDITOR GATE: Confirm estimate_item column mapping before syncing production data.');
            $syncService->syncEstimateItems($full);
            $this->info('Estimate items sync finished. Count: ' . \Modules\Performance\Models\Mirror\MirrorEstimateItem::count());
            return;
        }

        if ($full && !$this->option('force') && !$this->confirm('This will sync the entire historical database. Are you sure?')) {
            return;
        }

        $syncService->syncAll($full);
        $this->info('Mirror Sync Finished Successfully.');
    }
}
