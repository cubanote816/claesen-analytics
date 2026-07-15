<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Feature;

use Mockery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Database\Factories\UserFactory;
use Modules\Core\Services\AccessAnalyticsService;
use Tests\TestCase;

class AccessAnalyticsFallbackTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_and_login_fallback_when_event_table_is_missing(): void
    {
        $service = Mockery::mock(AccessAnalyticsService::class)->makePartial()->shouldAllowMockingProtectedMethods();
        $service->shouldReceive('eventsTableExists')->andReturnFalse();
        $service->shouldReceive('authAttemptsTableExists')->andReturnFalse();
        $service->shouldReceive('userColumnExists')->andReturnUsing(function (string $column): bool {
            return $column === 'last_active_at';
        });

        $this->app->instance(AccessAnalyticsService::class, $service);

        $user = UserFactory::new()->create([
            'password' => 'Secret1234!',
            'password_set_at' => now()->subDay(),
            'is_active' => true,
        ]);

        $data = $service->dashboardData();

        $this->assertFalse($data['analytics_available']);
        $this->assertSame(1, $data['eligible_users']);
        $this->assertSame(0, $data['active_users']);
        $this->assertSame(1, $data['inactive_users']);
        $this->assertCount(0, $data['app_summary']);
        $this->assertCount(0, $data['recent_events']);

        $service->recordLogin($user, 'Claesen-Safety', 'session_cookie');
        $failedAttempt = $service->recordFailedLogin($user, $user->email, 'Claesen-Safety', 'session_cookie', null, 'invalid_password');

        $fresh = $user->fresh();
        $this->assertNotNull($fresh->last_active_at);
        $this->assertNull($fresh->last_login_at);
        $this->assertNull($fresh->last_login_app_source);
        $this->assertNull($fresh->last_login_channel);
        $this->assertFalse($failedAttempt->exists);
    }

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }
}
