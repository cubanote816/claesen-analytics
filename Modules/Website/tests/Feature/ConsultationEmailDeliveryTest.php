<?php

declare(strict_types=1);

namespace Modules\Website\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Modules\Core\Models\Site;
use Modules\Core\Tests\Support\InteractsWithOrganizationFixtures;
use Modules\Mailing\Mail\Transport\MicrosoftGraphTransport;
use Modules\Mailing\Services\MicrosoftGraphService;
use Modules\Website\Jobs\SendConsultationEmailJob;
use Modules\Website\Mail\ConsultationConfirmationMail;
use Modules\Website\Mail\NewConsultationRequestMail;
use Modules\Website\Models\ConsultationEmailDelivery;
use Modules\Website\Models\ConsultationRequest;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Tests\TestCase;

/**
 * F4/CLA-473 of the multi-organization program — docs/ai/adr-multi-organization.md.
 *
 * Unit-level coverage of the pieces ConsultationEmailTest's HTTP-shaped
 * happy path doesn't exercise: per-site sender/recipient resolution, the
 * job's failure path (never rethrows, never leaks PII into last_error),
 * the retry command's attempt ceiling, and the MicrosoftGraphTransport
 * From-name bug this ticket fixed.
 */
final class ConsultationEmailDeliveryTest extends TestCase
{
    use InteractsWithOrganizationFixtures;
    use RefreshDatabase;

    public function test_site_mail_config_falls_back_to_global_config_when_unset(): void
    {
        config([
            'mail.from.address' => 'global@example.test',
            'mail.from.name' => 'Global Sender',
            'website.consultation_notification_email' => 'global-notify@example.test',
        ]);

        $site = Site::factory()->create([
            'mail_from_address' => null,
            'mail_from_name' => null,
            'mail_notification_email' => null,
        ]);

        $this->assertSame('global@example.test', $site->mailFromAddress());
        $this->assertSame('Global Sender', $site->mailFromName());
        $this->assertSame('global-notify@example.test', $site->notificationEmail());
    }

    public function test_site_mail_config_override_takes_priority_over_global_config(): void
    {
        config([
            'mail.from.address' => 'global@example.test',
            'mail.from.name' => 'Global Sender',
            'website.consultation_notification_email' => 'global-notify@example.test',
        ]);

        $site = Site::factory()->create([
            'mail_from_address' => 'bertels@example.test',
            'mail_from_name' => 'Electro Bertels',
            'mail_notification_email' => 'bertels-notify@example.test',
        ]);

        $this->assertSame('bertels@example.test', $site->mailFromAddress());
        $this->assertSame('Electro Bertels', $site->mailFromName());
        $this->assertSame('bertels-notify@example.test', $site->notificationEmail());
    }

    public function test_job_marks_delivery_sent_and_records_one_attempt(): void
    {
        Mail::fake();

        $consultation = ConsultationRequest::factory()->create();
        $delivery = ConsultationEmailDelivery::factory()->confirmation()->create([
            'site_id' => $consultation->site_id,
            'consultation_request_id' => $consultation->id,
            'recipient' => $consultation->email,
        ]);

        (new SendConsultationEmailJob($delivery->id))->handle();

        $delivery->refresh();
        $this->assertSame(ConsultationEmailDelivery::STATUS_SENT, $delivery->status);
        $this->assertSame(1, $delivery->attempts);
        $this->assertNotNull($delivery->sent_at);
        $this->assertNull($delivery->last_error);

        Mail::assertSent(ConsultationConfirmationMail::class);
    }

    public function test_job_never_rethrows_and_records_a_pii_free_error_on_failure(): void
    {
        // No Http::fake() at all — a real Mail::mailer('microsoft-graph')
        // send with unconfigured credentials throws MailConfigurationException
        // from MicrosoftGraphService::assertConfigured(), before any HTTP call.
        config([
            'mail.mailers.microsoft-graph.client_id' => null,
            'mail.mailers.microsoft-graph.tenant_id' => null,
            'mail.mailers.microsoft-graph.client_secret' => null,
        ]);

        $consultation = ConsultationRequest::factory()->create(['email' => 'someone-private@example.test']);
        $delivery = ConsultationEmailDelivery::factory()->confirmation()->create([
            'site_id' => $consultation->site_id,
            'consultation_request_id' => $consultation->id,
            'recipient' => $consultation->email,
        ]);

        // The assertion under test IS that this never throws.
        (new SendConsultationEmailJob($delivery->id))->handle();

        $delivery->refresh();
        $this->assertSame(ConsultationEmailDelivery::STATUS_FAILED, $delivery->status);
        $this->assertSame(1, $delivery->attempts);
        $this->assertNotNull($delivery->last_error);
        $this->assertStringNotContainsString('someone-private@example.test', $delivery->last_error);
        $this->assertStringNotContainsString($consultation->name, $delivery->last_error);
        $this->assertStringContainsString('MailConfigurationException', $delivery->last_error);
    }

