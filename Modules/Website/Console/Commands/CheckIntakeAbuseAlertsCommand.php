<?php

namespace Modules\Website\Console\Commands;

use Illuminate\Console\Command;
use Modules\Core\Models\Site;
use Modules\Core\Models\User;
use Modules\Website\Models\WebsiteAbuseAlert;
use Modules\Website\Models\WebsiteIntakeSpamAttempt;
use Modules\Website\Notifications\WebsiteAbuseAlertNotification;
use Spatie\Permission\Models\Role;

/**
 * F4/CLA-475 of the multi-organization program — docs/ai/adr-multi-organization.md.
 *
 * "Alertas por picos de abuso." Scans Modules\Website\Models\
 * WebsiteIntakeSpamAttempt (written only by Modules\Website\Services\
 * IntakeSpamGuard — honeypot hits and failed Turnstile checks, never
 * merely-throttled requests) for a per-site count over
 * config('website.intake_hardening.abuse_alert.threshold') within the
 * trailing 'window_minutes'.
 *
 * Idempotency: UNIQUE(site_id, alert_bucket) on website_abuse_alerts,
 * where alert_bucket is an hourly bucket — firstOrCreate() returns
 * ($alert, false) for a site already alerted this hour, same
 * mailing:check-deliverability-alerts pattern (Modules\Mailing\Console\
 * CheckDeliverabilityAlertsCommand), except recurring rather than
 * permanent: continued abuse into the next hour gets a fresh alert.
 */
class CheckIntakeAbuseAlertsCommand extends Command
{
    protected $signature = 'website:check-intake-abuse-alerts
                            {--dry-run : Show spikes without creating alerts or sending notifications}';

    protected $description = 'Check recent public-intake spam attempts for a per-site abuse spike.';

    public function handle(): int
    {
        $windowMinutes = (int) config('website.intake_hardening.abuse_alert.window_minutes', 60);
        $threshold = (int) config('website.intake_hardening.abuse_alert.threshold', 20);
        $dryRun = (bool) $this->option('dry-run');

        if ($dryRun) {
            $this->info('[dry-run] No alerts will be created or notifications sent.');
        }

        $spikes = WebsiteIntakeSpamAttempt::query()
            ->where('created_at', '>=', now()->subMinutes($windowMinutes))
            ->selectRaw('site_id, COUNT(*) as attempts')
            ->groupBy('site_id')
            ->havingRaw('COUNT(*) >= ?', [$threshold])
            ->get();

        if ($spikes->isEmpty()) {
            $this->info('No abuse spikes detected.');

            return self::SUCCESS;
        }

        $alertBucket = now()->format('Y-m-d-H');
        $created = 0;

        foreach ($spikes as $spike) {
            $created += $this->fireAlert($spike->site_id, (int) $spike->attempts, $threshold, $windowMinutes, $alertBucket, $dryRun);
        }

        if (! $dryRun) {
            $this->info("Done. New alerts created: {$created}.");
        }

        return self::SUCCESS;
    }

    private function fireAlert(?int $siteId, int $attempts, int $threshold, int $windowMinutes, string $alertBucket, bool $dryRun): int
    {
        if ($dryRun) {
            $this->warn("  [dry-run] site #{$siteId}: {$attempts} spam attempt(s) >= {$threshold} threshold in the last {$windowMinutes}m");

            return 0;
        }

        $alert = WebsiteAbuseAlert::firstOrCreate(
            ['site_id' => $siteId, 'alert_bucket' => $alertBucket],
            ['attempt_count' => $attempts, 'threshold' => $threshold, 'window_minutes' => $windowMinutes]
        );

        if (! $alert->wasRecentlyCreated) {
            // Already alerted this site for this hour — idempotent, skip.
            return 0;
        }

        $organizationId = Site::query()->find($siteId)?->organization_id;

        // Resilient: skip roles that don't exist yet, same pattern as
        // Modules\Mailing\Console\CheckDeliverabilityAlertsCommand.
        $roleIds = Role::whereIn('name', ['super_admin', 'admin'])->pluck('id');
        $recipients = User::whereHas('roles', fn ($q) => $q->whereIn('id', $roleIds))
            ->inOrganization($organizationId)
            ->get();

        $recipients->each->notify(new WebsiteAbuseAlertNotification($alert));
        $alert->update(['notified_at' => now()]);

        $this->line("  [ALERT] site #{$siteId}: {$attempts} attempt(s) — {$recipients->count()} user(s) notified.");

        return 1;
    }
}
