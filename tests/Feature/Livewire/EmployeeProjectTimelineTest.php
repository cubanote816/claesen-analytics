<?php

namespace Tests\Feature\Livewire;

use App\Livewire\EmployeeProjectTimeline;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Mockery;
use Modules\Cafca\Models\Employee;
use Modules\Performance\Services\EmployeePerformanceService;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EmployeeProjectTimelineTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function employee(): Employee
    {
        return Employee::create(['id' => 'TEST01', 'name' => 'Test Employee']);
    }

    private function fakeStats(array $projects = []): array
    {
        $fakeProjects = collect($projects)->values()->map(fn($p, $i) => [
            'project_id'        => 'P2026' . str_pad((string) $i, 4, '0', STR_PAD_LEFT),
            'project_name'      => $p['name'],
            'project_type_name' => 'Werf',
            'total_hours'       => 10.0,
            'last_active'       => Carbon::parse('2026-05-15'),
            'categories'        => ['Werf' => 10.0, 'Laden' => 0.0, 'Mobiliteit' => 0.0],
            'labor_breakdown'   => collect([]),
        ]);

        return [
            'hours'                => (float) $fakeProjects->sum('total_hours'),
            'working_days'         => 2,
            'achievement_rate'     => 50.0,
            'categories'           => ['Werf' => 5.0, 'Laden' => 3.0, 'Mobiliteit' => 2.0],
            'projects'             => $fakeProjects,
            'temporal_distribution'=> [],
            'temporal_type'        => 'daily',
            'period'               => ['start' => '2026-06-01', 'end' => '2026-06-22'],
        ];
    }

    private function mockService(array ...$statsSeries): EmployeePerformanceService
    {
        $mock = Mockery::mock(EmployeePerformanceService::class);
        $expectation = $mock->shouldReceive('getStatsForPeriod');
        foreach ($statsSeries as $stats) {
            $expectation->andReturn($stats)->once();
        }
        app()->instance(EmployeePerformanceService::class, $mock);
        return $mock;
    }

    // -------------------------------------------------------------------------
    // Tests
    // -------------------------------------------------------------------------

    public function test_calls_service_once_on_mount(): void
    {
        $employee = $this->employee();
        $mock = Mockery::mock(EmployeePerformanceService::class);
        $mock->shouldReceive('getStatsForPeriod')
            ->once()
            ->andReturn($this->fakeStats([['name' => 'Project Alpha']]));
        app()->instance(EmployeePerformanceService::class, $mock);

        Livewire::test(EmployeeProjectTimeline::class, ['record' => $employee]);

        $mock->shouldHaveReceived('getStatsForPeriod')->once();
    }

    public function test_calls_service_once_on_set_period(): void
    {
        $employee = $this->employee();
        $statsA = $this->fakeStats([['name' => 'Project Alpha']]);
        $statsB = $this->fakeStats([['name' => 'Project Beta']]);

        $mock = Mockery::mock(EmployeePerformanceService::class);
        $mock->shouldReceive('getStatsForPeriod')
            ->andReturn($statsA, $statsB);
        app()->instance(EmployeePerformanceService::class, $mock);

        Livewire::test(EmployeeProjectTimeline::class, ['record' => $employee])
            ->call('setPeriod', 'last_month');

        $mock->shouldHaveReceived('getStatsForPeriod')->twice();
    }

    public function test_period_change_clears_previous_projects_and_resets_page(): void
    {
        $employee = $this->employee();

        // Period A: 7 projects → 2 pages (perPage = 6)
        $periodAProjects = array_map(fn($n) => ['name' => "Project $n"], range(1, 7));
        $statsA = $this->fakeStats($periodAProjects);

        // Period B: 3 entirely different projects
        $statsB = $this->fakeStats([
            ['name' => 'New Project X'],
            ['name' => 'New Project Y'],
            ['name' => 'New Project Z'],
        ]);

        $mock = Mockery::mock(EmployeePerformanceService::class);
        $mock->shouldReceive('getStatsForPeriod')
            ->andReturn($statsA, $statsB);
        app()->instance(EmployeePerformanceService::class, $mock);

        $component = Livewire::test(EmployeeProjectTimeline::class, ['record' => $employee]);

        // Navigate to page 2 — "Project 7" is on page 2
        $component->call('gotoPage', 2);

        // Exactly one service call so far (from mount)
        $mock->shouldHaveReceived('getStatsForPeriod')->once();
        $component->assertSee('Project 7')
                  ->assertDontSee('New Project X');

        // Change period → cachedProjects replaced, page resets to 1
        $component->call('setPeriod', 'last_month');

        // Now exactly two service calls total
        $mock->shouldHaveReceived('getStatsForPeriod')->twice();

        $component->assertSee('New Project X')
                  ->assertSee('New Project Y')
                  ->assertSee('New Project Z')
                  ->assertDontSee('Project 7')
                  ->assertDontSee('Project 1');

        $this->assertEquals(1, $component->instance()->getPage());
    }

    public function test_project_data_visible_matches_mock(): void
    {
        $employee = $this->employee();
        $mock = Mockery::mock(EmployeePerformanceService::class);
        $mock->shouldReceive('getStatsForPeriod')
            ->once()
            ->andReturn($this->fakeStats([
                ['name' => 'Visible Project Name'],
            ]));
        app()->instance(EmployeePerformanceService::class, $mock);

        Livewire::test(EmployeeProjectTimeline::class, ['record' => $employee])
            ->assertSee('Visible Project Name');
    }
}
