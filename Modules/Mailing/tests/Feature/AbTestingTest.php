<?php

namespace Modules\Mailing\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Modules\Mailing\Enums\CampaignStatus;
use Modules\Mailing\Enums\MessageEventType;
use Modules\Mailing\Jobs\ExecuteCampaignJob;
use Modules\Mailing\Models\Campaign;
use Modules\Mailing\Models\CampaignMessage;
use Modules\Mailing\Models\EmailTemplate;
use Modules\Mailing\Models\MessageEvent;
use Modules\Mailing\Services\SuppressionService;
use Modules\Prospects\Models\Prospect;
use Tests\TestCase;

class AbTestingTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function abCampaign(array $attrs = []): Campaign
    {
        return Campaign::factory()->create(array_merge([
            'status'               => CampaignStatus::APPROVED,
            'template_id'          => EmailTemplate::factory()->create()->id,
            'subject_snapshot'     => 'Subject A',
            'ab_subject_b'         => 'Subject B',
            'ab_split_percent'     => 50,    // 50% A + 50% B in test
            'ab_winner_after_hours' => 1,
        ], $attrs));
    }

    private function mockMailer(): \App\Contracts\MarketingCampaignInterface
    {
        $mailer = Mockery::mock(\App\Contracts\MarketingCampaignInterface::class);
        $mailer->shouldReceive('sendCampaign')->andReturn(true);
        $this->app->instance(\App\Contracts\MarketingCampaignInterface::class, $mailer);
        return $mailer;
    }

    private function prospect(): Prospect
    {
        return Prospect::factory()->create(['unsubscribed_at' => null]);
    }

    // -------------------------------------------------------------------------
    // A/B first pass
    // -------------------------------------------------------------------------

    public function test_ab_first_pass_sends_to_split_groups_and_stays_sending(): void
    {
        $this->mockMailer();

        $campaign  = $this->abCampaign(['ab_split_percent' => 50]);
        $p1        = $this->prospect();
        $p2        = $this->prospect();

        // Fake resolveAudience by stubbing — use a partial mock approach
        // Since we can't easily fake the DB query, we seed messages directly via job
        // Actually, for this test we use the real DB with real prospects

        $job = new ExecuteCampaignJob(campaignId: $campaign->id);
        $job->handle(
            app(\App\Contracts\MarketingCampaignInterface::class),
            app(SuppressionService::class),
        );

        // Campaign should still be SENDING (not completed) after A/B first pass
        $this->assertDatabaseHas('mailing_campaigns', [
            'id'     => $campaign->id,
            'status' => CampaignStatus::SENDING->value,
        ]);

        // ab_test_started_at must be set
        $this->assertNotNull($campaign->fresh()->ab_test_started_at);
    }

    public function test_ab_messages_are_tagged_with_correct_variant(): void
    {
        $this->mockMailer();

        $campaign = $this->abCampaign(['ab_split_percent' => 50]);
        $this->prospect();
        $this->prospect();

        $job = new ExecuteCampaignJob(campaignId: $campaign->id);
        $job->handle(
            app(\App\Contracts\MarketingCampaignInterface::class),
            app(SuppressionService::class),
        );

        $variants = CampaignMessage::where('campaign_id', $campaign->id)
            ->where('status', 'sent')
            ->pluck('ab_variant')
            ->sort()
            ->values()
            ->toArray();

        $this->assertContains('A', $variants);
        $this->assertContains('B', $variants);
    }

    public function test_ab_first_pass_is_idempotent_when_run_twice(): void
    {
        $this->mockMailer();

        $campaign = $this->abCampaign(['ab_split_percent' => 50]);
        $this->prospect();
        $this->prospect();

        $job = new ExecuteCampaignJob(campaignId: $campaign->id);

        $job->handle(app(\App\Contracts\MarketingCampaignInterface::class), app(SuppressionService::class));
        $countAfterFirst = CampaignMessage::where('campaign_id', $campaign->id)->count();

        // Second run: should be a no-op (ab_test_started_at already set)
        $job->handle(app(\App\Contracts\MarketingCampaignInterface::class), app(SuppressionService::class));
        $countAfterSecond = CampaignMessage::where('campaign_id', $campaign->id)->count();

        $this->assertSame($countAfterFirst, $countAfterSecond, 'Second run must not create additional messages.');
    }

    public function test_audience_too_small_falls_back_to_normal_send(): void
    {
        $this->mockMailer();

        $campaign = $this->abCampaign(['ab_split_percent' => 50]);
        // Only 1 prospect — too small for two groups
        $this->prospect();

        $job = new ExecuteCampaignJob(campaignId: $campaign->id);
        $job->handle(app(\App\Contracts\MarketingCampaignInterface::class), app(SuppressionService::class));

        // Should complete as normal campaign
        $this->assertDatabaseHas('mailing_campaigns', [
            'id'     => $campaign->id,
            'status' => CampaignStatus::COMPLETED->value,
        ]);
    }

    public function test_invalid_split_percent_throws_exception(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/ab_split_percent/');

        $campaign = $this->abCampaign(['ab_split_percent' => 60, 'status' => CampaignStatus::SENDING]);
        // Pre-set ab_test_started_at to null explicitly (so it would attempt to run)
        Campaign::where('id', $campaign->id)->update(['ab_test_started_at' => null]);

        $this->prospect();
        $this->prospect();

        $job = new ExecuteCampaignJob(campaignId: $campaign->id);
        $job->handle(app(\App\Contracts\MarketingCampaignInterface::class), app(SuppressionService::class));
    }

    // -------------------------------------------------------------------------
    // SelectAbWinnerCommand
    // -------------------------------------------------------------------------

    public function test_winner_not_selected_before_window(): void
    {
        Queue::fake();

        $campaign = $this->abCampaign([
            'status'                => CampaignStatus::SENDING,
            'ab_test_started_at'   => now()->subMinutes(30), // only 30min, need 1h
            'ab_winner_after_hours' => 1,
        ]);

        $this->artisan('mailing:ab-select-winner')->assertSuccessful();

        Queue::assertNothingPushed();
        $this->assertNull($campaign->fresh()->ab_winner_variant);
    }

    public function test_winner_selected_by_ctr_b_wins(): void
    {
        Queue::fake();

        $campaign = $this->abCampaign([
            'status'               => CampaignStatus::SENDING,
            'ab_test_started_at'   => now()->subHours(2),
            'ab_winner_after_hours' => 1,
        ]);

        $p1 = $this->prospect();
        $p2 = $this->prospect();
        $p3 = $this->prospect();
        $p4 = $this->prospect();

        // Variant A: 2 sent, 0 clicks (CTR = 0%)
        $msgA1 = CampaignMessage::factory()->sent()->create(['campaign_id' => $campaign->id, 'prospect_id' => $p1->id, 'ab_variant' => 'A']);
        $msgA2 = CampaignMessage::factory()->sent()->create(['campaign_id' => $campaign->id, 'prospect_id' => $p2->id, 'ab_variant' => 'A']);

        // Variant B: 2 sent, 2 clicks (CTR = 100%)
        $msgB1 = CampaignMessage::factory()->sent()->create(['campaign_id' => $campaign->id, 'prospect_id' => $p3->id, 'ab_variant' => 'B']);
        $msgB2 = CampaignMessage::factory()->sent()->create(['campaign_id' => $campaign->id, 'prospect_id' => $p4->id, 'ab_variant' => 'B']);

        MessageEvent::create(['message_id' => $msgB1->id, 'event_type' => MessageEventType::CLICKED->value, 'occurred_at' => now()]);
        MessageEvent::create(['message_id' => $msgB2->id, 'event_type' => MessageEventType::CLICKED->value, 'occurred_at' => now()]);

        $this->artisan('mailing:ab-select-winner')->assertSuccessful();

        $this->assertSame('B', $campaign->fresh()->ab_winner_variant);
        Queue::assertPushed(ExecuteCampaignJob::class, function (ExecuteCampaignJob $job) use ($campaign): bool {
            return $job->campaignId === $campaign->id && $job->isWinnerSend === true;
        });
    }

    public function test_winner_tie_variant_a_wins(): void
    {
        Queue::fake();

        $campaign = $this->abCampaign([
            'status'               => CampaignStatus::SENDING,
            'ab_test_started_at'   => now()->subHours(2),
            'ab_winner_after_hours' => 1,
        ]);

        $p1 = $this->prospect();
        $p2 = $this->prospect();
        $p3 = $this->prospect();
        $p4 = $this->prospect();

        // Both variants: 2 sent, 0 clicks (CTR = 0% tie)
        CampaignMessage::factory()->sent()->create(['campaign_id' => $campaign->id, 'prospect_id' => $p1->id, 'ab_variant' => 'A']);
        CampaignMessage::factory()->sent()->create(['campaign_id' => $campaign->id, 'prospect_id' => $p2->id, 'ab_variant' => 'A']);
        CampaignMessage::factory()->sent()->create(['campaign_id' => $campaign->id, 'prospect_id' => $p3->id, 'ab_variant' => 'B']);
        CampaignMessage::factory()->sent()->create(['campaign_id' => $campaign->id, 'prospect_id' => $p4->id, 'ab_variant' => 'B']);

        $this->artisan('mailing:ab-select-winner')->assertSuccessful();

        $this->assertSame('A', $campaign->fresh()->ab_winner_variant, 'Tie-break: variant A must win.');
    }

    public function test_winner_command_is_idempotent_when_run_twice(): void
    {
        Queue::fake();

        $campaign = $this->abCampaign([
            'status'               => CampaignStatus::SENDING,
            'ab_test_started_at'   => now()->subHours(2),
            'ab_winner_after_hours' => 1,
        ]);

        $p1 = $this->prospect();
        $p2 = $this->prospect();
        CampaignMessage::factory()->sent()->create(['campaign_id' => $campaign->id, 'prospect_id' => $p1->id, 'ab_variant' => 'A']);
        CampaignMessage::factory()->sent()->create(['campaign_id' => $campaign->id, 'prospect_id' => $p2->id, 'ab_variant' => 'B']);

        $this->artisan('mailing:ab-select-winner')->assertSuccessful();
        $this->artisan('mailing:ab-select-winner')->assertSuccessful();

        Queue::assertPushed(ExecuteCampaignJob::class, 1); // not twice
    }

    public function test_command_skips_when_one_variant_has_zero_sent(): void
    {
        Queue::fake();

        $campaign = $this->abCampaign([
            'status'               => CampaignStatus::SENDING,
            'ab_test_started_at'   => now()->subHours(2),
            'ab_winner_after_hours' => 1,
        ]);

        // Only variant A messages — B has none
        $p1 = $this->prospect();
        CampaignMessage::factory()->sent()->create(['campaign_id' => $campaign->id, 'prospect_id' => $p1->id, 'ab_variant' => 'A']);

        $this->artisan('mailing:ab-select-winner')->assertSuccessful();

        Queue::assertNothingPushed();
        $this->assertNull($campaign->fresh()->ab_winner_variant);
    }

    // -------------------------------------------------------------------------
    // Winner send
    // -------------------------------------------------------------------------

    public function test_winner_send_delivers_to_remaining_prospects(): void
    {
        $this->mockMailer();

        $p1 = $this->prospect();
        $p2 = $this->prospect();
        $p3 = $this->prospect();

        $campaign = $this->abCampaign([
            'status'              => CampaignStatus::SENDING,
            'ab_winner_variant'   => 'B',
            'ab_test_started_at'  => now()->subHours(2),
        ]);

        // p1 and p2 already received A/B messages
        CampaignMessage::factory()->sent()->create(['campaign_id' => $campaign->id, 'prospect_id' => $p1->id, 'ab_variant' => 'A']);
        CampaignMessage::factory()->sent()->create(['campaign_id' => $campaign->id, 'prospect_id' => $p2->id, 'ab_variant' => 'B']);
        // p3 is remaining

        $job = new ExecuteCampaignJob(campaignId: $campaign->id, isWinnerSend: true);
        $job->handle(app(\App\Contracts\MarketingCampaignInterface::class), app(SuppressionService::class));

        // Campaign should be COMPLETED
        $this->assertDatabaseHas('mailing_campaigns', [
            'id'     => $campaign->id,
            'status' => CampaignStatus::COMPLETED->value,
        ]);
    }

    // -------------------------------------------------------------------------
    // Non-AB campaign is unaffected
    // -------------------------------------------------------------------------

    public function test_non_ab_campaign_runs_normally(): void
    {
        $this->mockMailer();

        $campaign = Campaign::factory()->create([
            'status'           => CampaignStatus::APPROVED,
            'template_id'      => EmailTemplate::factory()->create()->id,
            'subject_snapshot' => 'Normal subject',
            'ab_subject_b'     => null,
        ]);

        $this->prospect();

        $job = new ExecuteCampaignJob(campaignId: $campaign->id);
        $job->handle(app(\App\Contracts\MarketingCampaignInterface::class), app(SuppressionService::class));

        // Normal campaign completes and has no ab_variant on messages
        $this->assertDatabaseHas('mailing_campaigns', [
            'id'     => $campaign->id,
            'status' => CampaignStatus::COMPLETED->value,
        ]);

        $this->assertDatabaseMissing('mailing_messages', [
            'campaign_id' => $campaign->id,
            'ab_variant'  => 'A',
        ]);
    }
}
