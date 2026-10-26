<?php

declare(strict_types=1);

namespace Modules\Website\Tests\Feature;

use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Modules\Core\Models\Site;
use Modules\Core\Models\User;
use Modules\Core\Tests\Support\InteractsWithOrganizationFixtures;
use Modules\Website\Models\WebsiteAbuseAlert;
use Modules\Website\Models\WebsiteIntakeSpamAttempt;
use Modules\Website\Notifications\WebsiteAbuseAlertNotification;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * F4/CLA-475 of the multi-organization program — docs/ai/adr-multi-organization.md.
 */
final class CheckIntakeAbuseAlertsCommandTest extends TestCase
{
    use InteractsWithOrganizationFixtures;
    use RefreshDatabase;

    private function seedAttempts(int $siteId, int $count, string $reason = WebsiteIntakeSpamAttempt::REASON_HONEYPOT, ?Carbon $createdAt = null): void
    {
        for ($i = 0; $i < $count; $i++) {
            $attempt = WebsiteIntakeSpamAttempt::create([
                'site_id' => $siteId,
                'ip' => '203.0.113.'.($i % 255),
                'reason' => $reason,
            ]);

            if ($createdAt !== null) {
                $attempt->forceFill(['created_at' => $createdAt])->saveQuietly();
            }
        }
    }

    private function adminUser(): User
    {
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

        $user = $this->claesenUser();
        $user->assignRole('admin');

        return $user;
    }

    public function test_it_creates_no_alert_when_under_threshold(): void
    {
        config(['website.intake_hardening.abuse_alert.threshold' => 20]);
        $this->adminUser();
        $this->seedAttempts(Site::claesenId(), 19);

        $this->artisan('website:check-intake-abuse-alerts')->assertExitCode(0);

        $this->assertSame(0, WebsiteAbuseAlert::query()->count());
    }

    public function test_it_creates_and_notifies_when_threshold_is_reached(): void
    {
        Notification::fake();
        config(['website.intake_hardening.abuse_alert.threshold' => 20, 'website.intake_hardening.abuse_alert.window_minutes' => 60]);
        $admin = $this->adminUser();
        $this->seedAttempts(Site::claesenId(), 25);

        $this->artisan('website:check-intake-abuse-alerts')->assertExitCode(0);

        $alert = WebsiteAbuseAlert::query()->firstOrFail();
        $this->assertSame(Site::claesenId(), $alert->site_id);
        $this->assertSame(25, $alert->attempt_count);
        $this->assertSame(20, $alert->threshold);
        $this->assertNotNull($alert->notified_at);

        Notification::assertSentTo($admin, WebsiteAbuseAlertNotification::class);
    }

    public function test_it_is_idempotent_within_the_same_hour(): void
    {
        Notification::fake();
        config(['website.intake_hardening.abuse_alert.threshold' => 20]);
        $admin = $this->adminUser();
        $this->seedAttempts(Site::claesenId(), 25);

        $this->artisan('website:check-intake-abuse-alerts')->assertExitCode(0);
        $this->artisan('website:check-intake-abuse-alerts')->assertExitCode(0);

        $this->assertSame(1, WebsiteAbuseAlert::query()->count());
        Notification::assertSentToTimes($admin, WebsiteAbuseAlertNotification::class, 1);
    }

    public function test_attempts_outside_the_window_are_ignored(): void
    {
        config(['website.intake_hardening.abuse_alert.threshold' => 5, 'website.intake_hardening.abuse_alert.window_minutes' => 60]);
        $this->adminUser();
        $this->seedAttempts(Site::claesenId(), 10, createdAt: now()->subMinutes(120));

        $this->artisan('website:check-intake-abuse-alerts')->assertExitCode(0);

        $this->assertSame(0, WebsiteAbuseAlert::query()->count());
    }

    public function test_dry_run_creates_nothing(): void
    {
        Notification::fake();
        config(['website.intake_hardening.abuse_alert.threshold' => 5]);
        $admin = $this->adminUser();
        $this->seedAttempts(Site::claesenId(), 10);

        $this->artisan('website:check-intake-abuse-alerts', ['--dry-run' => true])->assertExitCode(0);

        $this->assertSame(0, WebsiteAbuseAlert::query()->count());
        Notification::assertNotSentTo($admin, WebsiteAbuseAlertNotification::class);
    }

    public function test_a_bertels_fixture_sites_alert_only_notifies_that_organizations_admins_when_enforcement_is_on(): void
    {
        Notification::fake();
        $this->enableOrganizationEnforcement();
        config(['website.intake_hardening.abuse_alert.threshold' => 5]);

        $claesenAdmin = $this->adminUser();

        $bertelsOrganization = $this->bertelsFixtureOrganization();
        $bertelsSite = $this->bertelsFixtureSite($bertelsOrganization);
        $bertelsAdmin = User::factory()->create(['organization_id' => $bertelsOrganization->id]);
        $bertelsAdmin->assignRole(Role::findOrCreate('admin', 'web'));

        $this->seedAttempts($bertelsSite->id, 10);

        $this->artisan('website:check-intake-abuse-alerts')->assertExitCode(0);

        Notification::assertSentTo($bertelsAdmin, WebsiteAbuseAlertNotification::class);
        Notification::assertNotSentTo($claesenAdmin, WebsiteAbuseAlertNotification::class);
    }
}
