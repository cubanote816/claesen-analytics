<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Feature;

use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Models\AccessEvent;
use Modules\Core\Models\AuthAttempt;
use Modules\Core\Models\AuthSecurityAlert;
use Modules\Core\Models\User;
use Modules\Core\Services\AccessAnalyticsService;
use Modules\Core\Notifications\SecurityAlertNotification;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AccessAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'technician', 'guard_name' => 'web']);
    }

    private function activeUser(array $overrides = []): User
    {
        return UserFactory::new()->create(array_merge([
            'password' => 'Secret1234!',
            'password_set_at' => now()->subDay(),
            'is_active' => true,
        ], $overrides));
    }

    public function test_token_login_uses_device_name_fallback_and_records_event(): void
    {
        $user = $this->activeUser();

        $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'Secret1234!',
            'device_name' => 'Claesen-Safety',
        ])
            ->assertOk()
            ->assertJsonStructure([
                'success',
                'accessToken',
                'tokenType',
                'expiresAt',
                'user' => ['id', 'name', 'email', 'roles'],
                'message',
            ]);

        $this->assertDatabaseHas('core_access_events', [
            'user_id' => $user->id,
            'event_type' => AccessEvent::EVENT_LOGIN,
            'app_source' => 'Claesen-Safety',
            'auth_channel' => 'sanctum_token',
        ]);

        $fresh = $user->fresh();
        $this->assertNotNull($fresh->last_login_at);
        $this->assertSame('Claesen-Safety', $fresh->last_login_app_source);
        $this->assertSame('sanctum_token', $fresh->last_login_channel);
    }

    public function test_session_login_records_explicit_app_source_and_updates_user_snapshot(): void
    {
        $user = $this->activeUser();
        $user->assignRole('technician');

        $this->postJson('/api/v1/auth/login/sport', [
            'email' => $user->email,
            'password' => 'Secret1234!',
            'app_source' => 'Claesen-Sport-updateing',
        ])
            ->assertOk()
            ->assertJsonStructure([
                'user' => ['id', 'name', 'email', 'roles'],
            ]);

        $this->assertDatabaseHas('core_access_events', [
            'user_id' => $user->id,
            'event_type' => AccessEvent::EVENT_LOGIN,
            'app_source' => 'Claesen-Sport-updateing',
            'auth_channel' => 'session_cookie',
        ]);

        $fresh = $user->fresh();
        $this->assertNotNull($fresh->last_login_at);
        $this->assertSame('Claesen-Sport-updateing', $fresh->last_login_app_source);
        $this->assertSame('session_cookie', $fresh->last_login_channel);
    }

    public function test_failed_login_attempts_are_recorded_and_rate_limited(): void
    {
        $user = $this->activeUser();

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->postJson('/api/v1/auth/login', [
                'email' => $user->email,
                'password' => 'WrongPassword!',
                'device_name' => 'Claesen-Safety',
            ])
                ->assertStatus(422)
                ->assertJsonValidationErrors('email');
        }

        $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'WrongPassword!',
            'device_name' => 'Claesen-Safety',
        ])
            ->assertStatus(429)
            ->assertJsonValidationErrors('email');

        $this->assertDatabaseHas('core_auth_attempts', [
            'user_id' => $user->id,
            'event_type' => AuthAttempt::EVENT_FAILED,
            'app_source' => 'Claesen-Safety',
            'auth_channel' => 'sanctum_token',
            'failure_reason' => 'invalid_password',
        ]);

        $this->assertDatabaseHas('core_auth_attempts', [
            'event_type' => AuthAttempt::EVENT_THROTTLED,
            'app_source' => 'Claesen-Safety',
            'auth_channel' => 'sanctum_token',
            'failure_reason' => 'rate_limited',
        ]);

        $this->assertDatabaseMissing('core_access_events', [
            'user_id' => $user->id,
            'event_type' => AccessEvent::EVENT_LOGIN,
            'app_source' => 'Claesen-Safety',
        ]);
    }

    public function test_dashboard_data_groups_login_events_and_activity_windows(): void
    {
        $service = app(AccessAnalyticsService::class);

        $safeUser = $this->activeUser(['email' => 'safety@example.test']);
        $sportUser = $this->activeUser(['email' => 'sport@example.test']);
        $inactiveUser = $this->activeUser(['email' => 'inactive@example.test']);

        $service->recordLogin($safeUser, 'Claesen-Safety', 'session_cookie');
        $service->recordLogin($sportUser, 'Claesen-Sport-updateing', 'sanctum_token');

        $data = $service->dashboardData(30, 10, 10);

        $this->assertSame(3, $data['eligible_users']);
        $this->assertSame(2, $data['active_users']);
        $this->assertSame(1, $data['inactive_users']);
        $this->assertSame(2, $data['apps_seen']);
        $this->assertCount(2, $data['app_summary']);
        $this->assertGreaterThanOrEqual(2, $data['recent_events']->count());
        $this->assertGreaterThanOrEqual(3, $data['user_snapshot']->count());
        $this->assertDatabaseHas('core_access_events', [
            'user_id' => $safeUser->id,
            'app_source' => 'Claesen-Safety',
        ]);
        $this->assertDatabaseHas('core_access_events', [
            'user_id' => $sportUser->id,
            'app_source' => 'Claesen-Sport-updateing',
        ]);
        $this->assertNull($inactiveUser->fresh()->last_login_at);
    }

    public function test_dashboard_data_groups_active_usage_by_last_known_app_origin(): void
    {
        $service = app(AccessAnalyticsService::class);

        $safetyUserA = $this->activeUser(['email' => 'activity-safety-a@example.test']);
        $safetyUserB = $this->activeUser(['email' => 'activity-safety-b@example.test']);
        $sportUser = $this->activeUser(['email' => 'activity-sport@example.test']);
        $staleUser = $this->activeUser(['email' => 'activity-stale@example.test']);

        $safetyUserA->forceFill([
            'last_login_app_source' => 'Claesen-Safety',
            'last_active_at' => now()->subHour(),
        ])->saveQuietly();

        $safetyUserB->forceFill([
            'last_login_app_source' => 'Claesen-Safety',
            'last_active_at' => now()->subMinutes(20),
        ])->saveQuietly();

        $sportUser->forceFill([
            'last_login_app_source' => 'Claesen-Sport-updateing',
            'last_active_at' => now()->subMinutes(45),
        ])->saveQuietly();

        $staleUser->forceFill([
            'last_login_app_source' => 'Claesen-Website',
            'last_active_at' => now()->subDays(40),
        ])->saveQuietly();

        $data = $service->dashboardData(30, 10, 10);

        $this->assertCount(2, $data['activity_summary']);

        $safetySummary = $data['activity_summary']->firstWhere('app_source', 'Claesen-Safety');
        $sportSummary = $data['activity_summary']->firstWhere('app_source', 'Claesen-Sport-updateing');

        $this->assertNotNull($safetySummary);
        $this->assertSame(2, (int) $safetySummary->active_users);
        $this->assertNotNull($safetySummary->last_active_at);

        $this->assertNotNull($sportSummary);
        $this->assertSame(1, (int) $sportSummary->active_users);
        $this->assertNotNull($sportSummary->last_active_at);

        $this->assertNull($data['activity_summary']->firstWhere('app_source', 'Claesen-Website'));
    }

    public function test_dashboard_data_includes_security_summary_and_failed_login_counts(): void
    {
        $service = app(AccessAnalyticsService::class);

        $user = $this->activeUser(['email' => 'watchlist@example.test']);

        $service->recordFailedLogin($user, $user->email, 'Claesen-Safety', 'session_cookie', null, 'invalid_password');
        $service->recordFailedLogin($user, $user->email, 'Claesen-Safety', 'session_cookie', null, 'invalid_password');
        $service->recordThrottledLogin(null, $user->email, 'Claesen-Safety', 'session_cookie', null, 'rate_limited');

        $data = $service->dashboardData(30, 10, 10);

        $this->assertSame(2, $data['failed_attempts']);
        $this->assertSame(1, $data['throttled_attempts']);
        $this->assertGreaterThanOrEqual(1, $data['security_alerts']->count());
        $this->assertGreaterThanOrEqual(1, $data['recent_security_events']->count());

        $snapshotUser = $data['user_snapshot']->firstWhere('id', $user->id);

        $this->assertNotNull($snapshotUser);
        $this->assertSame(2, $snapshotUser->failed_login_count);
        $this->assertNotNull($snapshotUser->last_failed_login_at);
    }

    public function test_security_alert_notification_is_sent_once_per_window(): void
    {
        Notification::fake();

        $service = app(AccessAnalyticsService::class);
        $superAdmin = $this->activeUser(['email' => 'admin-alerts@example.test']);
        $superAdmin->assignRole('super_admin');

        $subject = $this->activeUser(['email' => 'target-alerts@example.test']);

        for ($i = 0; $i < 5; $i++) {
            $service->recordFailedLogin(
                $subject,
                $subject->email,
                'Claesen-Safety',
                'session_cookie',
                null,
                'invalid_password'
            );
        }

        $this->assertDatabaseCount('core_auth_security_alerts', 1);

        Notification::assertSentTo($superAdmin, SecurityAlertNotification::class);

        $service->recordFailedLogin(
            $subject,
            $subject->email,
            'Claesen-Safety',
            'session_cookie',
            null,
            'invalid_password'
        );

        $this->assertDatabaseCount('core_auth_security_alerts', 1);
    }

    public function test_super_admin_can_open_access_analytics_page(): void
    {
        $user = $this->activeUser();
        $user->assignRole('super_admin');

        $this->actingAs($user)
            ->get('/access-analytics')
            ->assertOk()
            ->assertSeeText(__('core::access_analytics.hero_title'));
    }
}
