<?php

namespace Modules\Mailing\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Modules\Mailing\Enums\CampaignStatus;
use Modules\Mailing\Enums\FollowUpTrigger;
use Modules\Mailing\Enums\MessageEventType;
use Modules\Mailing\Jobs\ExecuteCampaignJob;
use Modules\Mailing\Models\Campaign;
use Modules\Mailing\Models\CampaignMessage;
use Modules\Mailing\Models\EmailTemplate;
use Modules\Mailing\Models\MessageEvent;
use Modules\Mailing\Models\SuppressionEntry;
use Modules\Prospects\Models\Prospect;
use Tests\TestCase;

class FollowUpTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function completedParent(array $parentAttrs = []): Campaign
    {
        $child = Campaign::factory()->create([
            'status'      => CampaignStatus::APPROVED,
            'template_id' => EmailTemplate::factory()->create()->id,
        ]);

        return Campaign::factory()->create(array_merge([
            'status'                => CampaignStatus::COMPLETED,
            'finished_at'           => now()->subHours(25),
            'followup_campaign_id'  => $child->id,
            'followup_trigger'      => FollowUpTrigger::CLICKED->value,
            'followup_delay_hours'  => 24,
            'followup_dispatched_at' => null,
        ], $parentAttrs));
    }

    private function prospect(array $attrs = []): Prospect
    {
        return Prospect::factory()->create(array_merge(['unsubscribed_at' => null], $attrs));
    }

    private function sentMessage(Campaign $campaign, Prospect $prospect): CampaignMessage
    {
        return CampaignMessage::factory()->sent()->create([
            'campaign_id' => $campaign->id,
            'prospect_id' => $prospect->id,
            'email'       => 'test@example.com',
        ]);
    }

    private function recordClick(CampaignMessage $message): void
    {
        MessageEvent::create([
            'message_id'  => $message->id,
            'event_type'  => MessageEventType::CLICKED->value,
            'occurred_at' => now(),
        ]);
    }

    // -------------------------------------------------------------------------
    // Timing
    // -------------------------------------------------------------------------

    public function test_followup_not_dispatched_before_delay(): void
    {
        Queue::fake();

        $child  = Campaign::factory()->create(['status' => CampaignStatus::APPROVED, 'template_id' => EmailTemplate::factory()->create()->id]);
        $parent = Campaign::factory()->create([
            'status'               => CampaignStatus::COMPLETED,
            'finished_at'          => now()->subHours(12), // only 12h, need 24h
            'followup_campaign_id' => $child->id,
            'followup_trigger'     => FollowUpTrigger::CLICKED->value,
            'followup_delay_hours' => 24,
        ]);

        $this->artisan('mailing:dispatch-followups')->assertSuccessful();

        Queue::assertNothingPushed();
    }

    // -------------------------------------------------------------------------
    // Audience resolution by trigger
    // -------------------------------------------------------------------------

    public function test_clicked_trigger_dispatches_only_clickers(): void
    {
        Queue::fake();

        $parent  = $this->completedParent(['followup_trigger' => FollowUpTrigger::CLICKED->value]);
        $clicker = $this->prospect();
        $passive = $this->prospect();

        $this->recordClick($this->sentMessage($parent, $clicker));
        $this->sentMessage($parent, $passive); // no click

        $this->artisan('mailing:dispatch-followups')->assertSuccessful();

        Queue::assertPushed(ExecuteCampaignJob::class, function (ExecuteCampaignJob $job) use ($clicker, $passive): bool {
            return in_array($clicker->id, $job->overrideProspectIds)
                && ! in_array($passive->id, $job->overrideProspectIds);
        });
    }

    public function test_not_clicked_trigger_dispatches_only_non_clickers(): void
    {
        Queue::fake();

        $parent  = $this->completedParent(['followup_trigger' => FollowUpTrigger::NOT_CLICKED->value]);
        $clicker = $this->prospect();
        $passive = $this->prospect();

        $this->recordClick($this->sentMessage($parent, $clicker));
        $this->sentMessage($parent, $passive);

        $this->artisan('mailing:dispatch-followups')->assertSuccessful();

        Queue::assertPushed(ExecuteCampaignJob::class, function (ExecuteCampaignJob $job) use ($clicker, $passive): bool {
            return ! in_array($clicker->id, $job->overrideProspectIds)
                && in_array($passive->id, $job->overrideProspectIds);
        });
    }

    // -------------------------------------------------------------------------
    // Invariants: unsubscribed + suppressed always excluded
    // -------------------------------------------------------------------------

    public function test_followup_audience_excludes_unsubscribed_even_if_clicked(): void
    {
        Queue::fake();

        $parent     = $this->completedParent(['followup_trigger' => FollowUpTrigger::CLICKED->value]);
        $unsubscribed = $this->prospect(['unsubscribed_at' => now()->subDay()]);

        $this->recordClick($this->sentMessage($parent, $unsubscribed));

        $this->artisan('mailing:dispatch-followups')->assertSuccessful();

        // Audience is empty → claimed but no job
        Queue::assertNothingPushed();
        $this->assertNotNull($parent->fresh()->followup_dispatched_at);
    }

    public function test_followup_audience_excludes_suppressed_even_if_clicked(): void
    {
        Queue::fake();

        $parent     = $this->completedParent(['followup_trigger' => FollowUpTrigger::CLICKED->value]);
        $suppressed = $this->prospect();

        SuppressionEntry::create([
            'email'         => 'suppressed@example.com',
            'prospect_id'   => $suppressed->id,
            'reason'        => 'hard_bounce',
            'suppressed_at' => now(),
        ]);

        $this->recordClick($this->sentMessage($parent, $suppressed));

        $this->artisan('mailing:dispatch-followups')->assertSuccessful();

        Queue::assertNothingPushed();
        $this->assertNotNull($parent->fresh()->followup_dispatched_at);
    }

    // -------------------------------------------------------------------------
    // Empty audience: claim without dispatch
    // -------------------------------------------------------------------------

    public function test_empty_audience_marks_dispatched_at_without_sending(): void
    {
        Queue::fake();

        $parent = $this->completedParent(['followup_trigger' => FollowUpTrigger::CLICKED->value]);
        // No clicks — no audience

        $this->artisan('mailing:dispatch-followups')->assertSuccessful();

        Queue::assertNothingPushed();
        $this->assertNotNull($parent->fresh()->followup_dispatched_at, 'followup_dispatched_at must be set even for empty audience to prevent retry loop.');
    }

    // -------------------------------------------------------------------------
    // Idempotency
    // -------------------------------------------------------------------------

    public function test_followup_command_is_idempotent_when_run_twice(): void
    {
        Queue::fake();

        $parent  = $this->completedParent();
        $clicker = $this->prospect();
        $this->recordClick($this->sentMessage($parent, $clicker));

        $this->artisan('mailing:dispatch-followups')->assertSuccessful();
        $this->artisan('mailing:dispatch-followups')->assertSuccessful();

        Queue::assertPushed(ExecuteCampaignJob::class, 1); // not twice
    }

    // -------------------------------------------------------------------------
    // Child campaign validation
    // -------------------------------------------------------------------------

    public function test_followup_skipped_if_child_not_approved(): void
    {
        Queue::fake();

        $child = Campaign::factory()->create([
            'status'      => CampaignStatus::DRAFT,
            'template_id' => EmailTemplate::factory()->create()->id,
        ]);

        $parent = Campaign::factory()->create([
            'status'               => CampaignStatus::COMPLETED,
            'finished_at'          => now()->subHours(25),
            'followup_campaign_id' => $child->id,
            'followup_trigger'     => FollowUpTrigger::CLICKED->value,
            'followup_delay_hours' => 24,
        ]);

        $this->artisan('mailing:dispatch-followups')->assertSuccessful();

        Queue::assertNothingPushed();
        $this->assertNull($parent->fresh()->followup_dispatched_at, 'Should not claim if child is not approved.');
    }

    // -------------------------------------------------------------------------
    // Self-referential follow-up rejected
    // -------------------------------------------------------------------------

    public function test_self_referential_followup_is_skipped(): void
    {
        Queue::fake();

        $campaign = Campaign::factory()->create([
            'status'      => CampaignStatus::COMPLETED,
            'finished_at' => now()->subHours(25),
            'template_id' => EmailTemplate::factory()->create()->id,
            'followup_trigger'     => FollowUpTrigger::CLICKED->value,
            'followup_delay_hours' => 24,
        ]);

        // Directly set self-reference (bypasses form validation)
        Campaign::where('id', $campaign->id)->update(['followup_campaign_id' => $campaign->id]);

        $this->artisan('mailing:dispatch-followups')->assertSuccessful();

        Queue::assertNothingPushed();
    }

    // -------------------------------------------------------------------------
    // --dry-run
    // -------------------------------------------------------------------------

    public function test_dry_run_does_not_dispatch_or_claim(): void
    {
        Queue::fake();

        $parent  = $this->completedParent();
        $clicker = $this->prospect();
        $this->recordClick($this->sentMessage($parent, $clicker));

        $this->artisan('mailing:dispatch-followups --dry-run')->assertSuccessful();

        Queue::assertNothingPushed();
        $this->assertNull($parent->fresh()->followup_dispatched_at);
    }
}
