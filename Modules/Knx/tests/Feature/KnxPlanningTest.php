<?php

declare(strict_types=1);

namespace Modules\Knx\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Modules\Core\Models\Organization;
use Modules\Core\Models\User;
use Modules\Knx\Database\Seeders\KnxDemoSeeder;
use Modules\Knx\Models\KnxEmployee;
use Modules\Knx\Models\KnxPlanningAssignment;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * K5 — technicians and planning (docs/BACKEND-API.md §4.4).
 *
 * The interesting part is `PUT /planning`: the contract calls it idempotent on
 * (technicianId, date), so assigning twice must leave one row, and assigning a
 * different project to the same day must replace — office planners drag rows
 * around and expect that, not an ever-growing list.
 */
final class KnxPlanningTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $organization = Organization::factory()->create(['slug' => 'electro-bertels']);

        Role::findOrCreate('knx_office', 'web');
        $user = User::factory()->create([
            'email' => 'lead@electrobertels.be',
            'password' => Hash::make('Secret1234!'),
            'is_active' => true,
            'organization_id' => $organization->id,
        ]);
        $user->assignRole('knx_office');
        KnxEmployee::factory()->office()->forOrganization($organization)->create(['user_id' => $user->id]);

        $this->seed(KnxDemoSeeder::class);

        $this->actingAs($user, 'sanctum');
    }

    /** @return array{0: string, 1: string} a field employee id and a project code */
    private function aTechnicianAndProject(): array
    {
        return [
            (string) KnxEmployee::query()->field()->orderBy('id')->firstOrFail()->id,
            'C1618',
        ];
    }

    public function test_the_technician_list_has_the_contract_shape_and_only_field_people(): void
    {
        $technicians = $this->getJson('/api/v1/knx/technicians')->assertOk()->json();

        $this->assertSame(['id', 'initials', 'name'], array_keys($technicians[0]));
        $this->assertSame(['JV', 'MC', 'SW', 'TJ', 'KP'], array_column($technicians, 'initials'));

        // The two office people are not technicians: they are never planned.
        $this->assertNotContains('LS', array_column($technicians, 'initials'));
        $this->assertNotContains('PA', array_column($technicians, 'initials'));
    }

    public function test_the_planning_range_is_required_and_bounded(): void
    {
        $this->getJson('/api/v1/knx/planning')
            ->assertStatus(422)
            ->assertJsonStructure(['errors' => ['from', 'to']]);

        $monday = now()->startOfWeek()->format('Y-m-d');

        $week = $this->getJson("/api/v1/knx/planning?from={$monday}&to=".now()->startOfWeek()->addDays(4)->format('Y-m-d'))
            ->assertOk()
            ->json();

        $this->assertNotEmpty($week);
        $this->assertSame(['technicianId', 'date', 'projectCode'], array_keys($week[0]));

        // An empty range is not an error, and the previous week has nothing.
        $this->getJson('/api/v1/knx/planning?from=2020-01-06&to=2020-01-10')->assertOk()->assertJsonCount(0);

        $this->getJson("/api/v1/knx/planning?from={$monday}&to=2020-01-10")->assertStatus(422);
    }

    public function test_assigning_is_idempotent_on_technician_and_day(): void
    {
        [$technicianId] = $this->aTechnicianAndProject();
        $date = now()->startOfWeek()->addDays(4)->format('Y-m-d');

        $response = $this->putJson('/api/v1/knx/planning', [
            'technicianId' => $technicianId,
            'date' => $date,
            'projectCode' => 'C1618',
        ])->assertOk();

        $this->assertSame(
            ['technicianId' => $technicianId, 'date' => $date, 'projectCode' => 'C1618'],
            $response->json(),
        );

        $this->assertSame(1, KnxPlanningAssignment::query()
            ->where('employee_id', $technicianId)
            ->whereDate('date', $date)
            ->count());

        // Replacing the project is what a planner does when dragging the row
        // elsewhere: it must replace, not add.
        $this->putJson('/api/v1/knx/planning', [
            'technicianId' => $technicianId,
            'date' => $date,
            'projectCode' => '240512',
        ])->assertOk()->assertJsonPath('projectCode', '240512');

        $this->assertSame(1, KnxPlanningAssignment::query()
            ->where('employee_id', $technicianId)
            ->whereDate('date', $date)
            ->count());
    }

    public function test_assigning_rejects_an_unknown_project_or_a_non_technician(): void
    {
        [$technicianId] = $this->aTechnicianAndProject();
        $officeId = (string) KnxEmployee::query()->office()->firstOrFail()->id;
        $date = now()->startOfWeek()->format('Y-m-d');

        $this->putJson('/api/v1/knx/planning', [
            'technicianId' => $technicianId,
            'date' => $date,
            'projectCode' => 'NOPE',
        ])->assertStatus(422)->assertJsonStructure(['errors' => ['projectCode']]);

        // An office person is a real person, but not a technician: one clear
        // message instead of a silent assignment nobody can act on.
        $this->putJson('/api/v1/knx/planning', [
            'technicianId' => $officeId,
            'date' => $date,
            'projectCode' => 'C1618',
        ])->assertStatus(422)->assertJsonStructure(['errors' => ['technicianId']]);

        $this->putJson('/api/v1/knx/planning', ['date' => $date])
            ->assertStatus(422)
            ->assertJsonStructure(['errors' => ['technicianId', 'projectCode']]);
    }

    public function test_unassigning_removes_the_day_and_is_not_an_error_when_empty(): void
    {
        $assignment = KnxPlanningAssignment::query()->with('project')->firstOrFail();

        $this->deleteJson('/api/v1/knx/planning?technicianId='.$assignment->employee_id.'&date='.$assignment->date->format('Y-m-d'))
            ->assertNoContent();

        $this->assertFalse(KnxPlanningAssignment::query()->whereKey($assignment->id)->exists());

        // Asking again for the same empty day is the state the caller wanted.
        $this->deleteJson('/api/v1/knx/planning?technicianId='.$assignment->employee_id.'&date='.$assignment->date->format('Y-m-d'))
            ->assertNoContent();
    }

    public function test_the_planning_endpoints_require_a_token(): void
    {
        $this->app['auth']->forgetGuards();

        $this->getJson('/api/v1/knx/technicians')->assertUnauthorized();
        $this->getJson('/api/v1/knx/planning?from=2026-01-01&to=2026-01-05')->assertUnauthorized();
        $this->putJson('/api/v1/knx/planning', [])->assertUnauthorized();
        $this->deleteJson('/api/v1/knx/planning')->assertUnauthorized();
    }
}
