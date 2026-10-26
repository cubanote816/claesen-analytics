<?php

namespace Modules\Website\Console\Commands;

use Illuminate\Console\Command;
use Modules\Website\Jobs\SendConsultationEmailJob;
use Modules\Website\Models\ConsultationEmailDelivery;

/**
 * F4/CLA-473 of the multi-organization program — docs/ai/adr-multi-organization.md.
 *
 * Re-dispatches every 'failed' delivery under the attempt ceiling — the
 * observable-retries mechanism SendConsultationEmailJob's own docblock
 * points to, chosen instead of Laravel's built-in $tries/backoff so the
 * job's behaviour never depends on which queue connection is active (see
 * that job for the full reasoning). A delivery that has already exhausted
 * --max-attempts stays 'failed' and visible — never silently retried
 * forever.
 */
class RetryFailedConsultationEmailsCommand extends Command
{
    protected $signature = 'website:retry-failed-consultation-emails {--max-attempts=3}';

    protected $description = 'Re-dispatch failed consultation e-mail deliveries that have not exhausted their attempt ceiling';

    public function handle(): int
    {
        $maxAttempts = (int) $this->option('max-attempts');

        $deliveries = ConsultationEmailDelivery::query()
            ->where('status', ConsultationEmailDelivery::STATUS_FAILED)
            ->where('attempts', '<', $maxAttempts)
            ->get();

        foreach ($deliveries as $delivery) {
            SendConsultationEmailJob::dispatch($delivery->id);
        }

        $this->info(sprintf('Re-dispatched %d failed consultation e-mail deliveries.', $deliveries->count()));

        return self::SUCCESS;
    }
}
