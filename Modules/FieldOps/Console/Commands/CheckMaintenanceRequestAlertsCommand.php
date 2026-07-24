<?php

declare(strict_types=1);

namespace Modules\FieldOps\Console\Commands;

use Illuminate\Console\Command;
use Modules\FieldOps\Services\MaintenanceRequestAlertService;

class CheckMaintenanceRequestAlertsCommand extends Command
{
    protected $signature = 'fieldops:check-request-alerts
                            {--dry-run : Preview alerts without creating/resolving them or notifying anyone}';

    protected $description = 'Check maintenance requests for missed first-response and client-confirmation SLA thresholds.';

    public function handle(MaintenanceRequestAlertService $service): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $result = $service->check($dryRun);

        $this->info($dryRun
            ? "[dry-run] {$result['created']} alert(s) would be created, {$result['resolved']} would auto-resolve."
            : "{$result['created']} alert(s) created and notified, {$result['resolved']} auto-resolved.");

        return self::SUCCESS;
    }
}
