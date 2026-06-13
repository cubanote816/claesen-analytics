<?php

namespace Modules\Intelligence\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Modules\Intelligence\Services\MonthlyBillingGuardianService;

class BillingGuardianCommand extends Command
{
    protected $signature = 'intelligence:billing-guardian
                            {--month=        : Period to analyse in YYYY-MM format (e.g. 2026-05)}
                            {--current-month : Analyse the current calendar month}
                            {--previous-month : Analyse the previous calendar month}
                            {--dry-run       : Detect and report without persisting alerts}';

    protected $description = 'Run the Monthly Billing Guardian for a given period and persist alerts.';

    public function handle(MonthlyBillingGuardianService $guardian): int
    {
        [$year, $month] = $this->resolvePeriod();

        if ($year === null) {
            $this->error('Specify a period with --month=YYYY-MM, --current-month, or --previous-month.');

            return self::FAILURE;
        }

        $dryRun  = (bool) $this->option('dry-run');
        $label   = sprintf('%d-%02d', $year, $month);
        $mode    = $dryRun ? 'DRY RUN' : 'LIVE';

        $this->info("Billing Guardian [{$mode}] — period: {$label}");

        $report = $guardian->analyzeMonth($year, $month, $dryRun);

        $this->table(
            ['Metric', 'Value'],
            [
                ['Period',    $label],
                ['Mode',      $mode],
                ['Detected',  $report->totalDetected],
                ['Created',   $report->created],
                ['Updated',   $report->updated],
                ['Skipped',   $report->skipped],
            ]
        );

        if (!empty($report->byType)) {
            $this->newLine();
            $this->line('<fg=yellow>Alerts by type:</>');
            $rows = [];
            foreach ($report->byType as $type => $count) {
                $rows[] = [$type, $count];
            }
            $this->table(['Alert type', 'Count'], $rows);
        }

        if ($report->totalDetected === 0) {
            $this->info('No billing anomalies detected for the period.');
        } elseif ($dryRun) {
            $this->warn("Dry run complete — {$report->totalDetected} alert(s) detected but NOT persisted.");
        } else {
            $this->info("Done — {$report->created} created, {$report->updated} updated, {$report->skipped} skipped.");
        }

        return self::SUCCESS;
    }

    /** @return array{int|null, int|null} */
    private function resolvePeriod(): array
    {
        if ($this->option('current-month')) {
            $now = Carbon::now('Europe/Brussels');

            return [$now->year, $now->month];
        }

        if ($this->option('previous-month')) {
            $prev = Carbon::now('Europe/Brussels')->subMonthNoOverflow();

            return [$prev->year, $prev->month];
        }

        $raw = $this->option('month');
        if ($raw) {
            if (!preg_match('/^(\d{4})-(\d{2})$/', $raw, $m)) {
                $this->error("Invalid --month format. Expected YYYY-MM, got: {$raw}");

                return [null, null];
            }

            return [(int) $m[1], (int) $m[2]];
        }

        return [null, null];
    }
}
