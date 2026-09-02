<?php

namespace Modules\Mailing\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Modules\Mailing\Enums\CampaignStatus;
use Modules\Mailing\Jobs\ExecuteCampaignJob;
use Modules\Mailing\Models\Campaign;
use Modules\Mailing\Models\EmailTemplate;
use Tests\TestCase;

class DispatchScheduledTest extends TestCase
{
    use RefreshDatabase;

    /**
     * scheduled_at is stored in UTC (CampaignForm converts Europe/Brussels -> UTC on
     * save) and DispatchScheduledCampaignsCommand compares with now()->utc(). Fixtures
     * must therefore express the "due"/"future" offset in UTC too, not the app-local
     * now(), or the comparison drifts by the UTC offset.
     */
    private function scheduledUtc(string $modifier): \Illuminate\Support\Carbon
    {
        return now()->utc()->modify($modifier);
    }

    private function approvedCampaign(array $attrs = []): Campaign
    {
        return Campaign::factory()->create(array_merge([
            'status'      => CampaignStatus::APPROVED,
            'template_id' => EmailTemplate::factory()->create()->id,
        ], $attrs));
    }

    // -------------------------------------------------------------------------
    // Basic dispatch logic
    // -------------------------------------------------------------------------

    public function test_dispatches_due_approved_campaign(): void
    {
        Queue::fake();

        $campaign = $this->approvedCampaign([
            'scheduled_at' => $this->scheduledUtc('-1 minute'),
        ]);

        $this->artisan('mailing:dispatch-scheduled')->assertSuccessful();

        Queue::assertPushed(ExecuteCampaignJob::class, function (ExecuteCampaignJob $job) use ($campaign): bool {
            return $job->campaignId === $campaign->id;
        });

        $this->assertDatabaseHas('mailing_campaigns', [
            'id'     => $campaign->id,
            'status' => CampaignStatus::SENDING->value,
        ]);
    }

    public function test_does_not_dispatch_future_campaign(): void
    {
        Queue::fake();

        $this->approvedCampaign(['scheduled_at' => $this->scheduledUtc('+1 hour')]);

        $this->artisan('mailing:dispatch-scheduled')->assertSuccessful();

        Queue::assertNothingPushed();
    }

    public function test_does_not_dispatch_campaign_without_scheduled_at(): void
    {
        Queue::fake();

        $this->approvedCampaign(['scheduled_at' => null]);

        $this->artisan('mailing:dispatch-scheduled')->assertSuccessful();

        Queue::assertNothingPushed();
    }

    public function test_does_not_dispatch_non_approved_campaigns(): void
    {
        Queue::fake();

        foreach ([CampaignStatus::DRAFT, CampaignStatus::REVIEW, CampaignStatus::SENDING, CampaignStatus::COMPLETED] as $status) {
            Campaign::factory()->create([
                'status'      => $status,
                'scheduled_at' => $this->scheduledUtc('-1 minute'),
                'template_id'  => EmailTemplate::factory()->create()->id,
            ]);
        }

        $this->artisan('mailing:dispatch-scheduled')->assertSuccessful();

        Queue::assertNothingPushed();
    }

    // -------------------------------------------------------------------------
    // Anti-duplicate: idempotency across two consecutive runs
    // -------------------------------------------------------------------------

    public function test_command_is_idempotent_when_run_twice_same_campaign(): void
    {
        Queue::fake();

        $campaign = $this->approvedCampaign([
            'scheduled_at' => $this->scheduledUtc('-1 minute'),
        ]);

        // First run: should claim (approved → sending) and dispatch.
        $this->artisan('mailing:dispatch-scheduled')->assertSuccessful();
        Queue::assertPushed(ExecuteCampaignJob::class, 1);

        // Verify the campaign was claimed in DB (status is now sending).
        $this->assertDatabaseHas('mailing_campaigns', [
            'id'     => $campaign->id,
            'status' => CampaignStatus::SENDING->value,
        ]);

        // Second run: campaign is no longer approved → must NOT dispatch again.
        $this->artisan('mailing:dispatch-scheduled')->assertSuccessful();
        Queue::assertPushed(ExecuteCampaignJob::class, 1); // still 1, not 2
    }

    // -------------------------------------------------------------------------
    // --dry-run does not mutate state or enqueue jobs
    // -------------------------------------------------------------------------

    public function test_dry_run_does_not_dispatch_or_claim(): void
    {
        Queue::fake();

        $campaign = $this->approvedCampaign([
            'scheduled_at' => $this->scheduledUtc('-1 minute'),
        ]);

        // Assert the run actually found this campaign — otherwise "nothing pushed /
        // status unchanged" would pass vacuously if the due filter matched nobody.
        $this->artisan('mailing:dispatch-scheduled --dry-run')
            ->expectsOutputToContain('Found 1 candidate(s).')
            ->expectsOutputToContain("[dry-run] campaign #{$campaign->id}")
            ->assertSuccessful();

        Queue::assertNothingPushed();

        // Status must remain approved — no atomic claim happened.
        $this->assertDatabaseHas('mailing_campaigns', [
            'id'     => $campaign->id,
            'status' => CampaignStatus::APPROVED->value,
        ]);
    }

    // -------------------------------------------------------------------------
    // Job validation: wrong status throws DomainException
    // -------------------------------------------------------------------------

    public function test_job_throws_domain_exception_for_non_sendable_campaign(): void
    {
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessageMatches('/cannot be sent/');

        $campaign = Campaign::factory()->create([
            'status'      => CampaignStatus::DRAFT,
            'template_id' => EmailTemplate::factory()->create()->id,
        ]);

        // Manually invoke the job — bypasses the queue
        $job = new ExecuteCampaignJob(campaignId: $campaign->id);
        $job->handle(
            app(\App\Contracts\MarketingCampaignInterface::class),
            app(\Modules\Mailing\Services\SuppressionService::class),
        );
    }
}
