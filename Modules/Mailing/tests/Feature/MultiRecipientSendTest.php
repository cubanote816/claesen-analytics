<?php

namespace Modules\Mailing\Tests\Feature;

use App\Contracts\MarketingCampaignInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

class MultiRecipientSendTest extends TestCase
{
    use RefreshDatabase;

    /** @var array<int, array{emails: array, token: ?string, html: string}> */
    private array $calls = [];

    protected function setUp(): void
    {
        parent::setUp();
        config(['mailing.send_delay_ms' => 0]);
        config([
            'mail.mailers.microsoft-graph.client_id'     => 'test-client-id',
            'mail.mailers.microsoft-graph.tenant_id'     => 'test-tenant-id',
            'mail.mailers.microsoft-graph.client_secret' => 'test-client-secret',
        ]);
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

    private function template(): EmailTemplate
    {
        return EmailTemplate::create([
            'name'     => 'Inline Template',
            'subject'  => 'Hello {{ name }}',
            'body'     => '<p>Dear {{ name }}</p><a href="https://example.com/x">x</a>',
            'category' => TemplateCategory::TRANSACTIONAL,
        ]);
    }

    /** @param callable(array $emails): bool|never $behaviour */
    private function mockMailer(callable $behaviour): void
    {
        $mock = Mockery::mock(MarketingCampaignInterface::class);
        $mock->shouldReceive('sendCampaign')->andReturnUsing(
            function ($prospect, $emails, $subject, $html, $unsub, $token) use ($behaviour) {
                $this->calls[] = ['emails' => $emails, 'token' => $token, 'html' => $html];

                return $behaviour($emails);
            }
        );
        $this->app->instance(MarketingCampaignInterface::class, $mock);
    }

    private function handle(ExecuteCampaignJob $job): void
    {
        $job->handle(app(MarketingCampaignInterface::class), app(SuppressionService::class));
    }

    private function inlineJob(array $prospectIds): ExecuteCampaignJob
    {
        return new ExecuteCampaignJob(prospectIds: $prospectIds, templateId: $this->template()->id);
    }

    public function test_prospect_with_two_addresses_gets_two_sends_and_two_messages_with_distinct_tokens(): void
    {
        $this->mockMailer(fn () => true);
        $p = $this->prospect(['a@example.com', 'b@example.com']);

        $this->handle($this->inlineJob([$p->id]));

        $this->assertCount(2, $this->calls);
        $this->assertSame(['a@example.com'], $this->calls[0]['emails']);
        $this->assertSame(['b@example.com'], $this->calls[1]['emails']);
        $this->assertNotSame($this->calls[0]['token'], $this->calls[1]['token']);
        $this->assertStringContainsString($this->calls[0]['token'], $this->calls[0]['html']);
        $this->assertStringNotContainsString($this->calls[1]['token'], $this->calls[0]['html']);

        $messages = CampaignMessage::where('prospect_id', $p->id)->get();
        $this->assertCount(2, $messages);
        $this->assertCount(2, $messages->pluck('tracking_token')->unique());
        $this->assertEqualsCanonicalizing(['a@example.com', 'b@example.com'], $messages->pluck('email')->all());

        $campaign = Campaign::first();
        $this->assertSame(2, $campaign->sent_count);
        $this->assertSame(0, $campaign->failed_count);
        $this->assertSame(CampaignStatus::COMPLETED, $campaign->status);
    }

    public function test_comma_separated_location_email_is_split_into_separate_sends(): void
    {
        $this->mockMailer(fn () => true);
        $p = $this->prospect(['a@example.com, b@example.com']);

        $this->handle($this->inlineJob([$p->id]));

        $this->assertCount(2, $this->calls);
        $this->assertSame(2, CampaignMessage::where('prospect_id', $p->id)->count());
    }

    public function test_exception_for_one_address_does_not_abort_the_others(): void
    {
        $this->mockMailer(function (array $emails) {
            if ($emails[0] === 'a@example.com') {
                throw new \Error('boom');
            }

            return true;
        });
        $p  = $this->prospect(['a@example.com', 'b@example.com']);
        $p2 = $this->prospect(['c@example.com']);

        $this->handle($this->inlineJob([$p->id, $p2->id]));

        $this->assertCount(3, $this->calls);
        $this->assertDatabaseHas('mailing_messages', ['email' => 'a@example.com', 'status' => 'failed', 'error_message' => 'boom']);
        $this->assertDatabaseHas('mailing_messages', ['email' => 'b@example.com', 'status' => 'sent']);
        $this->assertDatabaseHas('mailing_messages', ['email' => 'c@example.com', 'status' => 'sent']);

        $campaign = Campaign::first();
        $this->assertSame(2, $campaign->sent_count);
        $this->assertSame(1, $campaign->failed_count);
        $this->assertSame(CampaignStatus::COMPLETED, $campaign->status);
    }

    public function test_mailer_returning_false_records_failed_message_for_that_address_only(): void
    {
        $this->mockMailer(fn (array $emails) => $emails[0] !== 'a@example.com');
        $p = $this->prospect(['a@example.com', 'b@example.com']);

        $this->handle($this->inlineJob([$p->id]));

        $this->assertDatabaseHas('mailing_messages', ['email' => 'a@example.com', 'status' => 'failed']);
        $this->assertDatabaseHas('mailing_messages', ['email' => 'b@example.com', 'status' => 'sent']);
    }

    public function test_retrying_same_job_instance_reuses_campaign_and_does_not_resend(): void
    {
        $this->mockMailer(fn () => true);
        $p   = $this->prospect(['a@example.com', 'b@example.com']);
        $job = $this->inlineJob([$p->id]);

        $this->handle($job);
        $this->handle($job);

        $this->assertSame(1, Campaign::count());
        $this->assertCount(2, $this->calls);
        $this->assertSame(2, CampaignMessage::where('status', 'sent')->count());
        $this->assertSame(2, Campaign::first()->sent_count);
    }

    public function test_retry_after_partial_failure_does_not_resend_addresses_that_already_have_an_outcome(): void
    {
        $this->mockMailer(fn (array $emails) => $emails[0] !== 'b@example.com');
        $p   = $this->prospect(['a@example.com', 'b@example.com']);
        $job = $this->inlineJob([$p->id]);

        $this->handle($job);
        $this->calls = [];
        $this->handle($job);

        $this->assertSame(1, Campaign::count());
        $this->assertCount(0, $this->calls);
        $this->assertSame(2, CampaignMessage::count());
        $campaign = Campaign::first();
        $this->assertSame(1, $campaign->sent_count);
        $this->assertSame(1, $campaign->failed_count);
    }

    public function test_dispatch_key_is_generated_in_constructor_and_survives_serialization(): void
    {
        $job = new ExecuteCampaignJob(prospectIds: [1], templateId: 1);

        $this->assertNotEmpty($job->dispatchKey);
        $this->assertSame($job->dispatchKey, unserialize(serialize($job))->dispatchKey);
        $this->assertNotSame($job->dispatchKey, (new ExecuteCampaignJob(prospectIds: [1], templateId: 1))->dispatchKey);
    }

    public function test_failed_moves_inline_campaign_from_sending_to_failed(): void
    {
        $p   = $this->prospect(['a@example.com']);
        $job = $this->inlineJob([$p->id]);
        $campaign = Campaign::factory()->create([
            'status'       => CampaignStatus::SENDING,
            'dispatch_key' => $job->dispatchKey,
        ]);

        $job->failed(new \RuntimeException('fatal'));

        $campaign->refresh();
        $this->assertSame(CampaignStatus::FAILED, $campaign->status);
        $this->assertNotNull($campaign->finished_at);
    }

    public function test_suppressed_secondary_address_is_skipped_while_primary_is_sent(): void
    {
        $this->mockMailer(fn () => true);
        $p = $this->prospect(['a@example.com', 'b@example.com']);
        app(SuppressionService::class)->suppress('b@example.com', SuppressionReason::HARD_BOUNCE);

        $this->handle($this->inlineJob([$p->id]));

        $this->assertCount(1, $this->calls);
        $this->assertSame(['a@example.com'], $this->calls[0]['emails']);
        $this->assertDatabaseHas('mailing_messages', ['email' => 'a@example.com', 'status' => 'sent']);
        $this->assertDatabaseHas('mailing_messages', [
            'email' => 'b@example.com', 'status' => 'skipped', 'error_message' => 'suppressed: hard_bounce',
        ]);

        $campaign = Campaign::first();
        $this->assertSame(1, $campaign->sent_count);
        $this->assertSame(1, $campaign->skipped_count);
    }

    public function test_default_send_delay_is_at_least_two_seconds(): void
    {
        $config = require __DIR__ . '/../../config/config.php';

        $this->assertGreaterThanOrEqual(2000, $config['send_delay_ms']);
    }
}
