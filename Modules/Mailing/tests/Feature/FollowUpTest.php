<?php

namespace Modules\Mailing\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
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

    protected function tearDown(): void
    {
        // Restore anything the timezone-skew tests below changed. No-op for the rest.
        Carbon::setTestNow();
        DB::statement("SET time_zone = 'SYSTEM'");
        parent::tearDown();
    }

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
    // CLA-529: the delay is evaluated on the application clock (the clock
    // ExecuteCampaignJob writes finished_at with), NOT MySQL's NOW() which follows
    // the DB session time zone. Each case pins the session to +00:00 — a zone that
    // differs from app.timezone — so a NOW()-based comparison would drift by that
    // offset and fail these boundary assertions.
    // -------------------------------------------------------------------------

    public function test_followup_delay_uses_the_application_clock_summer_dst(): void
    {
        $this->assertFollowUpDelayIgnoresSessionTimeZone(Carbon::create(2026, 7, 15, 12, 0, 0, 'Europe/Brussels'));
    }

    public function test_followup_delay_uses_the_application_clock_winter(): void
    {
        $this->assertFollowUpDelayIgnoresSessionTimeZone(Carbon::create(2026, 1, 15, 12, 0, 0, 'Europe/Brussels'));
    }

    private function assertFollowUpDelayIgnoresSessionTimeZone(Carbon $appNow): void
    {
        config(['app.timezone' => 'Europe/Brussels']);
        Carbon::setTestNow($appNow);
        // Pin the DB session to a zone that differs from app.timezone (the scenario
        // reproduced for CLA-529) so a NOW()-based due check would be wrong.
        DB::statement("SET time_zone = '+00:00'");

        Queue::fake();

        $parent = $this->completedParent(['followup_delay_hours' => 6]);

        // 1 s BEFORE the 6 h delay elapses on the application clock → not due.
        $parent->forceFill(['finished_at' => now()->subHours(6)->addSecond()])->save();
        $this->artisan('mailing:dispatch-followups')
            ->expectsOutputToContain('No follow-up campaigns due.')
            ->assertSuccessful();
        Queue::assertNothingPushed();

        // 1 s AFTER → due. (Audience is empty in this minimal fixture, so the command
        // marks followup_dispatched_at without sending — the "empty audience" path;
        // the due evaluation is the point.)
        $parent->forceFill(['finished_at' => now()->subHours(6)->subSecond(), 'followup_dispatched_at' => null])->save();
        $this->artisan('mailing:dispatch-followups')
            ->expectsOutputToContain('Found 1 candidate(s).')
            ->assertSuccessful();
        $this->assertNotNull($parent->fresh()->followup_dispatched_at);
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
