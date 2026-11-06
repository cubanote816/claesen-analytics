<?php

namespace Modules\Mailing\Tests\Feature;

use App\Contracts\MarketingCampaignInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Modules\Mailing\Enums\CampaignStatus;
use Modules\Mailing\Enums\SuppressionReason;
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

class CampaignRunBudgetTest extends TestCase
{
    use RefreshDatabase;

    /** @var array<int, string> */
    private array $sentTo = [];

    private bool $thrown = false;

    protected function setUp(): void
    {
        parent::setUp();
        config(['mailing.send_delay_ms' => 0]);
    }

    private function prospect(array $emails): Prospect
    {
        $region = Region::firstOrCreate(['name' => 'Test Region'], ['slug' => 'test-region']);
        $p = Prospect::create(['name' => 'Club-' . uniqid(), 'region_id' => $region->id]);

        foreach ($emails as $i => $email) {
            ProspectLocation::create([
                'prospect_id'  => $p->id,
                'contact_type' => $i === 0 ? 'main' : 'secondary' . $i,
                'email'        => $email,
            ]);
        }

        return $p;
    }

    private function inlineJob(array $prospectIds, ?string $dispatchKey = null): ExecuteCampaignJob
    {
        $template = EmailTemplate::firstOrCreate(['name' => 'Budget Template'], [
            'subject'  => 'Hello {{ name }}',
            'body'     => '<p>Dear {{ name }}</p>',
            'category' => TemplateCategory::TRANSACTIONAL,
        ]);

        return new ExecuteCampaignJob(prospectIds: $prospectIds, templateId: $template->id, dispatchKey: $dispatchKey);
    }

    private function mockMailer(callable $behaviour): void
    {
        $mock = Mockery::mock(MarketingCampaignInterface::class);
        $mock->shouldReceive('sendCampaign')->andReturnUsing(
            function ($prospect, $emails) use ($behaviour) {
                $this->sentTo[] = $emails[0];

                return $behaviour($emails);
            }
        );
        $this->app->instance(MarketingCampaignInterface::class, $mock);
    }

    private function handle(ExecuteCampaignJob $job): void
    {
        $job->handle(app(MarketingCampaignInterface::class), app(SuppressionService::class));
    }

    public function test_default_run_budget_is_below_the_worker_timeout(): void
    {
        $config = require __DIR__ . '/../../config/config.php';

        $this->assertGreaterThan(0, $config['run_budget_seconds']);
        $this->assertLessThanOrEqual(45, $config['run_budget_seconds']);
    }

    public function test_exhausted_budget_dispatches_continuation_with_same_dispatch_key_and_keeps_campaign_sending(): void
    {
        Queue::fake();
        config(['mailing.run_budget_seconds' => 0]);
        $this->mockMailer(fn () => true);
        $p   = $this->prospect(['a@example.com', 'b@example.com', 'c@example.com']);
        $job = $this->inlineJob([$p->id]);

        $this->handle($job);

        $this->assertCount(1, $this->sentTo);
        $campaign = Campaign::first();
        $this->assertSame(CampaignStatus::SENDING, $campaign->status);
        $this->assertNull($campaign->finished_at);
        $this->assertSame(1, $campaign->sent_count);

        Queue::assertPushed(ExecuteCampaignJob::class, function (ExecuteCampaignJob $next) use ($job, $p) {
            return $next->dispatchKey === $job->dispatchKey
                && $next->campaignId === null
                && $next->prospectIds === [$p->id]
                && $next->templateId === $job->templateId;
        });
        Queue::assertPushed(ExecuteCampaignJob::class, 1);
    }

    public function test_chained_runs_send_every_address_exactly_once_and_complete_the_campaign(): void
    {
        Queue::fake();
        config(['mailing.run_budget_seconds' => 0]);
        $this->mockMailer(fn (array $emails) => $emails[0] !== 'b@example.com');
        $p1 = $this->prospect(['a@example.com', 'b@example.com']);
        $p2 = $this->prospect(['c@example.com']);
        $p3 = $this->prospect(['d@example.com']);
        app(SuppressionService::class)->suppress('d@example.com', SuppressionReason::HARD_BOUNCE);

        $job  = $this->inlineJob([$p1->id, $p2->id, $p3->id]);
        $runs = 0;
        while ($job !== null && $runs < 10) {
            Queue::fake();
            $this->handle($job);
            $runs++;
            $pushed = Queue::pushed(ExecuteCampaignJob::class);
            $job    = $pushed->isEmpty() ? null : $pushed->first();
        }

        $this->assertNull($job, 'chain must terminate');
        $this->assertSame(['a@example.com', 'b@example.com', 'c@example.com'], $this->sentTo);
        $this->assertSame(1, Campaign::count());

        $campaign = Campaign::first();
        $this->assertSame(CampaignStatus::COMPLETED, $campaign->status);
        $this->assertNotNull($campaign->finished_at);
        $this->assertSame(2, $campaign->sent_count);
        $this->assertSame(1, $campaign->failed_count);
        $this->assertSame(1, $campaign->skipped_count);
        $this->assertSame(4, CampaignMessage::where('campaign_id', $campaign->id)->count());
    }

