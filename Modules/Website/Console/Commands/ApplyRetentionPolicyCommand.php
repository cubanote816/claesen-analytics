<?php

namespace Modules\Website\Console\Commands;

use Illuminate\Console\Command;
use Modules\Core\Models\Site;
use Modules\Website\Services\RetentionService;

/**
 * F4/CLA-476 of the multi-organization program — docs/ai/adr-multi-organization.md.
 *
 * config('website.retention.enabled') gates the destructive part: with it
 * off (the default in every environment today), every run behaves like
 * --dry-run regardless of the flag — reports what it WOULD do, changes
 * nothing. Scheduled daily (bootstrap/app.php) so it is safe to leave
 * running unattended before the "validación jurídica belga" launch
 * requirement clears and the flag gets turned on for real.
 */
class ApplyRetentionPolicyCommand extends Command
{
    protected $signature = 'website:apply-retention-policy {--dry-run}';

    protected $description = 'Delete expired spam leads and anonymize expired closed leads per the retention policy';

    public function handle(RetentionService $retention): int
    {
        $dryRun = $this->option('dry-run') || ! config('website.retention.enabled');

        if ($dryRun && ! $this->option('dry-run')) {
            $this->warn('website.retention.enabled is off — running as --dry-run. Nothing will be deleted or anonymized.');
        }

        $totalDeleted = 0;
        $totalAnonymized = 0;

        foreach (Site::query()->with('organization')->get() as $site) {
            $result = $retention->applyToSite($site, $dryRun);
            $totalDeleted += $result['deleted'];
            $totalAnonymized += $result['anonymized'];

            if ($result['deleted'] > 0 || $result['anonymized'] > 0) {
                $this->line(sprintf(
                    '%s [%s]: %d spam lead(s) %s, %d closed lead(s) %s.',
                    $site->key,
                    $dryRun ? 'dry-run' : 'applied',
                    $result['deleted'],
                    $dryRun ? 'would be deleted' : 'deleted',
                    $result['anonymized'],
                    $dryRun ? 'would be anonymized' : 'anonymized'
                ));
            }
        }

        $this->info(sprintf(
            'Total: %d spam lead(s) %s, %d closed lead(s) %s.',
            $totalDeleted,
            $dryRun ? 'would be deleted' : 'deleted',
            $totalAnonymized,
            $dryRun ? 'would be anonymized' : 'anonymized'
        ));

        return self::SUCCESS;
    }
}
