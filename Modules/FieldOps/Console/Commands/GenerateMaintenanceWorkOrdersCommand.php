<?php

declare(strict_types=1);

namespace Modules\FieldOps\Console\Commands;

use Illuminate\Console\Command;
use Modules\FieldOps\Services\MaintenanceWorkOrderService;

class GenerateMaintenanceWorkOrdersCommand extends Command
{
    protected $signature = 'fieldops:generate-maintenance-work-orders {--dry-run : Preview due plans without creating work orders}';

    protected $description = 'Generate work orders for recurring FieldOps maintenance plans that are due.';

    public function handle(MaintenanceWorkOrderService $service): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $count = $service->generateDueOrders($dryRun);

        $this->info($dryRun
            ? "{$count} maintenance plan(s) are due. No work orders were created."
            : "{$count} maintenance work order(s) generated.");

        return self::SUCCESS;
    }
}
