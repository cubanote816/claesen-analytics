<?php

namespace Modules\Safety\Console\Commands;

use Illuminate\Console\Command;
use Carbon\Carbon;
use Modules\Safety\Services\SafetyAdoptionMetricsService;

class AggregateSafetyAdoptionMetricsCommand extends Command
{
    protected $signature = 'safety:aggregate-adoption {--date= : The date to aggregate (Y-m-d). Defaults to yesterday}';
    protected $description = 'Aggregate Safety adoption events into daily rollups and purge old raw events';

    public function handle(SafetyAdoptionMetricsService $service): int
    {
        $dateString = $this->option('date');
        $date = $dateString ? Carbon::parse($dateString) : Carbon::yesterday();

        $this->info("Aggregating adoption metrics for: " . $date->toDateString());

        $service->aggregateForDate($date);

        $this->info("Aggregation completed.");

        // Purge events older than 90 days
        $this->info("Purging raw events older than 90 days...");
        $deleted = $service->purgeOldEvents(90);
        
        $this->info("Purged {$deleted} old events.");

        return self::SUCCESS;
    }
}
