<?php

declare(strict_types=1);

namespace Modules\Employee\Tests\Feature;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Cafca\Models\Employee;
use Modules\Employee\Repositories\ProjectRepository;
use Modules\Performance\Models\Mirror\MirrorLabor;
use Tests\TestCase;

class EmployeeTimeStatsTest extends TestCase
{
    use RefreshDatabase;

    private $user;
    private Employee $employee;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withHeader('Origin', 'http://localhost');

        // Prevent SQL Server calls: ProjectRepository uses Cafca\Project (sqlsrv connection)
        $this->mock(ProjectRepository::class, function ($mock) {
            $mock->shouldReceive('getProjectsByIds')->andReturn(new EloquentCollection());
            $mock->shouldReceive('find')->andReturn(null);
        });

        $this->user     = UserFactory::new()->create();
        $this->employee = Employee::create([
            'id'            => '200',
            'name'          => 'Test Worker',
            'fl_active'     => true,
            'uren_per_week' => 40,
        ]);
    }

    private function labor(string $date, float $hours, string $type, array $overrides = []): void
    {
        MirrorLabor::create(array_merge([
            'id'                 => uniqid('L', true),
            'employee_id'        => 200,
            'project_id'         => 'P20260001',
            'hours'              => $hours,
            'date'               => $date,
            'labor_descr'        => $type,
            'h_from_1'           => '07:00:00',
            'h_to_1'             => '15:00:00',
            'fl_approved'        => true,
            'total_costprice'    => 100.0,
            'total_salesprice'   => 150.0,
            'distance'           => 10.0,
            'transport_costprice'  => 5.0,
            'transport_salesprice' => 8.0,
        ], $overrides));
    }

    // =========================================================================
    // Day stats
    // =========================================================================

    public function test_day_stats_returns_correct_envelope(): void
    {
        $this->labor('2026-06-02', 4.0, 'Laden');

        $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/employees/200/time/day/2026-06-02')
            ->assertOk()
            ->assertJson(['success' => true])
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'date',
                    'day_info'    => ['is_weekend', 'day_name', 'is_working_day'],
                    'summary'     => ['total_hours', 'approved_hours', 'target_hours', 'achievement_percentage'],
                    'schedule'    => ['start_time', 'end_time'],
                    'labor_hours' => ['laden_hours', 'werf_hours', 'transport_hours'],
                    'financial'   => ['costs', 'revenue', 'profit'],
                    'transport'   => ['total_distance', 'transport_cost', 'transport_revenue'],
                    'projects',
                ],
            ]);
    }

    public function test_day_stats_sums_labor_breakdown_correctly(): void
    {
        $this->labor('2026-06-02', 4.0, 'Laden');
        $this->labor('2026-06-02', 2.5, 'Werf');
        $this->labor('2026-06-02', 1.0, 'Mobiliteit');

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/employees/200/time/day/2026-06-02')
            ->assertOk();

        $labor = $response->json('data.labor_hours');
        $this->assertEquals(4.0, $labor['laden_hours']);
        $this->assertEquals(2.5, $labor['werf_hours']);
        $this->assertEquals(1.0, $labor['transport_hours']);
        $this->assertEquals(7.5, $response->json('data.summary.total_hours'));
    }

    public function test_day_stats_approved_hours_only_count_fl_approved(): void
    {
        $this->labor('2026-06-02', 6.0, 'Werf', ['fl_approved' => true]);
        $this->labor('2026-06-02', 2.0, 'Laden', ['fl_approved' => false]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/employees/200/time/day/2026-06-02')
            ->assertOk();

        $this->assertEquals(8.0, $response->json('data.summary.total_hours'));
        $this->assertEquals(6.0, $response->json('data.summary.approved_hours'));
    }

    public function test_day_stats_saturday_sets_is_weekend_true_and_zero_target(): void
    {
        // 2026-06-06 is a Saturday
        $this->labor('2026-06-06', 3.0, 'Werf');

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/employees/200/time/day/2026-06-06')
            ->assertOk();

        $this->assertTrue($response->json('data.day_info.is_weekend'));
        $this->assertFalse($response->json('data.day_info.is_working_day'));
        $this->assertEquals(0.0, $response->json('data.summary.target_hours'));
    }

    public function test_day_stats_empty_day_returns_zero_hours_with_correct_structure(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/employees/200/time/day/2026-01-15')
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->assertEquals(0.0, $response->json('data.summary.total_hours'));
        $this->assertEquals(0.0, $response->json('data.summary.approved_hours'));
        $this->assertEquals([], $response->json('data.projects'));
    }

    public function test_day_stats_unknown_employee_returns_500(): void
    {
        $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/employees/999999/time/day/2026-06-01')
            ->assertStatus(500)
            ->assertJson(['success' => false]);
    }

    // =========================================================================
    // Week stats
    // =========================================================================

    public function test_week_stats_returns_correct_envelope(): void
    {
        $this->labor('2026-06-02', 8.0, 'Werf');

        $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/employees/200/time/week/2026-06-02/2026-06-06')
            ->assertOk()
            ->assertJson(['success' => true])
            ->assertJsonStructure([
                'success',
                'data' => [
                    'period'         => ['start_date', 'end_date', 'working_days'],
                    'summary'        => ['total_hours', 'target_hours', 'achievement_percentage', 'days_worked', 'average_daily_hours', 'total_projects'],
                    'labor_hours'    => ['laden_hours', 'werf_hours', 'transport_hours'],
                    'financial'      => ['costs', 'revenue', 'profit'],
                    'transport'      => ['total_distance', 'transport_cost', 'transport_revenue'],
                    'daily_breakdown',
                    'projects',
                ],
            ]);
    }

    public function test_week_stats_aggregates_hours_across_days(): void
    {
        $this->labor('2026-06-01', 8.0, 'Werf');
        $this->labor('2026-06-02', 8.0, 'Laden');
        $this->labor('2026-06-03', 4.0, 'Mobiliteit');

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/employees/200/time/week/2026-06-01/2026-06-07')
            ->assertOk();

        $this->assertEquals(20.0, $response->json('data.summary.total_hours'));
        $this->assertEquals(8.0,  $response->json('data.labor_hours.werf_hours'));
        $this->assertEquals(8.0,  $response->json('data.labor_hours.laden_hours'));
        $this->assertEquals(4.0,  $response->json('data.labor_hours.transport_hours'));
    }

    public function test_week_stats_daily_breakdown_contains_one_entry_per_day_worked(): void
    {
        $this->labor('2026-06-01', 8.0, 'Werf');
        $this->labor('2026-06-03', 6.0, 'Laden');

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/employees/200/time/week/2026-06-01/2026-06-07')
            ->assertOk();

        $this->assertCount(2, $response->json('data.daily_breakdown'));
    }

    public function test_week_range_over_7_days_returns_500(): void
    {
        $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/employees/200/time/week/2026-06-01/2026-06-15')
            ->assertStatus(500)
            ->assertJson(['success' => false]);
    }

    // =========================================================================
    // Month stats
    // =========================================================================

    public function test_month_stats_returns_correct_envelope(): void
    {
        $this->labor('2026-06-02', 8.0, 'Werf');

        $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/employees/200/time/month/2026-06/weeks')
            ->assertOk()
            ->assertJson(['success' => true])
            ->assertJsonStructure([
                'success',
                'data' => [
                    'period'      => ['month', 'year'],
                    'employee_id',
                    'weeks',
                ],
            ]);
    }

    public function test_month_stats_weeks_is_non_empty_array(): void
    {
        $this->labor('2026-06-02', 8.0, 'Werf');
        $this->labor('2026-06-09', 8.0, 'Laden');

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/employees/200/time/month/2026-06/weeks')
            ->assertOk();

        $weeks = $response->json('data.weeks');
        $this->assertIsArray($weeks);
        $this->assertNotEmpty($weeks);
    }

    public function test_month_stats_each_week_has_required_keys(): void
    {
        $this->labor('2026-06-02', 8.0, 'Werf');

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/employees/200/time/month/2026-06/weeks')
            ->assertOk();

        $week = $response->json('data.weeks.0');
        $this->assertArrayHasKey('start_date',   $week);
        $this->assertArrayHasKey('end_date',     $week);
        $this->assertArrayHasKey('total_hours',  $week);
        $this->assertArrayHasKey('labor_hours',  $week);
        $this->assertArrayHasKey('target_hours', $week);
        $this->assertArrayHasKey('working_days', $week);
    }

    public function test_month_stats_unknown_employee_returns_500(): void
    {
        $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/employees/999999/time/month/2026-06/weeks')
            ->assertStatus(500)
            ->assertJson(['success' => false]);
    }
}
