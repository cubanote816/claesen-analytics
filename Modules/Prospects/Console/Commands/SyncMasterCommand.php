<?php

namespace Modules\Prospects\Console\Commands;

use Illuminate\Console\Command;
use Modules\Prospects\Jobs\MasterSyncJob;

class SyncMasterCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'prospects:sync-master';

    /**
     * The console command description.
     */
    protected $description = 'Trigger the master synchronization process for all federations.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting Master Synchronization Job...');
        
        MasterSyncJob::dispatch();
        
        $this->info('MasterSyncJob has been dispatched to the queue.');
    }
}