    public function test_within_budget_run_completes_without_continuation(): void
    {
        Queue::fake();
        config(['mailing.run_budget_seconds' => 3600]);
        $this->mockMailer(fn () => true);
        $p = $this->prospect(['a@example.com', 'b@example.com']);

        $this->handle($this->inlineJob([$p->id]));

        Queue::assertNothingPushed();
        $this->assertSame(CampaignStatus::COMPLETED, Campaign::first()->status);
    }

    public function test_retried_run_does_not_duplicate_skip_rows_or_skipped_count(): void
    {
        config(['mailing.run_budget_seconds' => 3600]);
        $this->mockMailer(fn () => true);
        $unsub = $this->prospect(['u@example.com']);
        $unsub->update(['unsubscribed_at' => now()]);
        $noMail = $this->prospect([]);
        $multi  = $this->prospect(['a@example.com', 'b@example.com']);
        app(SuppressionService::class)->suppress('b@example.com', SuppressionReason::HARD_BOUNCE);

        $job = $this->inlineJob([$unsub->id, $noMail->id, $multi->id]);
        $this->handle($job);
        $before = CampaignMessage::count();
        $this->handle($job);

        $campaign = Campaign::first();
        $this->assertSame($before, CampaignMessage::count());
        $this->assertSame(3, $campaign->skipped_count);
        $this->assertSame(1, $campaign->sent_count);
        $this->assertSame(['a@example.com'], $this->sentTo);
    }

    public function test_counters_equal_message_rows_after_mid_run_exception(): void
    {
        config(['mailing.run_budget_seconds' => 3600]);
        $this->mockMailer(fn () => true);
        $p1 = $this->prospect(['a@example.com']);
        $p2 = $this->prospect(['b@example.com']);
        // A prospect whose language makes processing blow up mid-run is hard to fake;
        // instead let the mailer succeed once and make the second prospect's message write fail.
        CampaignMessage::creating(function (CampaignMessage $m) {
            if ($m->email === 'b@example.com') {
                throw new \RuntimeException('db died');
            }
        });

        try {
            $this->handle($this->inlineJob([$p1->id, $p2->id]));
            $this->fail('expected the run to die');
        } catch (\RuntimeException $e) {
            $this->assertSame('db died', $e->getMessage());
        }

        $campaign = Campaign::first();
        $this->assertSame(CampaignStatus::SENDING, $campaign->status);
        $this->assertSame(1, $campaign->sent_count);
        $this->assertSame(
            CampaignMessage::where('campaign_id', $campaign->id)->where('status', 'sent')->count(),
            $campaign->sent_count
        );
    }

    public function test_job_timeout_is_below_redis_retry_after_and_default_budget_is_twenty(): void
    {
        $config = require __DIR__ . '/../../config/config.php';
        $job    = new ExecuteCampaignJob(prospectIds: [1], templateId: 1);

        $this->assertSame(85, $job->timeout);
        $this->assertLessThan(config('queue.connections.redis.retry_after'), $job->timeout);
        $this->assertSame(20, $config['run_budget_seconds']);
        // 20 budget + 30 Graph + 15 OAuth + 2 delay must fit inside the job timeout.
        $this->assertLessThan($job->timeout, $config['run_budget_seconds'] + 30 + 15 + 2);
    }

    public function test_replayed_delivery_does_not_dispatch_a_second_successor(): void
    {
        Queue::fake();
        config(['mailing.run_budget_seconds' => 0]);
        $this->mockMailer(fn () => true);
        $p     = $this->prospect(['a@example.com', 'b@example.com', 'c@example.com']);
        $job   = $this->inlineJob([$p->id]);
        $retry = unserialize(serialize($job));

        $this->handle($job);
        $this->handle($retry);

        Queue::assertPushed(ExecuteCampaignJob::class, 1);
        $this->assertSame(1, Campaign::count());
        $this->assertSame(['a@example.com'], $this->sentTo);
        $this->assertSame(1, Campaign::first()->run_generation);
    }

