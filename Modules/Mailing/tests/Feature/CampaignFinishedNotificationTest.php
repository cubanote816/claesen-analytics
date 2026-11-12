<?php

namespace Modules\Mailing\Tests\Feature;

use App\Contracts\MarketingCampaignInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Modules\Core\Models\User;
use Modules\Mailing\Enums\CampaignStatus;
use Modules\Mailing\Enums\TemplateCategory;
use Modules\Mailing\Jobs\ExecuteCampaignJob;
use Modules\Mailing\Models\Campaign;
use Modules\Mailing\Models\CampaignMessage;
use Modules\Mailing\Models\EmailTemplate;
use Modules\Mailing\Services\SuppressionService;
use Modules\Prospects\Models\Prospect;
use Modules\Prospects\Models\ProspectLocation;
use Modules\Prospects\Models\Region;
use Tests\TestCase;

/**
 * CLA-677: the launcher gets one Filament database notification when a campaign run reaches
 * its final state, never for intermediate generations or the A/B first pass.
 */
class CampaignFinishedNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['mailing.send_delay_ms' => 0, 'app.locale' => 'en']);
    }

    private function mockMailer(bool $succeeds = true): void
    {
        $mock = Mockery::mock(MarketingCampaignInterface::class);
        $mock->shouldReceive('sendCampaign')->andReturn($succeeds);
        $this->app->instance(MarketingCampaignInterface::class, $mock);
    }

    private function prospect(array $emails): Prospect
    {
        $region = Region::firstOrCreate(['name' => 'Test Region'], ['slug' => 'test-region']);
        $p      = Prospect::create(['name' => 'Club-' . uniqid(), 'region_id' => $region->id, 'unsubscribed_at' => null]);

        foreach ($emails as $i => $email) {
            ProspectLocation::create([
                'prospect_id'  => $p->id,
                'contact_type' => $i === 0 ? 'main' : 'secondary' . $i,
                'email'        => $email,
            ]);
        }

        return $p;
    }

    private function inlineJob(array $prospectIds, ?int $userId, ?string $dispatchKey = null): ExecuteCampaignJob
    {
        $template = EmailTemplate::firstOrCreate(['name' => 'Notify Template'], [
            'subject'  => 'Hello {{ name }}',
            'body'     => '<p>Dear {{ name }}</p>',
            'category' => TemplateCategory::TRANSACTIONAL,
        ]);

        return new ExecuteCampaignJob(
            prospectIds: $prospectIds,
            templateId: $template->id,
            userId: $userId,
            description: 'Spring mailing',
            dispatchKey: $dispatchKey,
        );
    }

    private function handle(ExecuteCampaignJob $job): void
    {
        $job->handle(app(MarketingCampaignInterface::class), app(SuppressionService::class));
    }

    private function existingCampaign(array $attrs = []): Campaign
    {
        return Campaign::factory()->create(array_merge([
            'status'           => CampaignStatus::APPROVED,
            'description'      => 'Scheduled mailing',
            'template_id'      => EmailTemplate::factory()->create()->id,
            'subject_snapshot' => 'Subject A',
            'body_snapshot'    => '<p>Hi</p>',
        ], $attrs));
    }

    private function notificationsFor(User $user)
    {
        return DB::table('notifications')
            ->where('notifiable_type', $user->getMorphClass())
            ->where('notifiable_id', $user->id)
            ->get()
            ->map(fn ($row) => json_decode($row->data, true));
    }

    public function test_inline_send_notifies_the_launching_user_once_with_the_counts(): void
    {
        $this->mockMailer();
        $user = User::factory()->create();
        $p1   = $this->prospect(['a@example.com']);
        $p2   = $this->prospect(['b@example.com']);
        $p3   = $this->prospect([]);

        $this->handle($this->inlineJob([$p1->id, $p2->id, $p3->id], $user->id));

        $rows = $this->notificationsFor($user);
        $this->assertCount(1, $rows);
        $this->assertSame('filament', $rows[0]['format']);
        $this->assertSame('Spring mailing finished', $rows[0]['title']);
        $this->assertSame('Sent: 2 · Failed: 0 · Skipped: 1', $rows[0]['body']);
        $this->assertStringContainsString('/campaigns/' . Campaign::first()->id, $rows[0]['actions'][0]['url']);
        $this->assertSame(1, DB::table('notifications')->count());
    }

    public function test_inline_send_spanning_generations_notifies_only_at_the_end(): void
    {
        config(['mailing.run_budget_seconds' => 0]);
        $this->mockMailer();
        $user = User::factory()->create();
        $p1   = $this->prospect(['a@example.com']);
        $p2   = $this->prospect(['b@example.com']);
        $p3   = $this->prospect(['c@example.com']);

        $job  = $this->inlineJob([$p1->id, $p2->id, $p3->id], $user->id);
        $runs = 0;
        while ($job !== null && $runs < 10) {
            // Fake only the continuation: Filament's database notification is itself queued.
            Queue::fake([ExecuteCampaignJob::class]);
            $this->handle($job);
            $runs++;
            $pushed = Queue::pushed(ExecuteCampaignJob::class);
            $job    = $pushed->isEmpty() ? null : $pushed->first();

            $expected = $job === null ? 1 : 0;
            $this->assertSame($expected, DB::table('notifications')->count(), "after run {$runs}");
        }

        $this->assertGreaterThan(1, $runs);
        $this->assertSame('Sent: 3 · Failed: 0 · Skipped: 0', $this->notificationsFor($user)[0]['body']);
    }

    public function test_inline_run_where_every_send_fails_sends_a_failed_notification(): void
    {
        $this->mockMailer(false);
        $user = User::factory()->create();
        $p    = $this->prospect(['a@example.com']);

        $this->handle($this->inlineJob([$p->id], $user->id));

        $rows = $this->notificationsFor($user);
        $this->assertCount(1, $rows);
        $this->assertSame('Spring mailing failed', $rows[0]['title']);
        $this->assertSame('Sent: 0 · Failed: 1 · Skipped: 0', $rows[0]['body']);
    }

    public function test_existing_campaign_notifies_the_approver(): void
    {
        $this->mockMailer();
        $creator  = User::factory()->create();
        $approver = User::factory()->create();
        $campaign = $this->existingCampaign(['created_by' => $creator->id, 'approved_by' => $approver->id]);
        $p        = $this->prospect(['a@example.com']);

        $this->handle(new ExecuteCampaignJob(campaignId: $campaign->id, overrideProspectIds: [$p->id]));

        $this->assertCount(1, $this->notificationsFor($approver));
        $this->assertCount(0, $this->notificationsFor($creator));
        $this->assertSame('Scheduled mailing finished', $this->notificationsFor($approver)[0]['title']);
        $this->assertSame('Sent: 1 · Failed: 0 · Skipped: 0', $this->notificationsFor($approver)[0]['body']);
    }

    public function test_existing_campaign_without_approver_notifies_the_creator(): void
    {
        $this->mockMailer();
        $creator  = User::factory()->create();
        $campaign = $this->existingCampaign(['created_by' => $creator->id]);
        $p        = $this->prospect(['a@example.com']);

        $this->handle(new ExecuteCampaignJob(campaignId: $campaign->id, overrideProspectIds: [$p->id]));

        $this->assertCount(1, $this->notificationsFor($creator));
        $this->assertSame(1, DB::table('notifications')->count());
    }

    public function test_ab_first_pass_does_not_notify_but_winner_send_completion_does(): void
    {
        $this->mockMailer();
        $creator  = User::factory()->create();
        $campaign = $this->existingCampaign([
            'created_by'       => $creator->id,
            'ab_subject_b'     => 'Subject B',
            'ab_split_percent' => 20,
        ]);
        foreach (range(1, 5) as $i) {
            $this->prospect(["p{$i}@example.com"]);
        }

        $this->handle(new ExecuteCampaignJob(campaignId: $campaign->id));

        $this->assertSame(CampaignStatus::SENDING, $campaign->fresh()->status);
        $this->assertSame(0, DB::table('notifications')->count());

        $campaign->update(['ab_winner_variant' => 'A']);
        $this->handle(new ExecuteCampaignJob(campaignId: $campaign->id, isWinnerSend: true));

        $this->assertSame(CampaignStatus::COMPLETED, $campaign->fresh()->status);
        $rows = $this->notificationsFor($creator);
        $this->assertCount(1, $rows);
        $this->assertSame('Sent: 5 · Failed: 0 · Skipped: 0', $rows[0]['body']);
    }

    public function test_ab_winner_send_with_nobody_left_notifies_once(): void
    {
        $this->mockMailer();
        $creator  = User::factory()->create();
        $p        = $this->prospect(['a@example.com']);
        $campaign = $this->existingCampaign([
            'status'            => CampaignStatus::SENDING,
            'created_by'        => $creator->id,
            'ab_subject_b'      => 'Subject B',
            'ab_split_percent'  => 50,
            'ab_winner_variant' => 'B',
        ]);
        CampaignMessage::factory()->sent()->create(['campaign_id' => $campaign->id, 'prospect_id' => $p->id, 'ab_variant' => 'A']);

        $this->handle(new ExecuteCampaignJob(campaignId: $campaign->id, isWinnerSend: true));

        $this->assertCount(1, $this->notificationsFor($creator));
    }

    public function test_invalid_snapshot_failure_sends_one_failed_notification(): void
    {
        $creator  = User::factory()->create();
        $campaign = $this->existingCampaign(['created_by' => $creator->id, 'template_category_snapshot' => null, 'preference_category_snapshot' => null]);

        try {
            $this->handle(new ExecuteCampaignJob(campaignId: $campaign->id));
            $this->fail('Expected DomainException');
        } catch (\DomainException) {
        }

        $this->assertSame(CampaignStatus::FAILED, $campaign->fresh()->status);
        $rows = $this->notificationsFor($creator);
        $this->assertCount(1, $rows);
        $this->assertSame('Scheduled mailing failed', $rows[0]['title']);
    }

    public function test_job_failure_hook_notifies_once(): void
    {
        $user = User::factory()->create();
        $job  = $this->inlineJob([1], $user->id);
        Campaign::factory()->create([
            'status'         => CampaignStatus::SENDING,
            'description'    => 'Spring mailing',
            'dispatch_key'   => $job->dispatchKey,
            'run_generation' => 0,
        ]);

        $job->failed(new \RuntimeException('boom'));
        $job->failed(new \RuntimeException('boom again'));

        $rows = $this->notificationsFor($user);
        $this->assertCount(1, $rows);
        $this->assertSame('Spring mailing failed', $rows[0]['title']);
    }

    public function test_empty_audience_completes_with_zeros_and_notifies(): void
    {
        $this->mockMailer();
        $creator  = User::factory()->create();
        $campaign = $this->existingCampaign(['created_by' => $creator->id]);

        $this->handle(new ExecuteCampaignJob(campaignId: $campaign->id, overrideProspectIds: []));

        $this->assertSame(CampaignStatus::COMPLETED, $campaign->fresh()->status);
        $rows = $this->notificationsFor($creator);
        $this->assertCount(1, $rows);
        $this->assertSame('Sent: 0 · Failed: 0 · Skipped: 0', $rows[0]['body']);
    }

    public function test_no_resolvable_user_means_no_notification_and_no_exception(): void
    {
        $this->mockMailer();
        $campaign = $this->existingCampaign(['created_by' => null, 'approved_by' => null]);
        $p        = $this->prospect(['a@example.com']);

        $this->handle(new ExecuteCampaignJob(campaignId: $campaign->id, overrideProspectIds: [$p->id]));

        $this->assertSame(CampaignStatus::COMPLETED, $campaign->fresh()->status);
        $this->assertSame(1, $campaign->fresh()->sent_count);
        $this->assertSame(0, DB::table('notifications')->count());
    }

    // CLA-677 verify: "Send now" notifies whoever pressed it, not the approver.
    public function test_send_now_notifies_the_actual_launcher(): void
    {
        $this->mockMailer();
        $approver = User::factory()->create();
        $launcher = User::factory()->create();
        $campaign = $this->existingCampaign([
            'approved_by'  => $approver->id,
            'scheduled_at' => null,
        ]);
        $this->actingAs($launcher);
        Queue::fake([ExecuteCampaignJob::class]);

        $this->assertTrue($campaign->dispatchImmediateSend());
        $this->handle(Queue::pushed(ExecuteCampaignJob::class)->first());

        $this->assertCount(1, $this->notificationsFor($launcher));
        $this->assertCount(0, $this->notificationsFor($approver));
    }

    // CLA-677 verify: the bell row is written synchronously, so a queue outage cannot lose it.
    public function test_terminal_notice_does_not_depend_on_the_queue(): void
    {
        $user = User::factory()->create();
        $campaign = $this->existingCampaign(['created_by' => $user->id]);
        $this->mockMailer();
        \Illuminate\Support\Facades\Bus::partialMock()
            ->shouldReceive('dispatch')
            ->andThrow(new \RuntimeException('queue down'));

        $this->handle(new ExecuteCampaignJob(campaignId: $campaign->id, overrideProspectIds: []));

        $this->assertCount(1, $this->notificationsFor($user));
    }

    // CLA-677 verify: keep the pre-CLA-677 outcome when failed() closes the run mid-send.
    public function test_active_send_keeps_the_computed_final_outcome(): void
    {
        $user = User::factory()->create();
        $campaign = $this->existingCampaign(['status' => CampaignStatus::SENDING, 'created_by' => $user->id]);
        $p = $this->prospect(['race@example.com']);
        $job = new ExecuteCampaignJob(campaignId: $campaign->id, overrideProspectIds: [$p->id]);
        $mailer = Mockery::mock(MarketingCampaignInterface::class);
        $mailer->shouldReceive('sendCampaign')->once()->andReturnUsing(function () use ($job) {
            $job->failed(new \RuntimeException('competing delivery failed'));

            return true;
        });
        $this->app->instance(MarketingCampaignInterface::class, $mailer);

        $this->handle($job);

        $fresh = $campaign->fresh();
        $this->assertSame(CampaignStatus::COMPLETED, $fresh->status);
        $this->assertSame(1, $fresh->sent_count);
        $this->assertNotNull($fresh->finished_at);
        // Exactly one notice for the run (the one failed() sent), never a second one.
        $this->assertCount(1, $this->notificationsFor($user));
    }
}
