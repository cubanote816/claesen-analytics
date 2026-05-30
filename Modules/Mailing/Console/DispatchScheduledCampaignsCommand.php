<?php

namespace Modules\Mailing\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Modules\Mailing\Enums\CampaignStatus;
use Modules\Mailing\Jobs\ExecuteCampaignJob;
use Modules\Mailing\Models\Campaign;

/**
 * Finds approved campaigns whose scheduled_at has passed (UTC) and dispatches them.
 *
 * Anti-duplicate strategy: before dispatching, the command does an atomic DB UPDATE
 * (WHERE status = 'approved') to claim the campaign into 'sending'. If another process
 * already claimed it (0 rows affected), the campaign is skipped — no double dispatch.
 *
 * --dry-run: lists candidates without touching DB or queue.
 */
class DispatchScheduledCampaignsCommand extends Command
{
    protected $signature = 'mailing:dispatch-scheduled
                            {--dry-run : List due campaigns without dispatching or claiming}';

    protected $description = 'Dispatch approved scheduled campaigns whose scheduled_at has passed.';

    public function handle(): int
    {
        $now = now()->utc();

        $candidates = Campaign::where('status', CampaignStatus::APPROVED->value)
            ->whereNotNull('scheduled_at')
            ->where('scheduled_at', '<=', $now)
            ->get();

        if ($candidates->isEmpty()) {
            $this->info('No scheduled campaigns due.');
            return self::SUCCESS;
        }

        $this->info("Found {$candidates->count()} candidate(s).");

        $dispatched = 0;
        $skipped    = 0;

        foreach ($candidates as $campaign) {
            if ($this->option('dry-run')) {
                $this->line(sprintf(
                    '  [dry-run] campaign #%d "%s" scheduled_at=%s',
                    $campaign->id,
                    $campaign->description,
                    $campaign->scheduled_at->toDateTimeString(),
                ));
                continue;
            }

            // Atomic claim: UPDATE only if still approved.
            // Prevents double dispatch when the command runs concurrently.
            $claimed = Campaign::where('id', $campaign->id)
                ->where('status', CampaignStatus::APPROVED->value)
                ->update(['status' => CampaignStatus::SENDING->value]);

            if ($claimed === 0) {
                $this->warn("  [SKIP] Campaign #{$campaign->id} already claimed — concurrent dispatch detected.");
                Log::info('mailing:dispatch-scheduled: campaign already claimed', ['campaign_id' => $campaign->id]);
                $skipped++;
                continue;
            }

            ExecuteCampaignJob::dispatch(campaignId: $campaign->id);

            $this->line("  [OK] Dispatched campaign #{$campaign->id}: {$campaign->description}");
            Log::info('mailing:dispatch-scheduled: dispatched', [
                'campaign_id'  => $campaign->id,
                'scheduled_at' => $campaign->scheduled_at->toDateTimeString(),
            ]);
            $dispatched++;
        }

        if (! $this->option('dry-run')) {
            $this->info("Done. Dispatched: {$dispatched} | Skipped (already claimed): {$skipped}");
        }

        return self::SUCCESS;
    }
}
