<?php

namespace Modules\Intelligence\Console\Commands;

use Illuminate\Console\Command;
use Modules\Intelligence\Services\SyncMirrorDataService;

class SyncMirrorCommand extends Command
{
    protected $signature = 'intelligence:sync-mirror {--full : Sync all historical data} {--materials : Sync the entire material catalog}';
    protected $description = 'Synchronize legacy CAFCA data to local MySQL mirror tables.';

    public function handle(SyncMirrorDataService $syncService)
    {
        $full = $this->option('full');

        if ($this->option('materials')) {
            $this->info('Deep-Syncing ALL materials from catalog...');
            $syncService->syncMaterials(true);
        } else {
            if ($full && !$this->confirm('This will sync the entire historical database. Are you sure?')) {
                return;
            }
            $syncService->syncAll($full);
        }

        $this->info('Mirror Sync Finished Successfuly.');
    }
}
