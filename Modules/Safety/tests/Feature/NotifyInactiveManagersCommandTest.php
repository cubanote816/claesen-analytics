<?php

declare(strict_types=1);

namespace Modules\Safety\Tests\Feature;

use Carbon\Carbon;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Mail;
use Modules\Safety\Database\Factories\InspectionFactory;
use Modules\Safety\Emails\InspectionReminderMail;
use Illuminate\Support\Facades\DB;
use Modules\Safety\Models\Inspection;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class NotifyInactiveManagersCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['super_admin', 'admin', 'project_manager', 'viewer'] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }

        // All tests require a configured PWA URL to pass the early-guard check.
        config(['safety.pwa_url' => 'https://safety.example.com/']);
    }

    private function manager(string $email = null, Carbon $createdAt = null)
    {
        $user = UserFactory::new()->create([
            'email'      => $email ?? fake()->unique()->safeEmail(),
            'created_at' => $createdAt ?? now()->subDays(30),
        ]);
        $user->assignRole('project_manager');
        return $user;
    }

    private function inspection($user, Carbon $completedAt, bool $softDelete = false): Inspection
    {
        $inspection = InspectionFactory::new()->create([
            'user_id'      => $user->id,
            'completed_at' => $completedAt,
        ]);

        if ($softDelete) {
            $inspection->delete();
        }

        return $inspection;
    }

    // ── Case 1: inactive manager (45 days, active inspection) receives email ───

    public function test_manager_inactive_45_days_receives_email(): void
    {
        Mail::fake();

        $manager = $this->manager();
        $this->inspection($manager, now()->subDays(45));

        $this->artisan('safety:notify-inactive-managers')
            ->assertExitCode(0);

        Mail::assertSent(InspectionReminderMail::class, function (InspectionReminderMail $mail) use ($manager): bool {
            return $mail->hasTo($manager->email)
                && $mail->recipient->is($manager)
                && $mail->daysSinceLastInspection === 45;
        });
    }

    // ── Case 2: archived (soft-deleted) inspection still counts ──────────────

    public function test_soft_deleted_inspection_counts_as_last_inspection(): void
    {
        Mail::fake();

        $manager = $this->manager();
        // Inspection was performed but later archived by an admin.
        $this->inspection($manager, now()->subDays(45), softDelete: true);

        $this->artisan('safety:notify-inactive-managers')
            ->assertExitCode(0);

        // withTrashed() must be used — the archived inspection counts.
        Mail::assertSent(InspectionReminderMail::class, fn ($mail) => $mail->hasTo($manager->email));
    }

    // ── Case 3: manager inspected 29 days ago — compliant, no email ──────────

    public function test_manager_inactive_29_days_does_not_receive_email(): void
    {
        Mail::fake();

        $manager = $this->manager();
        $this->inspection($manager, now()->subDays(29));

        $this->artisan('safety:notify-inactive-managers')
            ->assertExitCode(0);

        Mail::assertNotSent(InspectionReminderMail::class);
    }

    // ── Case 4: exactly 30 days — boundary inclusive (>= 30), receives email ─

    public function test_manager_inactive_exactly_30_days_receives_email(): void
    {
        Mail::fake();

        $manager = $this->manager();
        // Exactly at the threshold: compliance_days = 30.
        // Rule is >= 30 (inclusive): this manager should receive a reminder.
        $this->inspection($manager, Carbon::now()->subDays(30));

        $this->artisan('safety:notify-inactive-managers')
            ->assertExitCode(0);

        Mail::assertSent(InspectionReminderMail::class, fn ($mail) => $mail->hasTo($manager->email));
    }

    // ── Case 5: 31 days — clearly over threshold ──────────────────────────────

    public function test_manager_inactive_31_days_receives_email(): void
    {
        Mail::fake();

        $manager = $this->manager();
        $this->inspection($manager, now()->subDays(31));

        $this->artisan('safety:notify-inactive-managers')
            ->assertExitCode(0);

        Mail::assertSent(InspectionReminderMail::class, fn ($mail) => $mail->hasTo($manager->email));
    }

    // ── Case 6: no history, account > grace period → receives email ───────────

    public function test_manager_with_no_inspection_history_receives_email(): void
    {
        Mail::fake();

        // Account created 8 days ago — past the 7-day grace period.
        $manager = $this->manager(createdAt: now()->subDays(8));

        $this->artisan('safety:notify-inactive-managers')
            ->assertExitCode(0);

        Mail::assertSent(InspectionReminderMail::class, function (InspectionReminderMail $mail) use ($manager): bool {
            // days_since_last must be null (no history), not an integer.
            return $mail->hasTo($manager->email)
                && $mail->daysSinceLastInspection === null;
        });
    }

    // ── Case 7: no history, account < grace period → no email ────────────────

    public function test_new_manager_within_grace_period_does_not_receive_email(): void
    {
        Mail::fake();

        // Account created 6 days ago — still within the 7-day grace period.
        $manager = $this->manager(createdAt: now()->subDays(6));

        $this->artisan('safety:notify-inactive-managers')
            ->assertExitCode(0);

        Mail::assertNotSent(InspectionReminderMail::class);
    }

    // ── Case 8: dry-run — no email sent, candidate email appears in output ──────

    public function test_dry_run_does_not_send_email_but_logs_candidate(): void
    {
        Mail::fake();

        // Use a fixed email so the assertion is deterministic and readable.
        $manager = $this->manager(email: 'pm.dryrun@test.example');
        $this->inspection($manager, now()->subDays(45));

        // Use Artisan::call() + Artisan::output() to capture the full command
        // output reliably, bypassing PendingCommand's buffering quirks.
        $exitCode = Artisan::call('safety:notify-inactive-managers', ['--dry-run' => true]);
        $output   = Artisan::output();

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('[DRY-RUN]', $output);
        $this->assertStringContainsString('pm.dryrun@test.example', $output);
        Mail::assertNothingSent();
    }

    // ── Case 9: manager without email address — skipped, no crash ────────────

    public function test_manager_without_email_is_skipped_gracefully(): void
    {
        Mail::fake();

        // Create a valid user then blank out the email via direct DB update to
        // bypass the NOT NULL constraint while still exercising the skip logic.
        $manager = UserFactory::new()->create(['created_at' => now()->subDays(30)]);
        $manager->assignRole('project_manager');
        DB::table('users')
            ->where('id', $manager->id)
            ->update(['email' => '']);
        $manager->email = '';
        $this->inspection($manager, now()->subDays(45));

        $this->artisan('safety:notify-inactive-managers')
            ->expectsOutputToContain('[SKIP]')
            ->assertExitCode(0);

        Mail::assertNothingSent();
    }
}
