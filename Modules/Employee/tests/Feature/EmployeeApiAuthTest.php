<?php

declare(strict_types=1);

namespace Modules\Employee\Tests\Feature;

use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployeeApiAuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withHeader('Origin', 'http://localhost');
    }

    // -------------------------------------------------------------------------
    // Unauthenticated — all endpoints must return 401
    // -------------------------------------------------------------------------

    public function test_employee_list_requires_auth(): void
    {
        $this->getJson('/api/v1/employees')->assertUnauthorized();
    }

    public function test_rankings_requires_auth(): void
    {
        $this->getJson('/api/v1/employees/rankings')->assertUnauthorized();
    }

    public function test_dashboard_requires_auth(): void
    {
        $this->getJson('/api/v1/employees/dashboard')->assertUnauthorized();
    }

    public function test_time_stats_requires_auth(): void
    {
        $this->getJson('/api/v1/employees/100/time/stats')->assertUnauthorized();
    }

    public function test_day_stats_requires_auth(): void
    {
        $this->getJson('/api/v1/employees/100/time/day/2026-06-01')->assertUnauthorized();
    }

    public function test_week_stats_requires_auth(): void
    {
        $this->getJson('/api/v1/employees/100/time/week/2026-06-01/2026-06-07')->assertUnauthorized();
    }

    public function test_month_weeks_requires_auth(): void
    {
        $this->getJson('/api/v1/employees/100/time/month/2026-06/weeks')->assertUnauthorized();
    }

    public function test_analytics_dashboard_requires_auth(): void
    {
        $this->getJson('/api/v1/employees/analytics/dashboard')->assertUnauthorized();
    }

    public function test_projects_with_worked_hours_requires_auth(): void
    {
        $this->getJson('/api/v1/employees/projects/with-worked-hours')->assertUnauthorized();
    }

    // -------------------------------------------------------------------------
    // Authenticated — valid token reaches the endpoint (no 401/403)
    // -------------------------------------------------------------------------

    public function test_authenticated_user_can_reach_employee_list(): void
    {
        $user = UserFactory::new()->create();

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/employees')
            ->assertOk()
            ->assertJsonStructure(['success', 'data', 'message'])
            ->assertJson(['success' => true]);
    }

    public function test_authenticated_user_can_reach_rankings(): void
    {
        $user = UserFactory::new()->create();

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/employees/rankings')
            ->assertOk()
            ->assertJson(['success' => true]);
    }
}
