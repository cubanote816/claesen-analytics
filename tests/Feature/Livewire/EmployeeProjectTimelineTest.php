<?php

namespace Tests\Feature\Livewire;

use App\Livewire\EmployeeProjectTimeline;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Mockery;
use Modules\Cafca\Models\Employee;
use Modules\Performance\Services\EmployeePerformanceService;
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

    private function mockService(bool $hasHistory = true, array ...$statsSeries): EmployeePerformanceService
    {
        $mock = Mockery::mock(EmployeePerformanceService::class);
        $mock->shouldReceive('hasAnyLaborHistory')->andReturn($hasHistory);
        $expectation = $mock->shouldReceive('getStatsForPeriod');
        foreach ($statsSeries as $stats) {
            $expectation->andReturn($stats)->once();
        }
        app()->instance(EmployeePerformanceService::class, $mock);
        return $mock;
    }

    private function makeQueryException(string $sqlstate, string $message = 'Connection error'): QueryException
    {
        $pdo = new \PDOException($message);
        $pdo->errorInfo = [$sqlstate, 0, $message];
        return new QueryException('sqlsrv', 'SELECT 1', [], $pdo);
    }

    // -------------------------------------------------------------------------
    // EMP-005 regression tests (4 tests)
    // -------------------------------------------------------------------------

    public function test_calls_service_once_on_mount(): void
    {
        $employee = $this->employee();
        $mock = Mockery::mock(EmployeePerformanceService::class);
        $mock->shouldReceive('hasAnyLaborHistory')
            ->once()
            ->andReturn(true);
        $mock->shouldReceive('getStatsForPeriod')
            ->once()
            ->andReturn($this->fakeStats([['name' => 'Project Alpha']]));
        app()->instance(EmployeePerformanceService::class, $mock);

        Livewire::test(EmployeeProjectTimeline::class, ['record' => $employee]);

        $mock->shouldHaveReceived('hasAnyLaborHistory')->once();
        $mock->shouldHaveReceived('getStatsForPeriod')->once();
    }

    public function test_calls_service_once_on_set_period(): void
    {
        $employee = $this->employee();
        $statsA = $this->fakeStats([['name' => 'Project Alpha']]);
        $statsB = $this->fakeStats([['name' => 'Project Beta']]);

        $mock = Mockery::mock(EmployeePerformanceService::class);
        $mock->shouldReceive('hasAnyLaborHistory')
            ->once()
            ->andReturn(true);
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
        $mock->shouldReceive('hasAnyLaborHistory')
            ->once()
            ->andReturn(true);
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
        $mock->shouldReceive('hasAnyLaborHistory')
            ->once()
            ->andReturn(true);
        $mock->shouldReceive('getStatsForPeriod')
            ->once()
            ->andReturn($this->fakeStats([
                ['name' => 'Visible Project Name'],
            ]));
        app()->instance(EmployeePerformanceService::class, $mock);

        Livewire::test(EmployeeProjectTimeline::class, ['record' => $employee])
            ->assertSee('Visible Project Name');
    }

    // -------------------------------------------------------------------------
    // EMP-003 new tests (7 tests)
    // -------------------------------------------------------------------------

    public function test_shows_erp_unavailable_on_connection_error_in_stats(): void
    {
        $employee = $this->employee();
        $mock = Mockery::mock(EmployeePerformanceService::class);
        $mock->shouldReceive('hasAnyLaborHistory')->once()->andReturn(true);
        $mock->shouldReceive('getStatsForPeriod')
            ->once()
            ->andThrow($this->makeQueryException('HYT00', 'Login timeout expired'));
        app()->instance(EmployeePerformanceService::class, $mock);

        Livewire::test(EmployeeProjectTimeline::class, ['record' => $employee])
            ->assertSet('componentState', 'erp_unavailable');
    }

    public function test_shows_no_period_activity_when_employee_has_history(): void
    {
        $employee = $this->employee();
        $mock = Mockery::mock(EmployeePerformanceService::class);
        $mock->shouldReceive('hasAnyLaborHistory')->once()->andReturn(true);
        $mock->shouldReceive('getStatsForPeriod')
            ->once()
            ->andReturn($this->fakeStats()); // 0 hours
        app()->instance(EmployeePerformanceService::class, $mock);

        Livewire::test(EmployeeProjectTimeline::class, ['record' => $employee])
            ->assertSet('componentState', 'no_period_activity');
    }

    public function test_shows_no_history_when_employee_has_no_labor_data(): void
    {
        $employee = $this->employee();
        $mock = Mockery::mock(EmployeePerformanceService::class);
        $mock->shouldReceive('hasAnyLaborHistory')->once()->andReturn(false);
        $mock->shouldReceive('getStatsForPeriod')
            ->once()
            ->andReturn($this->fakeStats()); // 0 hours
        app()->instance(EmployeePerformanceService::class, $mock);

        Livewire::test(EmployeeProjectTimeline::class, ['record' => $employee])
            ->assertSet('componentState', 'no_history');
    }

    public function test_sqlstate_22012_is_rethrown_not_swallowed(): void
    {
        $employee = $this->employee();
        $mock = Mockery::mock(EmployeePerformanceService::class);
        $mock->shouldReceive('hasAnyLaborHistory')->once()->andReturn(true);
        $mock->shouldReceive('getStatsForPeriod')
            ->once()
            ->andThrow($this->makeQueryException('22012', 'Division by zero'));
        app()->instance(EmployeePerformanceService::class, $mock);

        // Livewire wraps re-thrown exceptions in ViewException — unwrap the chain
        try {
            Livewire::test(EmployeeProjectTimeline::class, ['record' => $employee]);
            $this->fail('Expected QueryException was not thrown');
        } catch (\Throwable $e) {
            $cause = $e;
            while ($cause->getPrevious() !== null && ! $cause instanceof QueryException) {
                $cause = $cause->getPrevious();
            }
            $this->assertInstanceOf(QueryException::class, $cause,
                'Expected QueryException in chain, got: ' . get_class($e));
            $this->assertSame('22012', $cause->errorInfo[0] ?? null);
        }
    }

    public function test_retryload_recovers_history_and_stats_after_erp_failure(): void
    {
        $employee = $this->employee();
        $stats = $this->fakeStats([['name' => 'Recovered Project']]);
        $connEx = $this->makeQueryException('HYT00', 'Login timeout expired');

        $callCount = 0;
        $mock = Mockery::mock(EmployeePerformanceService::class);
        $mock->shouldReceive('hasAnyLaborHistory')
            ->twice()
            ->andReturnUsing(function () use (&$callCount, $connEx) {
                if ($callCount++ === 0) {
                    throw $connEx;
                }
                return true;
            });
        $mock->shouldReceive('getStatsForPeriod')
            ->once()
            ->andReturn($stats);
        app()->instance(EmployeePerformanceService::class, $mock);

        $component = Livewire::test(EmployeeProjectTimeline::class, ['record' => $employee]);
        $component->assertSet('componentState', 'erp_unavailable')
                  ->assertSet('hasHistory', null);

        $component->call('retryLoad');

        $component->assertSet('componentState', 'ready')
                  ->assertSee('Recovered Project');
    }

    public function test_hashistory_null_with_zero_hours_shows_erp_unavailable_not_no_history(): void
    {
        $employee = $this->employee();
        $connEx = $this->makeQueryException('HYT00', 'Login timeout expired');

        // Mount: hasAnyLaborHistory fails → hasHistory = null, componentState = erp_unavailable
        $mock = Mockery::mock(EmployeePerformanceService::class);
        $mock->shouldReceive('hasAnyLaborHistory')
            ->once()
            ->andThrow($connEx);
        $mock->shouldNotReceive('getStatsForPeriod');
        app()->instance(EmployeePerformanceService::class, $mock);

        $component = Livewire::test(EmployeeProjectTimeline::class, ['record' => $employee]);
        $component->assertSet('componentState', 'erp_unavailable')
                  ->assertSet('hasHistory', null);

        // Rebind service: ERP recovers for stats but hasHistory stays null
        $mock2 = Mockery::mock(EmployeePerformanceService::class);
        $mock2->shouldReceive('getStatsForPeriod')
              ->once()
              ->andReturn($this->fakeStats()); // 0 hours
        $mock2->shouldNotReceive('hasAnyLaborHistory');
        app()->instance(EmployeePerformanceService::class, $mock2);

        // setPeriod calls calculateStats — hasHistory still null, hours = 0 → default arm → erp_unavailable
        $component->call('setPeriod', 'last_month');

        $component->assertSet('componentState', 'erp_unavailable');
    }

    public function test_logic_exception_with_connection_message_is_rethrown(): void
    {
        $employee = $this->employee();
        $mock = Mockery::mock(EmployeePerformanceService::class);
        $mock->shouldReceive('hasAnyLaborHistory')->once()->andReturn(true);
        $mock->shouldReceive('getStatsForPeriod')
            ->once()
            ->andThrow(new \LogicException('unable to connect to ERP'));
        app()->instance(EmployeePerformanceService::class, $mock);

        // Livewire wraps re-thrown exceptions in ViewException — unwrap the chain
        try {
            Livewire::test(EmployeeProjectTimeline::class, ['record' => $employee]);
            $this->fail('Expected LogicException was not thrown');
        } catch (\Throwable $e) {
            $cause = $e;
            while ($cause->getPrevious() !== null && ! $cause instanceof \LogicException) {
                $cause = $cause->getPrevious();
            }
            $this->assertInstanceOf(\LogicException::class, $cause,
                'Expected LogicException in chain, got: ' . get_class($e));
            $this->assertStringContainsString('unable to connect', $cause->getMessage());
        }
    }
}
