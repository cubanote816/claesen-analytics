<?php

declare(strict_types=1);

namespace Modules\Website\Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Modules\Website\Mail\ConsultationConfirmationMail;
use Modules\Website\Mail\NewConsultationRequestMail;
use Modules\Website\Models\ConsultationEmailDelivery;
use Modules\Website\Services\ConsultationService;
use Tests\TestCase;

/**
 * Tests for the DB::afterCommit email dispatch in ConsultationService::createRequest().
 *
 * DatabaseTruncation (not RefreshDatabase): RefreshDatabase wraps each test in a
 * transaction so DB::afterCommit callbacks never fire during the test, making the
 * happy-path assert always fail. DatabaseMigrations would work too but its
 * teardown runs `migrate:rollback` on the whole batch, which trips over broken
 * down() methods in unrelated migrations and half-tears-down the schema for every
 * later Website test class. Truncation migrates forward once and never rolls back.
 *
 * F4/CLA-473: createRequest() no longer calls Mail::send() itself — it creates
 * a ConsultationEmailDelivery row per e-mail and dispatches
 * SendConsultationEmailJob::afterCommit(). Under phpunit.xml's
 * QUEUE_CONNECTION=sync, that job still runs inline right after the commit,
 * so Mail::fake() still intercepts the real send — these tests keep working
 * unchanged in shape, just against two Mailables instead of one.
 */
class ConsultationEmailTest extends TestCase
{
    use DatabaseTruncation;

    // =========================================================================
    // Happy path — both e-mails fire after the transaction commits
    // =========================================================================

    public function test_consultation_creation_sends_both_emails_after_commit(): void
    {
        Mail::fake();

        // CLA-532: the notification recipient now comes from config (was a
        // hardcoded address). Set it for the happy path; the null-recipient
        // "persist but skip the send" behaviour is covered by
        // ConsultationEndpoint201Test.
        config(['website.consultation_notification_email' => 'qa@example.test']);

        $service = app(ConsultationService::class);

        $consultation = $service->createRequest([
            'name' => 'Jan Claesen',
            'email' => 'jan@example.com',
            'message' => 'Interested in stadium lighting.',
        ]);

        Mail::assertSent(
            NewConsultationRequestMail::class,
            fn ($mail) => $mail->consultation->email === 'jan@example.com'
                && $mail->hasTo('qa@example.test')
        );

        // F4/CLA-473: the client-facing confirmation — before this ticket,
        // no such e-mail was ever sent at all.
        Mail::assertSent(
            ConsultationConfirmationMail::class,
            fn ($mail) => $mail->consultation->email === 'jan@example.com'
                && $mail->hasTo('jan@example.com')
        );

        $deliveries = ConsultationEmailDelivery::query()
            ->where('consultation_request_id', $consultation->id)
            ->get();

        $this->assertCount(2, $deliveries);
        $this->assertTrue($deliveries->every(
            fn (ConsultationEmailDelivery $d) => $d->status === ConsultationEmailDelivery::STATUS_SENT
                && $d->attempts === 1
                && $d->sent_at !== null
        ));
    }

    // =========================================================================
    // Rollback guard — afterCommit must NOT fire when the transaction aborts
    // =========================================================================

    public function test_email_not_sent_when_transaction_rolls_back(): void
    {
        Mail::fake();
        $afterCommitFired = false;

        try {
            DB::transaction(function () use (&$afterCommitFired): void {
                DB::afterCommit(function () use (&$afterCommitFired): void {
                    $afterCommitFired = true;
                });

                throw new \RuntimeException('Simulated failure — forces transaction rollback');
            });
        } catch (\RuntimeException) {
            // Expected — the transaction was rolled back
        }

        $this->assertFalse(
            $afterCommitFired,
            'DB::afterCommit must not fire when the transaction rolls back'
        );

        Mail::assertNothingSent();
    }

    // =========================================================================
    // Consulting request row is persisted after a successful creation
    // =========================================================================

    public function test_consultation_request_is_stored_in_database(): void
    {
        Mail::fake();

        $service = app(ConsultationService::class);

        $service->createRequest([
            'name' => 'Marie Dupont',
            'email' => 'marie@example.com',
            'message' => 'Need outdoor lighting for industrial park.',
            'type' => 'quote',
            'project_type' => 'industrial',
            'source' => 'website',
        ]);

        $this->assertDatabaseHas('website_consultation_requests', [
            'email' => 'marie@example.com',
            'type' => 'quote',
            'project_type' => 'industrial',
            'status' => 'pending',
        ]);
    }
}