    public function test_job_never_logs_the_recipient_or_name_on_failure(): void
    {
        config([
            'mail.mailers.microsoft-graph.client_id' => null,
            'mail.mailers.microsoft-graph.tenant_id' => null,
            'mail.mailers.microsoft-graph.client_secret' => null,
        ]);

        $consultation = ConsultationRequest::factory()->create([
            'name' => 'Very Unique Name Qz9',
            'email' => 'must-not-leak-Qz9@example.test',
        ]);
        $delivery = ConsultationEmailDelivery::factory()->create([
            'site_id' => $consultation->site_id,
            'consultation_request_id' => $consultation->id,
            'recipient' => $consultation->email,
        ]);

        Log::shouldReceive('error')
            ->once()
            ->withArgs(function (string $message, array $context) {
                $haystack = $message.json_encode($context);

                return ! str_contains($haystack, 'must-not-leak-Qz9@example.test')
                    && ! str_contains($haystack, 'Very Unique Name Qz9');
            });

        (new SendConsultationEmailJob($delivery->id))->handle();
    }

    public function test_job_is_a_no_op_when_the_delivery_is_already_sent(): void
    {
        Mail::fake();

        $consultation = ConsultationRequest::factory()->create();
        $delivery = ConsultationEmailDelivery::factory()->sent()->create([
            'site_id' => $consultation->site_id,
            'consultation_request_id' => $consultation->id,
        ]);

        (new SendConsultationEmailJob($delivery->id))->handle();

        $delivery->refresh();
        $this->assertSame(1, $delivery->attempts, 'A delivery already marked sent must not be re-attempted.');
        Mail::assertNothingSent();
    }

    public function test_retry_command_redispatches_only_failed_deliveries_under_the_ceiling(): void
    {
        Queue::fake();

        $consultation = ConsultationRequest::factory()->create();

        $eligible = ConsultationEmailDelivery::factory()->failed(1)->create([
            'site_id' => $consultation->site_id,
            'consultation_request_id' => $consultation->id,
        ]);
        $exhausted = ConsultationEmailDelivery::factory()->failed(3)->create([
            'site_id' => $consultation->site_id,
            'consultation_request_id' => $consultation->id,
        ]);
        $sent = ConsultationEmailDelivery::factory()->sent()->create([
            'site_id' => $consultation->site_id,
            'consultation_request_id' => $consultation->id,
        ]);

        $this->artisan('website:retry-failed-consultation-emails', ['--max-attempts' => 3])
            ->assertSuccessful();

        Queue::assertPushed(
            SendConsultationEmailJob::class,
            fn (SendConsultationEmailJob $job) => $job->deliveryId === $eligible->id
        );
        Queue::assertNotPushed(
            SendConsultationEmailJob::class,
            fn (SendConsultationEmailJob $job) => $job->deliveryId === $exhausted->id
        );
        Queue::assertNotPushed(
            SendConsultationEmailJob::class,
            fn (SendConsultationEmailJob $job) => $job->deliveryId === $sent->id
        );
    }

    public function test_graph_transport_respects_the_mailables_own_from_name_not_only_the_global_config(): void
    {
        config(['mail.from.name' => 'Global Sender']);

        $email = (new Email)
            ->from(new Address('bertels@example.test', 'Electro Bertels'))
            ->to('someone@example.test')
            ->subject('Test')
            ->html('<p>Body</p>');

        $transport = new MicrosoftGraphTransport($this->createMock(MicrosoftGraphService::class));
        $method = new \ReflectionMethod($transport, 'getPayload');
        $method->setAccessible(true);
        $payload = $method->invoke($transport, $email);

        $this->assertSame('Electro Bertels', $payload['message']['from']['emailAddress']['name']);
        $this->assertSame('Electro Bertels', $payload['message']['sender']['emailAddress']['name']);
    }

    public function test_a_bertels_fixture_sites_own_sender_identity_is_used_end_to_end(): void
    {
        Mail::fake();

        $bertelsSite = $this->bertelsFixtureSite(null, [
            'mail_from_address' => 'no-reply@electrobertels.test',
            'mail_from_name' => 'Electro Bertels',
        ]);
        $consultation = ConsultationRequest::factory()->create(['site_id' => $bertelsSite->id]);
        $delivery = ConsultationEmailDelivery::factory()->create([
            'site_id' => $bertelsSite->id,
            'consultation_request_id' => $consultation->id,
            'recipient' => 'internal@electrobertels.test',
        ]);

        (new SendConsultationEmailJob($delivery->id))->handle();

        Mail::assertSent(
            NewConsultationRequestMail::class,
            function ($mail) {
                $from = $mail->envelope()->from;

                return $from->address === 'no-reply@electrobertels.test'
                    && $from->name === 'Electro Bertels';
            }
        );
    }

    public function test_graph_transport_falls_back_to_global_config_name_when_the_mailable_sets_none(): void
    {
        config(['mail.from.name' => 'Global Sender']);

        $email = (new Email)
            ->from('bertels@example.test') // no display name
            ->to('someone@example.test')
            ->subject('Test')
            ->html('<p>Body</p>');

        $transport = new MicrosoftGraphTransport($this->createMock(MicrosoftGraphService::class));
        $method = new \ReflectionMethod($transport, 'getPayload');
        $method->setAccessible(true);
        $payload = $method->invoke($transport, $email);

        $this->assertSame('Global Sender', $payload['message']['from']['emailAddress']['name']);
    }
}
