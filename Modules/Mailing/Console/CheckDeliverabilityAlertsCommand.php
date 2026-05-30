<?php

namespace Modules\Mailing\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Modules\Core\Models\User;
use Modules\Mailing\Enums\CampaignStatus;
use Modules\Mailing\Enums\DeliverabilityAlertType;
use Modules\Mailing\Enums\MessageEventType;
use Modules\Mailing\Models\Campaign;
use Modules\Mailing\Models\CampaignMessage;
use Modules\Mailing\Models\DeliverabilityAlert;
use Modules\Mailing\Models\MessageEvent;
use Modules\Mailing\Notifications\DeliverabilityAlertNotification;

/**
 * Scans recently completed campaigns for deliverability threshold violations.
 *
 * Idempotency: UNIQUE(campaign_id, alert_type) on mailing_deliverability_alerts.
 * firstOrCreate() returns ($alert, false) for already-alerted pairs — no double notification.
 *
 * Scope: COMPLETED campaigns finished within config('mailing.alert_check_days') days.
 * Metrics: COUNT(DISTINCT message_id) for both bounce and spam events (same as dashboard widget).
 */
class CheckDeliverabilityAlertsCommand extends Command
{
    protected $signature = 'mailing:check-deliverability-alerts
                            {--dry-run : Show violations without creating alerts or sending notifications}';

    protected $description = 'Check completed campaigns for hard bounce and spam complaint threshold violations.';

    public function handle(): int
    {
        $checkDays   = (int) config('mailing.alert_check_days', 7);
        $bounceThreshold = (float) config('mailing.hard_bounce_alert', 0.05);
        $spamThreshold   = (float) config('mailing.spam_rate_alert', 0.0008);
        $dryRun      = (bool) $this->option('dry-run');

        if ($dryRun) {
            $this->info('[dry-run] No alerts will be created or notifications sent.');
        }

        $campaigns = Campaign::where('status', CampaignStatus::COMPLETED->value)
            ->where('finished_at', '>=', now()->subDays($checkDays))
            ->get();

        if ($campaigns->isEmpty()) {
            $this->info('No completed campaigns in the evaluation window.');
            return self::SUCCESS;
        }

        $this->info("Evaluating {$campaigns->count()} campaign(s) from the last {$checkDays} days.");

        $alertsCreated = 0;

        foreach ($campaigns as $campaign) {
            $alertsCreated += $this->evaluateCampaign($campaign, $bounceThreshold, $spamThreshold, $dryRun);
        }

        if (! $dryRun) {
            $this->info("Done. New alerts created: {$alertsCreated}.");
        }

        return self::SUCCESS;
    }

    private function evaluateCampaign(Campaign $campaign, float $bounceThreshold, float $spamThreshold, bool $dryRun): int
    {
        $sentCount = CampaignMessage::where('campaign_id', $campaign->id)
            ->where('status', 'sent')
            ->count();

        if ($sentCount === 0) {
            return 0;
        }

        $messageIds = CampaignMessage::where('campaign_id', $campaign->id)
            ->where('status', 'sent')
            ->pluck('id');

        $hardBounceCount = MessageEvent::whereIn('message_id', $messageIds)
            ->where('event_type', MessageEventType::BOUNCED_HARD->value)
            ->distinct('message_id')
            ->count('message_id');

        $spamCount = MessageEvent::whereIn('message_id', $messageIds)
            ->where('event_type', MessageEventType::COMPLAINED->value)
            ->distinct('message_id')
            ->count('message_id');

        $hardBounceRate = $hardBounceCount / $sentCount;
        $spamRate       = $spamCount / $sentCount;

        $created = 0;

        if ($hardBounceRate > $bounceThreshold) {
            $created += $this->fireAlert(
                $campaign, DeliverabilityAlertType::HARD_BOUNCE_HIGH,
                $hardBounceRate, $bounceThreshold, $sentCount, $hardBounceCount,
                $dryRun
            );
        }

        if ($spamRate > $spamThreshold) {
            $created += $this->fireAlert(
                $campaign, DeliverabilityAlertType::SPAM_COMPLAINT_HIGH,
                $spamRate, $spamThreshold, $sentCount, $spamCount,
                $dryRun
            );
        }

        return $created;
    }

    private function fireAlert(
        Campaign $campaign,
        DeliverabilityAlertType $type,
        float $rate,
        float $threshold,
        int $sentCount,
        int $eventCount,
        bool $dryRun,
    ): int {
        $ratePercent = round($rate * 100, 4);
        $thresholdPct = round($threshold * 100, 4);

        if ($dryRun) {
            $this->warn(sprintf(
                '  [dry-run] campaign #%d "%s" — %s: %.4f%% > %.4f%% threshold (%d/%d)',
                $campaign->id, $campaign->description,
                $type->label(), $ratePercent, $thresholdPct,
                $eventCount, $sentCount
            ));
            return 0;
        }

        [$alert, $created] = DeliverabilityAlert::firstOrCreate(
            ['campaign_id' => $campaign->id, 'alert_type' => $type->value],
            [
                'rate'        => $rate,
                'threshold'   => $threshold,
                'sent_count'  => $sentCount,
                'event_count' => $eventCount,
            ]
        );

        if (! $created) {
            // Already alerted for this campaign + type — idempotent, skip
            return 0;
        }

        // Notify admins and campaign managers
        $recipients = User::role(['super_admin', 'admin', 'campaign_manager'])->get();
        $recipients->each->notify(new DeliverabilityAlertNotification($alert));

        $alert->update(['notified_at' => now()]);

        $this->line(sprintf(
            '  [ALERT] campaign #%d "%s" — %s: %.4f%% > %.4f%% (%d/%d) — %d user(s) notified.',
            $campaign->id, $campaign->description,
            $type->label(), $ratePercent, $thresholdPct,
            $eventCount, $sentCount, $recipients->count()
        ));

        Log::warning('mailing:check-deliverability-alerts: threshold exceeded', [
            'campaign_id'   => $campaign->id,
            'alert_type'    => $type->value,
            'rate_percent'  => $ratePercent,
            'threshold_pct' => $thresholdPct,
            'sent_count'    => $sentCount,
            'event_count'   => $eventCount,
        ]);

        return 1;
    }
}
