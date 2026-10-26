<?php

declare(strict_types=1);

namespace Modules\Knx\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Models\Organization;
use Modules\Core\Models\User;
use Modules\Knx\Database\Seeders\KnxDemoSeeder;
use Modules\Knx\Models\KnxDevice;
use Modules\Knx\Models\KnxEmployee;
use Modules\Knx\Models\KnxPlanningAssignment;
use Modules\Knx\Models\KnxProject;
use Tests\TestCase;

/**
 * K7b — the dashboard aggregate (docs/BACKEND-API.md §4.2).
 *
 * Two of its numbers are weekday-dependent in real life (who is scheduled today,
 * what was registered this week), so the assertions create their own row instead
 * of relying on the fixture's dates — otherwise this suite would pass Monday to
 * Friday and fail on a weekend.
 */
final class KnxDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Organization::factory()->create(['slug' => 'electro-bertels']);

        $this->seed(KnxDemoSeeder::class);

        $this->actingAs(
            User::query()->where('email', 'lien.smet@electrobertels.be')->sole(),
            'sanctum',
        );
    }

    public function test_the_dashboard_has_the_contract_shape(): void
    {
        $payload = $this->getJson('/api/v1/knx/dashboard')->assertOk()->json();

        $this->assertSame(['kpis', 'activeProjects', 'openConflicts', 'techniciansToday'], array_keys($payload));
        $this->assertSame(
            ['activeProjects', 'inDelivery', 'devicesThisWeek', 'openConflicts', 'criticalConflicts', 'techniciansScheduledToday', 'techniciansTotal'],
            array_keys($payload['kpis']),
        );

        $this->assertSame(
            ['code', 'name', 'clientId', 'clientName', 'city', 'lead', 'devicesPlanned', 'devicesDone', 'photos', 'status', 'deadline'],
            array_keys($payload['activeProjects'][0]),
        );

        $this->assertSame(
            ['technician', 'projectCode', 'projectName', 'city', 'status'],
            array_keys($payload['techniciansToday'][0]),
        );
        $this->assertSame(['id', 'initials', 'name'], array_keys($payload['techniciansToday'][0]['technician']));
    }

    public function test_the_kpis_count_the_right_things(): void
    {
        $payload = $this->getJson('/api/v1/knx/dashboard')->json();

        // Four of the fixtures's five projects are not done.
        $this->assertSame(4, $payload['kpis']['activeProjects']);
        $this->assertSame(4, count($payload['activeProjects']));
        $this->assertSame(1, $payload['kpis']['inDelivery']);

        // k1, k2, k3 and k4 are open/in_review; k1 and k2 are critical.
        $this->assertSame(4, $payload['kpis']['openConflicts']);
        $this->assertCount(4, $payload['openConflicts']);
        $this->assertSame(2, $payload['kpis']['criticalConflicts']);

        $this->assertNotContains('done', array_column($payload['activeProjects'], 'status'));

        // The conflict panel comes ordered the way the office triages.
        $this->assertSame(['critical', 'critical', 'warning', 'warning'], array_column($payload['openConflicts'], 'severity'));
    }

    public function test_it_reports_one_row_per_active_technician(): void
    {
        $payload = $this->getJson('/api/v1/knx/dashboard')->json();

        $this->assertSame(5, $payload['kpis']['techniciansTotal']);
        $this->assertCount(5, $payload['techniciansToday']);

        $this->assertSame(
            ['JV', 'MC', 'SW', 'TJ', 'KP'],
            array_column(array_column($payload['techniciansToday'], 'technician'), 'initials'),
        );
    }

    public function test_today_is_reported_from_the_planning(): void
    {
        // Own row: the fixture plans Monday–Friday, so "today" only exists on a
        // weekday otherwise.
        KnxPlanningAssignment::query()->whereDate('date', now()->toDateString())->delete();

        $technician = KnxEmployee::query()->field()->orderBy('id')->firstOrFail();
        $project = KnxProject::query()->where('code', 'C1618')->sole();

        KnxPlanningAssignment::create([
            'employee_id' => $technician->getKey(),
            'project_id' => $project->getKey(),
            'date' => now()->toDateString(),
        ]);

        $payload = $this->getJson('/api/v1/knx/dashboard')->json();
        $row = collect($payload['techniciansToday'])->firstWhere('technician.id', (string) $technician->getKey());

        $this->assertSame(1, $payload['kpis']['techniciansScheduledToday']);
        $this->assertSame('C1618', $row['projectCode']);
        $this->assertSame($project->name, $row['projectName']);
        $this->assertSame($project->city, $row['city']);
        $this->assertSame('on_site', $row['status']);

        // Everyone else is off, and `travelling` is never invented: nothing in this
        // domain knows whether somebody is on the road.
        $others = collect($payload['techniciansToday'])->where('technician.id', '!=', (string) $technician->getKey());

        $this->assertCount(4, $others);
        $this->assertSame([], $others->where('status', '!=', 'off')->pluck('status')->all());
        $this->assertSame([], collect($payload['techniciansToday'])->where('status', 'travelling')->all());
    }

    public function test_devices_registered_this_week_are_counted_from_monday(): void
    {
        $before = $this->getJson('/api/v1/knx/dashboard')->json('kpis.devicesThisWeek');

        KnxDevice::factory()->fromField()->create([
            'project_id' => KnxProject::query()->where('code', 'C1618')->sole()->getKey(),
            'registered_at' => now(),
        ]);

        $after = $this->getJson('/api/v1/knx/dashboard')->json('kpis.devicesThisWeek');

        $this->assertSame($before + 1, $after);

        // A registration from before Monday must not move the number.
        KnxDevice::factory()->fromField()->create([
            'project_id' => KnxProject::query()->where('code', 'C1618')->sole()->getKey(),
            'registered_at' => now()->startOfWeek()->subWeek(),
        ]);

        $this->assertSame($after, $this->getJson('/api/v1/knx/dashboard')->json('kpis.devicesThisWeek'));
    }

    public function test_the_dashboard_requires_a_token(): void
    {
        $this->app['auth']->forgetGuards();

        $this->getJson('/api/v1/knx/dashboard')->assertUnauthorized()->assertJsonPath('code', 'unauthenticated');
    }
}
