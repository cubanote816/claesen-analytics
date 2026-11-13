<?php

namespace Modules\Mailing\Tests\Feature;

use App\Contracts\MarketingCampaignInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Sleep;
use Modules\Mailing\Enums\CampaignStatus;
use Modules\Mailing\Enums\TemplateCategory;
use Modules\Mailing\Jobs\ExecuteCampaignJob;
use Modules\Mailing\Models\Campaign;
use Modules\Mailing\Models\CampaignMessage;
use Modules\Mailing\Models\EmailTemplate;
use Modules\Mailing\Services\MicrosoftGraphMailer;
use Modules\Mailing\Services\MicrosoftGraphService;
use Modules\Mailing\Services\SuppressionService;
use Modules\Prospects\Models\Prospect;
use Modules\Prospects\Models\ProspectLocation;
use Modules\Prospects\Models\Region;
use Tests\TestCase;

/**
 * CLA-681: transient Graph errors (429/503) are retried inside the run budget;
 * everything else is not, and the real failure reason reaches error_message.
 */
class GraphTransientRetryTest extends TestCase
{
    use RefreshDatabase;

    private const SEND_URL = 'graph.microsoft.com/v1.0/users/*/sendMail';

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'mailing.send_delay_ms'                      => 0,
            'mailing.graph_retry_attempts'               => 2,
            'mailing.graph_retry_backoff_seconds'        => [1, 3],
            'mailing.run_budget_seconds'                 => 20,
            'mail.mailers.microsoft-graph.client_id'     => 'test-client-id',
            'mail.mailers.microsoft-graph.tenant_id'     => 'test-tenant-id',
            'mail.mailers.microsoft-graph.client_secret' => 'test-client-secret',
        ]);
        Cache::put('microsoft_graph_token', 'fake-token', 60);
        Sleep::fake();
        Queue::fake();
        $this->app->instance(MarketingCampaignInterface::class, new MicrosoftGraphMailer);
    }

    protected function tearDown(): void
    {
        MicrosoftGraphService::setSendDeadline(null);
        Sleep::fake(false);
        parent::tearDown();
    }

    private function graphError(int $status, string $code = 'UnknownError', string $message = '', array $headers = [])
    {
        return Http::response(['error' => ['code' => $code, 'message' => $message]], $status, $headers);
    }

    private function errorThenAccepted(int $status, array $headers = [])
    {
        return Http::sequence()
            ->push(['error' => ['code' => $status === 429 ? 'TooManyRequests' : 'UnknownError', 'message' => '']], $status, $headers)
            ->push('', 202);
    }

    private function sendCalls(): int
    {
        return Http::recorded(fn (Request $r) => str_ends_with($r->url(), '/sendMail'))->count();
    }

    private function runCampaign(bool $inline = false): Campaign
    {
        $region   = Region::firstOrCreate(['name' => 'Test Region'], ['slug' => 'test-region']);
        $prospect = Prospect::create(['name' => 'Club-' . uniqid(), 'region_id' => $region->id]);
        ProspectLocation::create([
            'prospect_id'  => $prospect->id,
            'contact_type' => 'main',
            'email'        => 'club@example.com',
        ]);
        $tpl = EmailTemplate::create(['name' => 'T', 'subject' => 'Hello {{ name }}', 'body' => '<p>Dear {{ name }}</p>', 'category' => TemplateCategory::TRANSACTIONAL]);
        $campaign = Campaign::factory()->create([
            'status'           => CampaignStatus::APPROVED,
            'template_id'      => $tpl->id,
            'subject_snapshot' => $tpl->subject,
            'body_snapshot'    => $tpl->body,
        ]);

        // Inline runs carry the CLA-654 deadline; campaign-id runs have none.
        $job = $inline
            ? new ExecuteCampaignJob(prospectIds: [$prospect->id], templateId: $tpl->id)
            : new ExecuteCampaignJob(campaignId: $campaign->id, overrideProspectIds: [$prospect->id]);
        $job->handle(app(MarketingCampaignInterface::class), app(SuppressionService::class));

        return $inline ? Campaign::latest('id')->first() : $campaign->fresh();
    }

    // S1
    public function test_503_then_202_is_retried_once_and_counted_as_sent(): void
    {
        Http::fake([self::SEND_URL => $this->errorThenAccepted(503)]);

        $campaign = $this->runCampaign();

        $this->assertSame(2, $this->sendCalls());
        $this->assertSame(1, $campaign->sent_count);
        $this->assertSame(0, $campaign->failed_count);
        $this->assertSame('sent', CampaignMessage::first()->status);
        $this->assertNull(CampaignMessage::first()->error_message);
        Sleep::assertSleptTimes(1);
        Sleep::assertSlept(fn ($d) => (int) $d->totalMilliseconds === 1000);
    }

    // S1
    public function test_429_retry_after_header_is_honoured(): void
    {
        Http::fake([self::SEND_URL => $this->errorThenAccepted(429, ['Retry-After' => '2'])]);

        $campaign = $this->runCampaign();

        $this->assertSame(2, $this->sendCalls());
        $this->assertSame(1, $campaign->sent_count);
        Sleep::assertSleptTimes(1);
        Sleep::assertSlept(fn ($d) => (int) $d->totalMilliseconds === 2000);
    }

    // S1
    public function test_retry_after_is_capped(): void
    {
        Http::fake([self::SEND_URL => $this->errorThenAccepted(429, ['Retry-After' => '3600'])]);
        // Wide budget so the cap, not the deadline, decides.
        config(['mailing.run_budget_seconds' => 600]);

        $this->runCampaign();

        Sleep::assertSlept(fn ($d) => (int) $d->totalMilliseconds === 10000);
    }

    // S1, S4
    public function test_three_503s_stop_after_two_retries_and_store_the_real_reason(): void
    {
        Http::fake([self::SEND_URL => $this->graphError(503)]);

        $campaign = $this->runCampaign();

        $this->assertSame(3, $this->sendCalls());
        $this->assertSame(0, $campaign->sent_count);
        $this->assertSame(1, $campaign->failed_count);
        $this->assertSame(CampaignStatus::FAILED, $campaign->status);
        $this->assertSame('graph 503 UnknownError', CampaignMessage::first()->error_message);
        $this->assertSame('failed', CampaignMessage::first()->status);
        Sleep::assertSleptTimes(2);
        Sleep::assertSlept(fn ($d) => (int) $d->totalMilliseconds === 1000, 1);
        Sleep::assertSlept(fn ($d) => (int) $d->totalMilliseconds === 3000, 1);
    }

    // S4
    public function test_reason_includes_graph_message_truncated_and_no_secrets(): void
    {
        Http::fake([self::SEND_URL => $this->graphError(403, 'ErrorSendAsDenied', str_repeat('x', 500))]);

        $this->runCampaign();

        $reason = CampaignMessage::first()->error_message;
        $this->assertStringStartsWith('graph 403 ErrorSendAsDenied: xxx', $reason);
        $this->assertLessThanOrEqual(260, mb_strlen($reason));
        $this->assertStringNotContainsString('fake-token', $reason);
    }

    // S3, S4
    public function test_502_is_not_retried(): void
    {
        Http::fake([self::SEND_URL => $this->graphError(502)]);

        $campaign = $this->runCampaign();

        $this->assertSame(1, $this->sendCalls());
        $this->assertSame(1, $campaign->failed_count);
        $this->assertSame('graph 502 UnknownError', CampaignMessage::first()->error_message);
        Sleep::assertNeverSlept();
    }

    // S3
    public function test_504_is_not_retried(): void
    {
        Http::fake([self::SEND_URL => $this->graphError(504)]);

        $campaign = $this->runCampaign();

        $this->assertSame(1, $this->sendCalls());
        $this->assertSame(1, $campaign->failed_count);
        $this->assertSame('graph 504 UnknownError', CampaignMessage::first()->error_message);
        Sleep::assertNeverSlept();
    }

    // S3
    public function test_client_errors_are_not_retried(): void
    {
        Http::fake([self::SEND_URL => $this->graphError(400, 'ErrorInvalidRecipients')]);

        $this->runCampaign();

        $this->assertSame(1, $this->sendCalls());
        $this->assertSame('graph 400 ErrorInvalidRecipients', CampaignMessage::first()->error_message);
    }

    // S2
    public function test_retry_is_skipped_when_the_wait_does_not_fit_the_run_budget(): void
    {
        Http::fake([self::SEND_URL => $this->graphError(503)]);
        // First send always runs; the 1 s backoff does not fit a 0 s budget.
        config(['mailing.run_budget_seconds' => 0]);

        $campaign = $this->runCampaign(inline: true);

        $this->assertSame(1, $this->sendCalls());
        $this->assertSame(1, $campaign->failed_count);
        $this->assertSame('graph 503 UnknownError', CampaignMessage::first()->error_message);
        Sleep::assertNeverSlept();
    }

    // S2
    public function test_service_deadline_blocks_a_retry_that_would_overrun(): void
    {
        Http::fake([self::SEND_URL => $this->graphError(503)]);
        MicrosoftGraphService::setSendDeadline(microtime(true) + 0.5);

        $this->assertFalse((new MicrosoftGraphService)->sendMail('s@example.com', ['message' => []]));

        $this->assertSame(1, $this->sendCalls());
        Sleep::assertNeverSlept();
    }

    // Retries are logged without secrets
    public function test_each_retry_is_logged_as_warning_with_status_and_attempt(): void
    {
        Http::fake([self::SEND_URL => $this->errorThenAccepted(503)]);
        Log::spy();

        $this->runCampaign();

        Log::shouldHaveReceived('warning')->withArgs(
            fn ($msg, $ctx = []) => str_contains($msg, 'retry') && ($ctx['status'] ?? null) === 503 && ($ctx['attempt'] ?? null) === 1
        )->once();
    }

    // S5
    public function test_success_path_is_unchanged(): void
    {
        Http::fake([self::SEND_URL => Http::response('', 202)]);

        $campaign = $this->runCampaign();

        $this->assertSame(1, $this->sendCalls());
        $this->assertSame(1, $campaign->sent_count);
        $this->assertSame(CampaignStatus::COMPLETED, $campaign->status);
        Sleep::assertNeverSlept();
    }

    // S4 fallback: no Graph reason available (connection failure) keeps the localized text
    public function test_connection_failure_is_not_retried_and_keeps_generic_message(): void
    {
        Http::fake([self::SEND_URL => fn () => throw new \Illuminate\Http\Client\ConnectionException('timeout')]);

        $campaign = $this->runCampaign();

        $this->assertSame(1, $campaign->failed_count);
        $this->assertSame(__('prospects::resource.notifications.error_mailer'), CampaignMessage::first()->error_message);
        Sleep::assertNeverSlept();
    }

    // CLA-681 verify: an HTTP-date Retry-After is honoured and capped like delta-seconds.
    public function test_http_date_retry_after_is_capped(): void
    {
        Http::fake([self::SEND_URL => $this->errorThenAccepted(
            429,
            ['Retry-After' => gmdate('D, d M Y H:i:s \G\M\T', time() + 60)]
        )]);

        $this->runCampaign();

        Sleep::assertSleptTimes(1);
        Sleep::assertSlept(fn ($d) => (int) $d->totalMilliseconds === 10000);
    }

    // CLA-681 verify: a connection failure on the retry (possibly accepted) is never retried again.
    public function test_connection_failure_during_retry_stops(): void
    {
        $calls = 0;
        Http::fake([self::SEND_URL => function () use (&$calls) {
            if (++$calls === 1) {
                return $this->graphError(503);
            }
            throw new \Illuminate\Http\Client\ConnectionException('timeout');
        }]);

        $campaign = $this->runCampaign();

        $this->assertSame(2, $calls);
        $this->assertSame(1, $campaign->failed_count);
        Sleep::assertSleptTimes(1);
    }

    // CLA-681 verify: runs without a deadline stop retrying after the retry window,
    // so 1 + 2 attempts cannot outlive the job timeout.
    public function test_runs_without_deadline_respect_the_retry_window(): void
    {
        config(['mailing.graph_retry_window_seconds' => 0]);
        Http::fake([self::SEND_URL => $this->errorThenAccepted(503)]);

        $campaign = $this->runCampaign();

        $this->assertSame(1, $this->sendCalls());
        $this->assertSame(1, $campaign->failed_count);
        Sleep::assertNeverSlept();
    }

    // CLA-681 review R4: the retry window is shared by the whole run, not restarted per
    // message, so many transient failures cannot add up past the job timeout.
    public function test_retry_window_is_shared_across_the_run(): void
    {
        config([
            'mailing.graph_retry_window_seconds'  => 1,
            'mailing.graph_retry_backoff_seconds' => [0, 0],
        ]);
        $calls = 0;
        Http::fake([self::SEND_URL => function () use (&$calls) {
            $calls++;
            if ($calls === 2) {
                usleep(1_100_000); // the first message's retry uses up the run's window
                return Http::response('', 202);
            }

            return $calls === 4 ? Http::response('', 202) : $this->graphError(503);
        }]);

        $region = Region::firstOrCreate(['name' => 'Test Region'], ['slug' => 'test-region']);
        $ids    = [];
        foreach (['a@example.com', 'b@example.com'] as $email) {
            $prospect = Prospect::create(['name' => 'Club-' . uniqid(), 'region_id' => $region->id]);
            ProspectLocation::create(['prospect_id' => $prospect->id, 'contact_type' => 'main', 'email' => $email]);
            $ids[] = $prospect->id;
        }
        $tpl = EmailTemplate::create(['name' => 'T', 'subject' => 'Hello {{ name }}', 'body' => '<p>Dear {{ name }}</p>', 'category' => TemplateCategory::TRANSACTIONAL]);
        $campaign = Campaign::factory()->create([
            'status'           => CampaignStatus::APPROVED,
            'template_id'      => $tpl->id,
            'subject_snapshot' => $tpl->subject,
            'body_snapshot'    => $tpl->body,
        ]);

        (new ExecuteCampaignJob(campaignId: $campaign->id, overrideProspectIds: $ids))
            ->handle(app(MarketingCampaignInterface::class), app(SuppressionService::class));
        $campaign->refresh();

        $this->assertSame(3, $calls);
        $this->assertSame(1, $campaign->sent_count);
        $this->assertSame(1, $campaign->failed_count);
    }
}
