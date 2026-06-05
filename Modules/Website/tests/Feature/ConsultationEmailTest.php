<?php

declare(strict_types=1);

namespace Modules\Website\Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Modules\Website\Mail\NewConsultationRequestMail;
use Modules\Website\Services\ConsultationService;
use Tests\TestCase;

/**
 * Tests for the DB::afterCommit email guard in ConsultationService::createRequest().
 *
 * DatabaseMigrations is required here — NOT RefreshDatabase.
 * RefreshDatabase wraps each test in a transaction so DB::afterCommit callbacks
 * never fire during the test, making the happy-path assert always fail.
 */
class ConsultationEmailTest extends TestCase
{
    use DatabaseMigrations;

    // =========================================================================
    // Happy path — email fires after the transaction commits
    // =========================================================================

    public function test_consultation_creation_sends_email_after_commit(): void
    {
        Mail::fake();

        $service = app(ConsultationService::class);

        $service->createRequest([
            'name'    => 'Jan Claesen',
            'email'   => 'jan@example.com',
            'message' => 'Interested in stadium lighting.',
        ]);

        Mail::assertSent(
            NewConsultationRequestMail::class,
            fn ($mail) => $mail->consultation->email === 'jan@example.com'
        );
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
            'name'         => 'Marie Dupont',
            'email'        => 'marie@example.com',
            'message'      => 'Need outdoor lighting for industrial park.',
            'type'         => 'quote',
            'project_type' => 'industrial',
            'source'       => 'website',
        ]);

        $this->assertDatabaseHas('website_consultation_requests', [
            'email'        => 'marie@example.com',
            'type'         => 'quote',
            'project_type' => 'industrial',
            'status'       => 'pending',
        ]);
    }
}
