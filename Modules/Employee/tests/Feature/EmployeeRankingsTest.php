<?php

declare(strict_types=1);

namespace Modules\Employee\Tests\Feature;

use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Cafca\Models\Employee;
use Modules\Performance\Models\Mirror\MirrorLabor;
use Tests\TestCase;

class EmployeeRankingsTest extends TestCase
{
    use RefreshDatabase;

    private $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withHeader('Origin', 'http://localhost');
        $this->user = UserFactory::new()->create();
    }

    private function makeEmployee(int $id, string $name): Employee
    {
        return Employee::create([
            'id'        => (string) $id,
            'name'      => $name,
            'fl_active' => true,
        ]);
    }

    private function makeEntry(int $employeeId, float $hours, string $type, string $date): void
    {
        MirrorLabor::create([
            'id'          => uniqid('L', true),
            'employee_id' => $employeeId,
            'project_id'  => 'P20260001',
            'hours'       => $hours,
            'date'        => $date,
            'labor_descr' => $type,
        ]);
    }

    // -------------------------------------------------------------------------
    // Structure
    // -------------------------------------------------------------------------

    public function test_rankings_returns_correct_envelope(): void
    {
        $this->makeEmployee(100, 'Any Worker');

        $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/employees/rankings')
            ->assertOk()
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'period'   => ['start_date', 'end_date'],
                    'rankings',
                ],
            ])
            ->assertJson(['success' => true]);
    }

    public function test_rankings_each_entry_has_required_fields(): void
    {
        $this->makeEmployee(100, 'Alice');
        $this->makeEntry(100, 8.0, 'Werf', now()->subMonth()->startOfMonth()->toDateString());

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/employees/rankings')
            ->assertOk();

        $ranking = $response->json('data.rankings.0');
        $this->assertArrayHasKey('id',          $ranking);
        $this->assertArrayHasKey('name',        $ranking);
        $this->assertArrayHasKey('total_hours', $ranking);
        $this->assertArrayHasKey('labor_hours', $ranking);
        $this->assertArrayHasKey('laden_hours',     $ranking['labor_hours']);
        $this->assertArrayHasKey('werf_hours',      $ranking['labor_hours']);
        $this->assertArrayHasKey('transport_hours', $ranking['labor_hours']);
    }

    // -------------------------------------------------------------------------
    // Sorting
    // -------------------------------------------------------------------------

    public function test_employee_with_more_hours_ranks_first(): void
    {
        $this->makeEmployee(101, 'High Hours');
        $this->makeEmployee(102, 'Low Hours');

        $start = now()->subMonth()->startOfMonth()->toDateString();
        $end   = now()->subMonth()->endOfMonth()->toDateString();

        $this->makeEntry(101, 8.0, 'Werf', $start);
        $this->makeEntry(101, 8.0, 'Werf', $start);  // 16h total
        $this->makeEntry(102, 2.0, 'Werf', $start);  // 2h total

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/employees/rankings?start_date={$start}&end_date={$end}")
            ->assertOk();

        $rankings = $response->json('data.rankings');
        $this->assertEquals('High Hours', $rankings[0]['name']);
        $this->assertEquals('Low Hours',  $rankings[1]['name']);
    }

    // -------------------------------------------------------------------------
    // Labor breakdown
    // -------------------------------------------------------------------------

    public function test_labor_breakdown_separates_types(): void
    {
        $this->makeEmployee(103, 'Breakdown Test');

        $date  = now()->subMonth()->startOfMonth()->toDateString();
        $start = now()->subMonth()->startOfMonth()->toDateString();
        $end   = now()->subMonth()->endOfMonth()->toDateString();

        $this->makeEntry(103, 4.0, 'Laden',     $date);
        $this->makeEntry(103, 3.0, 'Werf',      $date);
        $this->makeEntry(103, 1.0, 'Mobiliteit', $date);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/employees/rankings?start_date={$start}&end_date={$end}")
            ->assertOk();

        $emp = collect($response->json('data.rankings'))->firstWhere('name', 'Breakdown Test');

        $this->assertNotNull($emp);
        $this->assertEquals(4.0, $emp['labor_hours']['laden_hours']);
        $this->assertEquals(3.0, $emp['labor_hours']['werf_hours']);
        $this->assertEquals(1.0, $emp['labor_hours']['transport_hours']);
        $this->assertEquals(8.0, $emp['total_hours']);
    }

    // -------------------------------------------------------------------------
    // Date filter
    // -------------------------------------------------------------------------

    public function test_hours_outside_range_are_excluded(): void
    {
        $this->makeEmployee(104, 'May Worker');

        $this->makeEntry(104, 8.0, 'Werf', '2026-05-10');  // outside June
        $this->makeEntry(104, 4.0, 'Werf', '2026-06-10');  // inside June

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/employees/rankings?start_date=2026-06-01&end_date=2026-06-30')
            ->assertOk();

        $emp = collect($response->json('data.rankings'))->firstWhere('name', 'May Worker');
        $this->assertEquals(4.0, $emp['total_hours']);
    }

    public function test_employee_with_no_hours_in_range_shows_zero(): void
    {
        $this->makeEmployee(105, 'Silent Worker');
        // No entries at all
        $start = '2026-06-01';
        $end   = '2026-06-30';

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/employees/rankings?start_date={$start}&end_date={$end}")
            ->assertOk();

        $emp = collect($response->json('data.rankings'))->firstWhere('name', 'Silent Worker');
        $this->assertNotNull($emp);
        $this->assertEquals(0.0, $emp['total_hours']);
    }

    // -------------------------------------------------------------------------
    // No result cap — full listing
    // -------------------------------------------------------------------------

    public function test_rankings_returns_all_employees_uncapped(): void
    {
        $date  = now()->subMonth()->startOfMonth()->toDateString();
        $start = now()->subMonth()->startOfMonth()->toDateString();
        $end   = now()->subMonth()->endOfMonth()->toDateString();

        foreach (range(200, 212) as $id) {
            $this->makeEmployee($id, "Worker {$id}");
            $this->makeEntry($id, 8.0, 'Werf', $date);
        }

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/employees/rankings?start_date={$start}&end_date={$end}")
            ->assertOk();

        $this->assertCount(13, $response->json('data.rankings'));
    }
}
