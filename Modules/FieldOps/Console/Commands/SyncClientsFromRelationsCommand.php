<?php

namespace Modules\FieldOps\Console\Commands;

use Illuminate\Console\Command;
use Modules\FieldOps\Services\ClientRelationSyncService;

class SyncClientsFromRelationsCommand extends Command
{
    protected $signature = 'fieldops:sync-clients-from-relations';

    protected $description = 'Import/update FoClient records from CAFCA relations already mirrored locally (tp_customer=1 only).';

    public function handle(ClientRelationSyncService $service): int
    {
        $this->info('Syncing FieldOps clients from mirrored CAFCA relations...');

        $count = $service->sync();

        $this->info("Done. {$count} client(s) processed.");

        return self::SUCCESS;
    }
}
