<?php

namespace Modules\Mailing\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Mailing\Enums\CampaignStatus;
use Modules\Mailing\Enums\FollowUpTrigger;
use Modules\Mailing\Enums\MessageEventType;
use Modules\Mailing\Jobs\ExecuteCampaignJob;
use Modules\Mailing\Models\Campaign;
use Modules\Prospects\Models\Prospect;

/**
 * Dispatches follow-up campaigns for completed parent campaigns whose delay has passed.
 *
 * Safety guarantees:
 *  - Self-referential follow-up (followup_campaign_id === id) is skipped explicitly.
 *  - Child campaign must be in APPROVED status; otherwise skipped with log.
 *  - Empty audience after filtering: followup_dispatched_at IS STILL MARKED to prevent
 *    infinite retry. Documented as "processed_empty_audience" in log.
 *  - Atomic claim (UPDATE WHERE followup_dispatched_at IS NULL) happens BEFORE dispatch.
 *    If dispatch fails after claim, the campaign is marked as processed — no double send.
 *  - --dry-run: no DB mutations, no job dispatch.
 */
class DispatchFollowUpsCommand extends Command
{
    protected $signature = 'mailing:dispatch-followups
                            {--dry-run : Show candidates without dispatching or claiming}';

    protected $description = 'Dispatch follow-up campaigns for completed parent campaigns whose delay has elapsed.';

    public function handle(): int
    {
        $candidates = Campaign::where('status', CampaignStatus::COMPLETED->value)
            ->whereNotNull('followup_campaign_id')
            ->whereNotNull('followup_trigger')
            ->whereNotNull('followup_delay_hours')
            ->whereNull('followup_dispatched_at')
            ->whereNotNull('finished_at')
            ->whereRaw('finished_at <= DATE_SUB(NOW(), INTERVAL followup_delay_hours HOUR)')
            ->get();

        if ($candidates->isEmpty()) {
            $this->info('No follow-up campaigns due.');
            return self::SUCCESS;
        }

        $this->info("Found {$candidates->count()} candidate(s).");

        foreach ($candidates as $parent) {
            $this->processCandidate($parent);
        }

        return self::SUCCESS;
    }

    private function processCandidate(Campaign $parent): void
    {
        // Guard: self-referential follow-up
        if ($parent->followup_campaign_id === $parent->id) {
            $this->warn("  [SKIP] Campaign #{$parent->id}: followup_campaign_id points to itself — invalid configuration.");
            Log::warning('mailing:dispatch-followups: self-referential follow-up skipped', ['campaign_id' => $parent->id]);
            return;
        }

        $child = Campaign::find($parent->followup_campaign_id);

        if (! $child) {
            $this->warn("  [SKIP] Campaign #{$parent->id}: child campaign #{$parent->followup_campaign_id} not found.");
            Log::warning('mailing:dispatch-followups: child campaign not found', [
                'parent_id' => $parent->id,
                'child_id'  => $parent->followup_campaign_id,
            ]);
            return;
        }

        if ($child->status !== CampaignStatus::APPROVED) {
            $this->warn("  [SKIP] Campaign #{$parent->id}: child #{$child->id} is not approved (status: {$child->status->value}).");
            Log::warning('mailing:dispatch-followups: child campaign not approved', [
                'parent_id'    => $parent->id,
                'child_id'     => $child->id,
                'child_status' => $child->status->value,
            ]);
            return;
        }

        $trigger     = $parent->followup_trigger;
        $audienceIds = $this->resolveFollowUpAudience($parent, $trigger);

        if ($this->option('dry-run')) {
            $this->line(sprintf(
                '  [dry-run] parent #%d → child #%d trigger=%s delay=%dh audience=%d prospects',
                $parent->id, $child->id, $trigger->value, $parent->followup_delay_hours, count($audienceIds)
            ));
            return;
        }

        // Atomic claim BEFORE dispatch — prevents double dispatch on concurrent runs.
        // followup_dispatched_at is set even for empty audience (prevents infinite retry).
        $claimed = Campaign::where('id', $parent->id)
            ->whereNull('followup_dispatched_at')
            ->update(['followup_dispatched_at' => now()]);

        if ($claimed === 0) {
            $this->warn("  [SKIP] Campaign #{$parent->id}: already claimed by another process.");
            Log::info('mailing:dispatch-followups: already claimed', ['campaign_id' => $parent->id]);
            return;
        }

        if (empty($audienceIds)) {
            $this->warn("  [SKIP→CLAIMED] Campaign #{$parent->id}: audience is empty after filtering — marked processed without send.");
            Log::warning('mailing:dispatch-followups: empty audience — processed without send', [
                'parent_id' => $parent->id,
                'child_id'  => $child->id,
                'trigger'   => $trigger->value,
            ]);
            return;
        }

        ExecuteCampaignJob::dispatch(
            campaignId: $child->id,
            overrideProspectIds: $audienceIds,
        );

        $this->line(sprintf(
            '  [OK] Dispatched follow-up #%d → child #%d (trigger=%s, %d prospects)',
            $parent->id, $child->id, $trigger->value, count($audienceIds)
        ));
        Log::info('mailing:dispatch-followups: dispatched', [
            'parent_id'    => $parent->id,
            'child_id'     => $child->id,
            'trigger'      => $trigger->value,
            'audience_size' => count($audienceIds),
        ]);
    }

    // -------------------------------------------------------------------------
    // Audience resolution
    // -------------------------------------------------------------------------

    private function resolveFollowUpAudience(Campaign $parent, FollowUpTrigger $trigger): array
    {
        $eventType = match ($trigger) {
            FollowUpTrigger::CLICKED, FollowUpTrigger::NOT_CLICKED => MessageEventType::CLICKED->value,
            FollowUpTrigger::OPENED,  FollowUpTrigger::NOT_OPENED  => MessageEventType::OPENED->value,
        };

        $hasEvent = in_array($trigger, [FollowUpTrigger::CLICKED, FollowUpTrigger::OPENED], true);

        $baseQuery = DB::table('mailing_messages')
            ->select('prospect_id')
            ->where('campaign_id', $parent->id)
            ->where('status', 'sent')
            ->whereNotNull('prospect_id');

        if ($hasEvent) {
            $baseQuery->whereExists(function ($sub) use ($eventType): void {
                $sub->from('mailing_message_events')
                    ->whereColumn('mailing_message_events.message_id', 'mailing_messages.id')
                    ->where('mailing_message_events.event_type', $eventType);
            });
        } else {
            $baseQuery->whereNotExists(function ($sub) use ($eventType): void {
                $sub->from('mailing_message_events')
                    ->whereColumn('mailing_message_events.message_id', 'mailing_messages.id')
                    ->where('mailing_message_events.event_type', $eventType);
            });
        }

        $candidateIds = $baseQuery->pluck('prospect_id')->toArray();

        if (empty($candidateIds)) {
            return [];
        }

        // Exclude unsubscribed and suppressed — same invariants as SegmentResolverService
        return Prospect::whereIn('id', $candidateIds)
            ->whereNull('unsubscribed_at')
            ->whereNotIn('id', function ($sub): void {
                $sub->select('prospect_id')
                    ->from('mailing_suppression_list')
                    ->whereNotNull('prospect_id');
            })
            ->pluck('id')
            ->toArray();
    }
}
