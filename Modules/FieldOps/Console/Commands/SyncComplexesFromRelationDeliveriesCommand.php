<?php

namespace Modules\FieldOps\Console\Commands;

use Illuminate\Console\Command;
use Modules\FieldOps\Services\ComplexRelationDeliverySyncService;

class SyncComplexesFromRelationDeliveriesCommand extends Command
{
    protected $signature = 'fieldops:sync-complexes-from-relation-deliveries';

    protected $description = 'Import/update Complex records from CAFCA relation_delivery rows already mirrored locally, linked to an already-synced FoClient.';

    public function handle(ComplexRelationDeliverySyncService $service): int
    {
        $this->info('Syncing FieldOps complexes from mirrored CAFCA relation_delivery...');

        $count = $service->sync();

        $this->info("Done. {$count} complex(es) processed.");

        return self::SUCCESS;
    }
}
