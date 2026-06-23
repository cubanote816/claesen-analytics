<?php

declare(strict_types=1);

namespace Modules\Safety\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Modules\Safety\Emails\InspectionReminderMail;
use Modules\Safety\Services\InspectionReminderService;

class NotifyInactiveManagersCommand extends Command
{
    protected $signature = 'safety:notify-inactive-managers
                            {--days= : Override the compliance threshold in days}
                            {--dry-run : List candidates without sending any email}';

    protected $description = 'Emails project_managers who have not performed an inspection in >= N days.';

    public function __construct(private readonly InspectionReminderService $reminder)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        // Validate PWA URL before doing any work — a missing URL would produce
        // broken CTA links in every email, so we fail early in both real and dry-run
        // modes to catch misconfiguration before it reaches production.
        $pwaMissing = empty(config('safety.pwa_url'));
        if ($pwaMissing) {
            $this->error('SAFETY_PWA_URL is not configured. Set it in .env before running this command.');
            return self::FAILURE;
        }

        $days    = $this->option('days') !== null ? (int) $this->option('days') : null;
        $dryRun  = (bool) $this->option('dry-run');

        if ($dryRun) {
            $this->info('[DRY-RUN] No emails will be sent.');
        }

        $inactive = $this->reminder->getInactiveProjectManagers(days: $days);

        if ($inactive->isEmpty()) {
            $this->info('All project managers are compliant. No reminders needed.');
            return self::SUCCESS;
        }

        $sent    = 0;
        $skipped = 0;

        foreach ($inactive as $entry) {
            $user           = $entry['user'];
            $daysSinceLast  = $entry['days_since_last'];

            if (empty($user->email)) {
                $this->warn(sprintf('[SKIP] %s — no email address', $user->name));
                $skipped++;
                continue;
            }

            $label = $daysSinceLast !== null
                ? "{$daysSinceLast} dagen"
                : 'nooit geïnspecteerd';

            if ($dryRun) {
                $this->line(sprintf('[DRY-RUN] %s <%s> — %s', $user->name, $user->email, $label));
                continue;
            }

            Mail::to($user->email)->send(new InspectionReminderMail($user, $daysSinceLast));

            $this->info(sprintf('[OK] %s <%s> — herinnering verzonden (%s)', $user->name, $user->email, $label));
            $sent++;
        }

        if (! $dryRun) {
            $this->info(sprintf('Klaar: %d herinnering(en) verzonden, %d overgeslagen.', $sent, $skipped));
        }

        return self::SUCCESS;
    }
}
