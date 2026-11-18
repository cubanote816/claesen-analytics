<?php

declare(strict_types=1);

namespace Modules\Knx\Console\Commands;

use Illuminate\Console\Command;
use Modules\Knx\Models\KnxNotification;
use Modules\Knx\Models\KnxProject;
use Modules\Knx\Services\KnxNoticeMailer;

/**
 * `knx:notification-digest` — mail the notices that are not urgent, grouped per
 * project (KNX-54).
 *
 * The scheduler runs it hourly; the same command is what sends the summary when a visit
 * closes, so both cadences use one implementation and one dedupe flag (`emailed_at`).
 * Running it twice in a row sends nothing the second time.
 */
class KnxNotificationDigestCommand extends Command
{
    protected $signature = 'knx:notification-digest {--project= : Only this project code}';

    protected $description = 'Send the digest of non-immediate KNX notices (KNX-54)';

    public function handle(KnxNoticeMailer $mailer): int
    {
        $projects = KnxProject::query()
            ->when(
                (string) $this->option('project') !== '',
                fn ($query) => $query->where('code', $this->option('project')),
            )
            ->whereIn('id', KnxNotification::query()->whereNull('emailed_at')->select('project_id'))
            ->get();

        $total = 0;

        foreach ($projects as $project) {
            $total += $mailer->sendDigest($project);
        }

        $this->info("Digest sent for {$total} notice(s).");

        return self::SUCCESS;
    }
}
