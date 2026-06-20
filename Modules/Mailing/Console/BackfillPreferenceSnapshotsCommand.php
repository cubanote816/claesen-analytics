<?php

namespace Modules\Mailing\Console;

use Illuminate\Console\Command;
use Modules\Mailing\Enums\CampaignStatus;
use Modules\Mailing\Enums\TemplateCategory;
use Modules\Mailing\Models\Campaign;

/**
 * Backfills template_category_snapshot and preference_category_snapshot for campaigns
 * that were created before MAI-PREF-001.
 *
 * DEPLOY SEQUENCE (production):
 *  1. Stop queue workers before running migrate (avoids processing mid-flight campaigns
 *     with missing snapshot data). If using Supervisor: `supervisorctl stop laravel-worker:*`
 *     If using manual queue:work: stop the process on the server.
 *  2. php artisan migrate
 *  3. php artisan mailing:backfill-preference-snapshots --apply
 *  4. Restart workers: `supervisorctl start laravel-worker:*` or re-run queue:work.
 *     Workers on the database driver also respect `php artisan queue:restart` (graceful stop).
 *
 * Safety rules:
 *  - Dry-run by default (--apply to commit).
 *  - Aborts with exit code 1 if any campaign is in the 'sending' state at invocation time.
 *  - Idempotent: a campaign is considered "done" only when it has a valid snapshot combination.
 *    Invalid/inconsistent snapshots are re-processed on every run.
 *  - Approved campaigns with unresolvable template → reverted to 'review' (clears approved_by/at).
 *  - Exits non-zero if any unresolvable cases remain after processing.
 */
class BackfillPreferenceSnapshotsCommand extends Command
{
    protected $signature = 'mailing:backfill-preference-snapshots
                            {--apply : Commit changes to the database (default is dry-run)}';

    protected $description = 'Backfill template_category_snapshot and preference_category_snapshot for pre-MAI-PREF-001 campaigns';

    private int $skipped      = 0;
    private int $updated      = 0;
    private int $reverted     = 0;
    private int $unresolvable = 0;

    public function handle(): int
    {
        $apply     = (bool) $this->option('apply');
        $validKeys = array_keys(config('mailing.preference_categories', []));

        $this->info($apply ? '[APPLY MODE] Changes will be written to the database.' : '[DRY-RUN] No changes will be made. Pass --apply to commit.');

        // Blocker: never run while campaigns are being sent
        $sendingCount = Campaign::where('status', CampaignStatus::SENDING->value)->count();
        if ($sendingCount > 0) {
            $this->error("BLOCKED: {$sendingCount} campaign(s) currently in 'sending' state.");
            $this->error('Stop queue workers and wait for all sending campaigns to finish before running this command.');
            return Command::FAILURE;
        }

        $campaigns = Campaign::with('template')
            ->whereIn('status', [
                CampaignStatus::DRAFT->value,
                CampaignStatus::REVIEW->value,
                CampaignStatus::APPROVED->value,
            ])
            ->get();

        $this->info("Found {$campaigns->count()} campaigns in draft/review/approved states.");

        foreach ($campaigns as $campaign) {
            $this->processCampaign($campaign, $validKeys, $apply);
        }

        $this->newLine();
        $this->table(
            ['Outcome', 'Count'],
            [
                ['Skipped (already valid)', $this->skipped],
                ['Updated', $this->updated],
                ['Approved → Review (unclassified commercial)', $this->reverted],
                ['Unresolvable (no template / unknown category)', $this->unresolvable],
            ]
        );

        if ($this->unresolvable > 0) {
            $this->error("{$this->unresolvable} campaign(s) could not be resolved. Assign or re-link their templates and run again.");
            return Command::FAILURE;
        }

        if (! $apply && ($this->updated > 0 || $this->reverted > 0)) {
            $this->warn('Dry-run complete. Run with --apply to commit these changes.');
        }

        $this->info('Done.');
        return Command::SUCCESS;
    }

    private function processCampaign(Campaign $campaign, array $validKeys, bool $apply): void
    {
        $template    = $campaign->template;
        $knownCats   = [TemplateCategory::COMMERCIAL->value, TemplateCategory::TRANSACTIONAL->value];
        $currentCat  = $campaign->template_category_snapshot;
        $currentPref = $campaign->preference_category_snapshot;

        // Determine if the current combination is already valid
        if (in_array($currentCat, $knownCats, true)) {
            $isCommercial    = $currentCat === TemplateCategory::COMMERCIAL->value;
            $isTransactional = $currentCat === TemplateCategory::TRANSACTIONAL->value;

            $validCombo = match (true) {
                $isCommercial    => in_array($currentPref, $validKeys, true),
                $isTransactional => $currentPref === null,
                default          => false,
            };

            if ($validCombo) {
                $this->skipped++;
                $this->line("  [SKIP]      Campaign #{$campaign->id} '{$campaign->description}' — already valid ({$currentCat}/{$currentPref}).");
                return;
            }
        }

        // No template → unresolvable
        if (! $template) {
            $this->unresolvable++;
            $this->warn("  [UNRESOLV]  Campaign #{$campaign->id} '{$campaign->description}' — no template linked. Re-link the template and run again.");
            return;
        }

        // Try to build snapshot
        try {
            $snapshot = Campaign::buildSnapshotFrom($template);
        } catch (\InvalidArgumentException $e) {
            $this->unresolvable++;
            $this->warn("  [UNRESOLV]  Campaign #{$campaign->id} '{$campaign->description}' — {$e->getMessage()}");
            return;
        }

        $newCat  = $snapshot['template_category_snapshot'];
        $newPref = $snapshot['preference_category_snapshot'];

        // Commercial with no valid preference_category after snapshot build → unresolvable
        if ($newCat === TemplateCategory::COMMERCIAL->value && ! in_array($newPref, $validKeys, true)) {
            // If campaign is approved, revert to review so it can be fixed
            if ($campaign->status === CampaignStatus::APPROVED) {
                $scheduledAt = $campaign->scheduled_at?->format('Y-m-d H:i') ?? 'none';
                $this->warn("  [REVERT]    Campaign #{$campaign->id} '{$campaign->description}' — commercial but template '{$template->name}' has no preference_category. Reverting approved → review. (was scheduled_at: {$scheduledAt})");
                if ($apply) {
                    $campaign->update([
                        'status'      => CampaignStatus::REVIEW->value,
                        'approved_by' => null,
                        'approved_at' => null,
                    ]);
                }
                $this->reverted++;
            } else {
                $this->warn("  [UNRESOLV]  Campaign #{$campaign->id} '{$campaign->description}' — commercial but template '{$template->name}' has no preference_category. Assign one and run again.");
                $this->unresolvable++;
            }
            return;
        }

        // Snapshot is valid — write it
        $this->line("  [UPDATE]    Campaign #{$campaign->id} '{$campaign->description}' — {$newCat}/{$newPref}");
        if ($apply) {
            $campaign->update([
                'template_name'                => $snapshot['template_name'],
                'subject_snapshot'             => $snapshot['subject_snapshot'],
                'body_snapshot'                => $snapshot['body_snapshot'],
                'template_category_snapshot'   => $newCat,
                'preference_category_snapshot' => $newPref,
            ]);
        }
        $this->updated++;
    }
}
