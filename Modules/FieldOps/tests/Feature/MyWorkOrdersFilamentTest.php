<?php

declare(strict_types=1);

namespace Modules\FieldOps\Tests\Feature;

use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\Cafca\Models\Employee;
use Modules\FieldOps\Enums\MaintenanceWorkOrderStatus;
use Modules\FieldOps\Filament\Pages\MyWorkOrders;
use Modules\FieldOps\Filament\Resources\ComplexResource;
use Modules\FieldOps\Models\FoMaintenanceType;
use Modules\FieldOps\Models\FoMaintenanceWorkOrder;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MyWorkOrdersFilamentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['technician', 'super_admin', 'admin', 'project_manager'] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }
    }

    private static int $employeeSequence = 0;

    private function makeEmployee(): Employee
    {
        $id = 'TECH-'.(++self::$employeeSequence);

        return Employee::create(['id' => $id, 'name' => "Technician {$id}", 'fl_active' => true]);
    }

    private function technician(): array
    {
        $employee = $this->makeEmployee();
        $user = UserFactory::new()->create(['employee_id' => $employee->id]);
        $user->assignRole('technician');

        return [$user, $employee];
    }

    public function test_technician_only_sees_their_own_assigned_work_orders(): void
    {
        [$user, $employee] = $this->technician();
        $otherEmployee = $this->makeEmployee();
        // Two factory() calls in one test each independently spin up their own
        // preventive FoMaintenanceType and collide on its unique code — share one
        // instead (same fix as CLA-502 this session).
        $maintenanceType = FoMaintenanceType::factory()->preventive()->create();

        $mine = FoMaintenanceWorkOrder::factory()->create([
            'fo_maintenance_type_id' => $maintenanceType->id,
            'assigned_employee_id' => $employee->id,
            'status' => MaintenanceWorkOrderStatus::ASSIGNED,
        ]);
        $notMine = FoMaintenanceWorkOrder::factory()->create([
            'fo_maintenance_type_id' => $maintenanceType->id,
            'assigned_employee_id' => $otherEmployee->id,
            'status' => MaintenanceWorkOrderStatus::ASSIGNED,
        ]);

        $this->actingAs($user);

        Livewire::test(MyWorkOrders::class)
            ->assertCanSeeTableRecords([$mine])
            ->assertCanNotSeeTableRecords([$notMine]);
    }

    public function test_technician_can_start_their_own_planned_order(): void
    {
        [$user, $employee] = $this->technician();
        $order = FoMaintenanceWorkOrder::factory()->create([
            'assigned_employee_id' => $employee->id,
            'status' => MaintenanceWorkOrderStatus::PLANNED,
        ]);

        $this->actingAs($user);

        Livewire::test(MyWorkOrders::class)
            ->callTableAction('start', $order)
            ->assertHasNoTableActionErrors();

        $this->assertSame(MaintenanceWorkOrderStatus::IN_PROGRESS, $order->fresh()->status);
        $this->assertSame($user->id, $order->fresh()->started_by_user_id);
    }

    public function test_technician_can_submit_their_own_in_progress_order(): void
    {
        [$user, $employee] = $this->technician();
        $order = FoMaintenanceWorkOrder::factory()->create([
            'assigned_employee_id' => $employee->id,
            'status' => MaintenanceWorkOrderStatus::IN_PROGRESS,
            'started_at' => now()->subHour(),
        ]);

        $this->actingAs($user);

        Livewire::test(MyWorkOrders::class)
            ->callTableAction('complete', $order, data: [
                'root_cause' => 'Loose connector.',
                'solution_applied' => 'Reseated the connector and verified continuity.',
                'completion_notes' => 'All good.',
                'completed_at' => now()->toDateTimeString(),
                'completion_details' => ['inspection' => true],
            ])
            ->assertHasNoTableActionErrors();

        $fresh = $order->fresh();
        $this->assertSame(MaintenanceWorkOrderStatus::AWAITING_VALIDATION, $fresh->status);
        $this->assertSame('Reseated the connector and verified continuity.', $fresh->solution_applied);
        $this->assertSame($user->id, $fresh->completed_by_user_id);
    }

    // CLA-581: a foreign technician's order isn't just hidden from the table — it's
    // outright unresolvable as a table action target, because the table's own query
    // already excludes it (Filament can't mount an action on a record outside the
    // base query). assertOwnRecord()'s explicit re-check is a second, independent
    // layer (same posture as CLA-500/CLA-501 this session) that never even gets
    // reached in this particular scenario, since resolution fails first.
    public function test_technician_cannot_start_another_technicians_order(): void
    {
        [$user] = $this->technician();
        $otherEmployee = $this->makeEmployee();
        $order = FoMaintenanceWorkOrder::factory()->create([
            'assigned_employee_id' => $otherEmployee->id,
            'status' => MaintenanceWorkOrderStatus::PLANNED,
        ]);

        $this->actingAs($user);

        $this->expectException(\Filament\Actions\Exceptions\ActionNotResolvableException::class);

        Livewire::test(MyWorkOrders::class)
            ->callTableAction('start', $order);
    }

    public function test_technician_cannot_reach_admin_fieldops_resources(): void
    {
        [$user] = $this->technician();
        $this->actingAs($user);

        $this->get(ComplexResource::getUrl('index'))->assertForbidden();
    }

    public function test_project_manager_still_has_no_panel_access_after_technician_change(): void
    {
        $user = UserFactory::new()->create();
        $user->assignRole('project_manager');

        $this->assertFalse($user->hasPanelAccess());
    }
}
