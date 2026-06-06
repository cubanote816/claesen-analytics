<?php

namespace Modules\Mailing\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Mailing\Enums\CampaignStatus;
use Modules\Mailing\Enums\MessageEventType;
use Modules\Mailing\Jobs\ExecuteCampaignJob;
use Modules\Mailing\Models\Campaign;

/**
 * Selects the A/B test winner for eligible campaigns and dispatches the winner send.
 *
 * Eligibility: status=SENDING, ab_subject_b IS NOT NULL, ab_winner_variant IS NULL,
 *              ab_test_started_at <= NOW() - ab_winner_after_hours.
 *
 * Winner metric: CTR = unique clicked messages / sent messages per variant.
 * Tie-breaking:  variant A wins.
 * Safety:        atomic claim via UPDATE WHERE ab_winner_variant IS NULL prevents
 *                double winner selection on concurrent runs.
 */
class SelectAbWinnerCommand extends Command
{
    protected $signature = 'mailing:ab-select-winner
                            {--dry-run : Show candidates and calculated CTRs without selecting or dispatching}';

    protected $description = 'Select A/B test winner by CTR and dispatch the winner send for eligible campaigns.';

    public function handle(): int
    {
        $candidates = Campaign::where('status', CampaignStatus::SENDING->value)
            ->whereNotNull('ab_subject_b')
            ->whereNull('ab_winner_variant')
            ->whereNotNull('ab_test_started_at')
            ->whereRaw('ab_test_started_at <= DATE_SUB(NOW(), INTERVAL ab_winner_after_hours HOUR)')
            ->get();

        if ($candidates->isEmpty()) {
            $this->info('No A/B campaigns ready for winner selection.');
            return self::SUCCESS;
        }

        $this->info("Found {$candidates->count()} candidate(s).");

        foreach ($candidates as $campaign) {
            $this->processCandidate($campaign);
        }

        return self::SUCCESS;
    }

    private function processCandidate(Campaign $campaign): void
    {
        // Sent counts per variant
        $sentCounts = DB::table('mailing_messages')
            ->where('campaign_id', $campaign->id)
            ->where('status', 'sent')
            ->whereIn('ab_variant', ['A', 'B'])
            ->selectRaw('ab_variant, COUNT(*) as cnt')
            ->groupBy('ab_variant')
            ->pluck('cnt', 'ab_variant');

        $sentA = (int) ($sentCounts['A'] ?? 0);
        $sentB = (int) ($sentCounts['B'] ?? 0);

        // Unique click counts per variant (distinct message_id — not raw click events)
        $clickCounts = DB::table('mailing_messages as m')
            ->join('mailing_message_events as e', 'e.message_id', '=', 'm.id')
            ->where('m.campaign_id', $campaign->id)
            ->whereIn('m.ab_variant', ['A', 'B'])
            ->where('e.event_type', MessageEventType::CLICKED->value)
            ->selectRaw('m.ab_variant, COUNT(DISTINCT m.id) as unique_clicks')
            ->groupBy('m.ab_variant')
            ->pluck('unique_clicks', 'ab_variant');

        $clicksA = (int) ($clickCounts['A'] ?? 0);
        $clicksB = (int) ($clickCounts['B'] ?? 0);

        $ctrA = $sentA > 0 ? round($clicksA / $sentA * 100, 4) : 0;
        $ctrB = $sentB > 0 ? round($clicksB / $sentB * 100, 4) : 0;

        // Guard: one or both variants empty
        if ($sentA === 0 && $sentB === 0) {
            $this->warn("  [SKIP] Campaign #{$campaign->id}: no sent messages for either variant — cannot select winner.");
            Log::error('mailing:ab-select-winner: no sent messages for either variant', [
                'campaign_id' => $campaign->id,
            ]);
            return;
        }

        if ($sentA === 0 || $sentB === 0) {
            $this->warn("  [SKIP] Campaign #{$campaign->id}: one variant has 0 sent messages (A={$sentA}, B={$sentB}) — refusing silent selection.");
            Log::error('mailing:ab-select-winner: one variant empty — refusing to select winner', [
                'campaign_id' => $campaign->id,
                'sent_a'      => $sentA,
                'sent_b'      => $sentB,
            ]);
            return;
        }

        // Low sample warning (MVP: proceed anyway)
        $minSample = config('mailing.ab_min_sample', 5);
        if ($sentA < $minSample || $sentB < $minSample) {
            Log::warning('mailing:ab-select-winner: sample size below minimum — selecting winner anyway.', [
                'campaign_id' => $campaign->id,
                'sent_a'      => $sentA,
                'sent_b'      => $sentB,
                'min_sample'  => $minSample,
            ]);
        }

        // Tie-breaking: A wins
        $winner = $ctrB > $ctrA ? 'B' : 'A';

        if ($this->option('dry-run')) {
            $this->line(sprintf(
                '  [dry-run] campaign #%d — A: %d sent, %d clicks (CTR %.4f%%) | B: %d sent, %d clicks (CTR %.4f%%) | winner → %s',
                $campaign->id, $sentA, $clicksA, $ctrA, $sentB, $clicksB, $ctrB, $winner
            ));
            return;
        }

        // Atomic claim: set winner only if still null
        $claimed = Campaign::where('id', $campaign->id)
            ->whereNull('ab_winner_variant')
            ->update([
                'ab_winner_variant'    => $winner,
                'ab_winner_selected_at' => now(),
            ]);

        if ($claimed === 0) {
            $this->warn("  [SKIP] Campaign #{$campaign->id}: winner already claimed by another process.");
            Log::info('mailing:ab-select-winner: winner already claimed', ['campaign_id' => $campaign->id]);
            return;
        }

        ExecuteCampaignJob::dispatch(campaignId: $campaign->id, isWinnerSend: true);

        $this->line(sprintf(
            '  [OK] Campaign #%d — winner: %s (CTR_A=%.4f%%, CTR_B=%.4f%%) — winner send dispatched.',
            $campaign->id, $winner, $ctrA, $ctrB
        ));
        Log::info('mailing:ab-select-winner: winner selected and winner send dispatched', [
            'campaign_id' => $campaign->id,
            'winner'      => $winner,
            'ctr_a'       => $ctrA,
            'ctr_b'       => $ctrB,
        ]);
    }
}
