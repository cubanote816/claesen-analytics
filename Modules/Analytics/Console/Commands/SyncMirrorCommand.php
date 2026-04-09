<?php

namespace Modules\Analytics\Console\Commands;

use Illuminate\Console\Command;
use Modules\Analytics\Services\SyncMirrorDataService;

class SyncMirrorCommand extends Command
{
    protected $signature = 'analytics:sync-mirror {--full : Sync all historical data}';
    protected $description = 'Synchronize legacy CAFCA data to local MySQL mirror tables.';

    public function handle(SyncMirrorDataService $syncService)
    {
        $this->info('Starting Mirror Sync...');
        $full = $this->option('full');

        if ($full && !$this->confirm('This will sync the entire historical database. Are you sure?')) {
            return;
        }

        $syncService->syncAll($full);

        $this->info('Mirror Sync Finished Successfuly.');
    }
}