    public function test_successor_is_dispatched_with_the_next_generation(): void
    {
        Queue::fake();
        config(['mailing.run_budget_seconds' => 0]);
        $this->mockMailer(fn () => true);
        $p = $this->prospect(['a@example.com', 'b@example.com']);

        $this->handle($this->inlineJob([$p->id]));

        Queue::assertPushed(ExecuteCampaignJob::class, fn (ExecuteCampaignJob $next) => $next->generation === 1);
    }

    public function test_stale_failure_does_not_close_a_progressing_chain(): void
    {
        Queue::fake();
        config(['mailing.run_budget_seconds' => 0]);
        $this->mockMailer(fn () => true);
        $p   = $this->prospect(['a@example.com', 'b@example.com', 'c@example.com']);
        $old = $this->inlineJob([$p->id]);

        $this->handle($old);
        $successor = Queue::pushed(ExecuteCampaignJob::class)->first();
        Queue::fake();
        $this->handle($successor);
        $old->failed(new \RuntimeException('stale'));

        $campaign = Campaign::first();
        $this->assertSame(CampaignStatus::SENDING, $campaign->status);
        $this->assertNull($campaign->finished_at);
        $this->assertSame(2, $campaign->sent_count);
        Queue::assertPushed(ExecuteCampaignJob::class, 1);
    }

    public function test_current_generation_failure_closes_the_campaign(): void
    {
        Queue::fake();
        config(['mailing.run_budget_seconds' => 0]);
        $this->mockMailer(fn () => true);
        $p   = $this->prospect(['a@example.com', 'b@example.com']);
        $job = $this->inlineJob([$p->id]);

        $this->handle($job);
        $successor = Queue::pushed(ExecuteCampaignJob::class)->first();
        $successor->failed(new \RuntimeException('fatal'));

        $campaign = Campaign::first();
        $this->assertSame(CampaignStatus::FAILED, $campaign->status);
        $this->assertNotNull($campaign->finished_at);
    }

    public function test_run_exits_without_sending_when_campaign_is_not_sending(): void
    {
        Queue::fake();
        config(['mailing.run_budget_seconds' => 0]);
        $this->mockMailer(fn () => true);
        $p   = $this->prospect(['a@example.com', 'b@example.com']);
        $job = $this->inlineJob([$p->id]);

        $this->handle($job);
        $successor = Queue::pushed(ExecuteCampaignJob::class)->first();
        Campaign::query()->update(['status' => CampaignStatus::FAILED->value, 'finished_at' => now()]);
        Queue::fake();
        $this->sentTo = [];

        $this->handle($successor);

        $this->assertSame([], $this->sentTo);
        Queue::assertNothingPushed();
        $this->assertSame(CampaignStatus::FAILED, Campaign::first()->status);
    }

    public function test_failed_successor_dispatch_rolls_back_the_handover_so_the_run_can_retry_it(): void
    {
        config(['mailing.run_budget_seconds' => 0]);
        $this->mockMailer(fn () => true);
        $p   = $this->prospect(['a@example.com', 'b@example.com', 'c@example.com']);
        $job = $this->inlineJob([$p->id]);

        $pushed = [];
        $queue  = Mockery::mock(\Illuminate\Contracts\Queue\Queue::class);
        $queue->shouldReceive('push')->andReturnUsing(function ($command) use (&$pushed) {
            if ($pushed === [] && ! $this->thrown) {
                $this->thrown = true;
                throw new \RuntimeException('redis down');
            }
            $pushed[] = $command;

            return 1;
        });
        $factory = Mockery::mock(\Illuminate\Contracts\Queue\Factory::class);
        $factory->shouldReceive('connection')->andReturn($queue);
        $this->app->instance(\Illuminate\Contracts\Queue\Factory::class, $factory);

        try {
            $this->handle($job);
            $this->fail('dispatch should have thrown');
        } catch (\RuntimeException $e) {
            $this->assertSame('redis down', $e->getMessage());
        }

        $campaign = Campaign::first();
        $this->assertSame(0, $campaign->run_generation);
        $this->assertSame(CampaignStatus::SENDING, $campaign->status);
        $this->assertSame([], $pushed);

        // Queue retry of the same run (generation 0) hands over again.
        $this->handle($job);

        $this->assertSame(1, Campaign::first()->run_generation);
        $this->assertCount(1, $pushed);
        $this->assertSame(1, $pushed[0]->generation);
        $this->assertSame($job->dispatchKey, $pushed[0]->dispatchKey);
        // The retry does not resend 'a'; it makes progress on 'b' before handing over again.
        $this->assertSame(['a@example.com', 'b@example.com'], $this->sentTo);
    }
}
