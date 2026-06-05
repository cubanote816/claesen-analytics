<?php

declare(strict_types=1);

namespace Modules\Website\Tests\Feature;

use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Website\Models\ConsultationActivity;
use Modules\Website\Models\ConsultationReminder;
use Modules\Website\Services\ConsultationService;
use Tests\TestCase;

/**
 * Tests for website:process-reminders (ProcessConsultationRemindersCommand).
 * Covers WEB-011 — reminder state transitions and activity logging.
 */
class ConsultationReminderCommandTest extends TestCase
{
    use RefreshDatabase;

    // =========================================================================
    // Due pending reminder → completed + activity logged
    // =========================================================================

    public function test_due_reminder_is_completed_and_activity_logged(): void
    {
        $user     = UserFactory::new()->create();
        $reminder = ConsultationReminder::factory()
            ->due()
            ->create(['user_id' => $user->id]);

        $this->artisan('website:process-reminders')->assertSuccessful();

        $fresh = $reminder->fresh();
        $this->assertSame('completed', $fresh->status);
        $this->assertNotNull($fresh->completed_at);

        // logActivity was called inside the command
        $this->assertDatabaseHas('website_consultation_activities', [
            'consultation_request_id' => $reminder->consultation_request_id,
            'type'                    => 'reminder_triggered',
        ]);
    }

    // =========================================================================
    // Future reminder → skipped (status stays pending)
    // =========================================================================

    public function test_future_reminder_is_not_processed(): void
    {
        $reminder = ConsultationReminder::factory()->create([
            'remind_at' => now()->addHour(),
            'status'    => 'pending',
        ]);

        $this->artisan('website:process-reminders')->assertSuccessful();

        $this->assertSame('pending', $reminder->fresh()->status);
    }

    // =========================================================================
    // Already-processing reminder → skipped by atomic claim
    // =========================================================================

    public function test_already_processing_reminder_is_skipped(): void
    {
        // A reminder that is past-due but already claimed by another process
        $reminder = ConsultationReminder::factory()
            ->due()
            ->processing()
            ->create();

        $this->artisan('website:process-reminders')->assertSuccessful();

        // The command only picks up 'pending'; 'processing' is left untouched
        $this->assertSame('processing', $reminder->fresh()->status);
        $this->assertNull($reminder->fresh()->completed_at);
    }

    // =========================================================================
    // Failure during processing → reverts to pending
    // =========================================================================

    public function test_processing_failure_reverts_reminder_to_pending(): void
    {
        $reminder = ConsultationReminder::factory()
            ->due()
            ->create();

        // Force logActivity to throw so the catch block runs
        $this->mock(ConsultationService::class, function ($mock): void {
            $mock->shouldReceive('logActivity')
                ->once()
                ->andThrow(new \RuntimeException('Forced failure'));
        });

        $this->artisan('website:process-reminders')->assertSuccessful();

        // Rollback clause: status should revert to 'pending'
        $this->assertSame('pending', $reminder->fresh()->status);
        $this->assertNull($reminder->fresh()->completed_at);
    }

    // =========================================================================
    // Reminder without assigned user → no notification, still completes
    // =========================================================================

    public function test_reminder_without_user_completes_without_notification(): void
    {
        $reminder = ConsultationReminder::factory()
            ->due()
            ->create(['user_id' => null]);

        $this->artisan('website:process-reminders')->assertSuccessful();

        $this->assertSame('completed', $reminder->fresh()->status);
    }

    // =========================================================================
    // Multiple reminders — each processed independently
    // =========================================================================

    public function test_multiple_due_reminders_are_all_completed(): void
    {
        ConsultationReminder::factory()->count(3)->due()->create();

        $this->artisan('website:process-reminders')->assertSuccessful();

        $this->assertSame(
            0,
            ConsultationReminder::where('status', 'pending')->count()
        );
        $this->assertSame(
            3,
            ConsultationReminder::where('status', 'completed')->count()
        );
    }
}
