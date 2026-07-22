<?php

declare(strict_types=1);

namespace Modules\FieldOps\Tests\Feature;

use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Modules\Cafca\Models\Employee;
use Modules\FieldOps\Enums\MaintenanceWorkOrderStatus;
use Modules\FieldOps\Models\Complex;
use Modules\FieldOps\Models\ElectricalBoard;
use Modules\FieldOps\Models\FoClient;
use Modules\FieldOps\Models\FoMaintenancePlan;
use Modules\FieldOps\Models\FoMaintenanceRecord;
use Modules\FieldOps\Models\FoMaintenanceType;
use Modules\FieldOps\Models\FoMaintenanceWorkOrder;
use Modules\FieldOps\Models\Luminaire;
use Modules\FieldOps\Models\LuminaireFrame;
use Modules\FieldOps\Models\Structure;
use Modules\FieldOps\Models\Terrain;
use Modules\FieldOps\Services\MaintenanceWorkOrderService;
use Modules\Intelligence\Services\GeminiService;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MaintenanceWorkOrderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mock(GeminiService::class, fn ($mock) => $mock->shouldReceive('translateAndDetect')->andReturn(['translations' => [], 'detected_locale' => 'nl']));
        foreach (['super_admin', 'admin', 'project_manager'] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }
    }

    public function test_contextual_order_keeps_equipment_position_and_client_immutable(): void
    {
        [$luminaire, $client] = $this->luminaireWithClientContext();
        $admin = UserFactory::new()->create();
        $admin->assignRole('admin');
        $token = $admin->createToken('test')->plainTextToken;
        $type = FoMaintenanceType::factory()->corrective()->create();

        $response = $this->withToken($token)->postJson("/api/v1/fieldops/luminaires/{$luminaire->id}/maintenance-work-orders", [
            'fo_maintenance_type_id' => $type->id,
            'scheduled_for' => '2026-07-24 08:00:00',
            'priority' => 'high',
            'problem_description' => 'Intermittent light output',
            'client_id' => 999999,
            'luminaire_position_id' => 999999,
        ])->assertCreated();

        $order = FoMaintenanceWorkOrder::findOrFail($response->json('data.id'));
        self::assertSame($client->id, $order->client_id);
        self::assertSame($luminaire->luminaire_position_id, $order->luminaire_position_id);
        self::assertSame($luminaire->id, $order->maintainable_id);
        self::assertSame(Luminaire::class, $order->maintainable_type);
    }

    public function test_field_submission_requires_assignment_and_backoffice_validation_creates_history(): void
    {
        [$luminaire] = $this->luminaireWithClientContext();
        $employee = Employee::create(['id' => 'FIELD-001', 'name' => 'Field Worker', 'fl_active' => true]);
        $worker = UserFactory::new()->create(['employee_id' => $employee->id]);
        $worker->assignRole('project_manager');
        $admin = UserFactory::new()->create();
        $admin->assignRole('admin');
        $type = FoMaintenanceType::factory()->corrective()->create();
        $order = app(MaintenanceWorkOrderService::class)->create([
            'maintainable_type' => Luminaire::class,
            'maintainable_id' => $luminaire->id,
            'fo_maintenance_type_id' => $type->id,
            'assigned_employee_id' => $employee->id,
            'scheduled_for' => now(),
            'priority' => 'high',
            'problem_description' => 'Driver failure',
        ], $admin->id);

        $workerToken = $worker->createToken('field')->plainTextToken;
        $this->withToken($workerToken)
            ->postJson("/api/v1/fieldops/maintenance-work-orders/{$order->id}/start")
            ->assertOk()->assertJsonPath('data.status', 'in_progress');

        $this->withToken($workerToken)
            ->postJson("/api/v1/fieldops/maintenance-work-orders/{$order->id}/submit", [
                'solution_applied' => 'Driver replaced and output tested.',
                'root_cause' => 'Water ingress',
                'completion_notes' => 'Photographs captured in the field app.',
            ])->assertOk()->assertJsonPath('data.status', 'awaiting_validation');

        self::assertSame(0, FoMaintenanceRecord::count());

        $adminToken = $admin->createToken('backoffice')->plainTextToken;
        Auth::forgetGuards();
        $this->withToken($adminToken)
            ->postJson("/api/v1/fieldops/maintenance-work-orders/{$order->id}/validate")
            ->assertOk()
            ->assertJsonPath('data.status', 'completed');

        $order->refresh();
        self::assertNotNull($order->maintenance_record_id);
        $this->assertDatabaseHas('fo_maintenance_records', [
            'id' => $order->maintenance_record_id,
            'maintainable_id' => $luminaire->id,
            'luminaire_position_id' => $luminaire->luminaire_position_id,
            'solution_applied' => 'Driver replaced and output tested.',
        ]);
    }

    public function test_electrical_board_order_derives_client_from_its_site(): void
    {
        $client = FoClient::factory()->create();
        $complex = Complex::factory()->create(['client_id' => $client->id]);
        $board = ElectricalBoard::factory()->create();
        $board->complexes()->attach($complex);
        $type = FoMaintenanceType::factory()->preventive()->create();

        $order = app(MaintenanceWorkOrderService::class)->create([
            'maintainable_type' => ElectricalBoard::class,
            'maintainable_id' => $board->id,
            'fo_maintenance_type_id' => $type->id,
            'scheduled_for' => now()->addDay(),
            'priority' => 'medium',
        ], null);

        self::assertSame($client->id, $order->client_id);
        self::assertNull($order->luminaire_position_id);
    }

    public function test_other_field_worker_cannot_execute_an_assigned_order(): void
    {
        $luminaire = Luminaire::factory()->create();
        $assigned = Employee::create(['id' => 'FIELD-ASSIGNED', 'name' => 'Assigned', 'fl_active' => true]);
        $other = Employee::create(['id' => 'FIELD-OTHER', 'name' => 'Other', 'fl_active' => true]);
        $user = UserFactory::new()->create(['employee_id' => $other->id]);
        $user->assignRole('project_manager');
        $order = FoMaintenanceWorkOrder::factory()->forMaintainable($luminaire)->create([
            'assigned_employee_id' => $assigned->id,
            'status' => MaintenanceWorkOrderStatus::ASSIGNED,
        ]);

        $this->withToken($user->createToken('field')->plainTextToken)
            ->postJson("/api/v1/fieldops/maintenance-work-orders/{$order->id}/start")
            ->assertForbidden();
    }

    public function test_assigned_queue_only_returns_the_workers_orders_with_equipment_context(): void
    {
        [$luminaire, $client] = $this->luminaireWithClientContext();
        $luminaire->luminaireType()->update(['product_family' => 'OptiVision LED']);
        $assigned = Employee::create(['id' => 'FIELD-QUEUE', 'name' => 'Assigned worker', 'fl_active' => true]);
        $other = Employee::create(['id' => 'FIELD-OTHER-QUEUE', 'name' => 'Other worker', 'fl_active' => true]);
        $user = UserFactory::new()->create(['employee_id' => $assigned->id]);
        $user->assignRole('project_manager');
        $type = FoMaintenanceType::factory()->corrective()->create();

        $ownOrder = FoMaintenanceWorkOrder::factory()->forMaintainable($luminaire)->create([
            'fo_maintenance_type_id' => $type->id,
            'client_id' => $client->id,
            'assigned_employee_id' => $assigned->id,
            'status' => MaintenanceWorkOrderStatus::ASSIGNED,
        ]);
        FoMaintenanceWorkOrder::factory()->forMaintainable($luminaire)->create([
            'fo_maintenance_type_id' => $type->id,
            'assigned_employee_id' => $other->id,
            'status' => MaintenanceWorkOrderStatus::ASSIGNED,
        ]);

        $this->withToken($user->createToken('field')->plainTextToken)
            ->getJson('/api/v1/fieldops/maintenance-work-orders/assigned')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $ownOrder->id)
            ->assertJsonPath('data.0.equipment.kind', 'luminaire')
            ->assertJsonPath('data.0.equipment.serial_number', $luminaire->serial_number)
            ->assertJsonPath('data.0.client.id', $client->id);
    }

    public function test_work_order_queue_requires_authentication_and_submission_requires_solution(): void
    {
        $this->getJson('/api/v1/fieldops/maintenance-work-orders/assigned')->assertUnauthorized();

        $luminaire = Luminaire::factory()->create();
        $employee = Employee::create(['id' => 'FIELD-VALIDATION', 'name' => 'Field worker', 'fl_active' => true]);
        $worker = UserFactory::new()->create(['employee_id' => $employee->id]);
        $worker->assignRole('project_manager');
        $order = FoMaintenanceWorkOrder::factory()->forMaintainable($luminaire)->create([
            'assigned_employee_id' => $employee->id,
            'status' => MaintenanceWorkOrderStatus::IN_PROGRESS,
            'started_at' => now()->subHour(),
        ]);

        $this->withToken($worker->createToken('field')->plainTextToken)
            ->postJson("/api/v1/fieldops/maintenance-work-orders/{$order->id}/submit", [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('solution_applied');
    }

    public function test_exceptional_backoffice_closure_requires_and_stores_reason(): void
    {
        $admin = UserFactory::new()->create();
        $admin->assignRole('admin');
        $order = FoMaintenanceWorkOrder::factory()->create(['status' => MaintenanceWorkOrderStatus::PLANNED]);
        $token = $admin->createToken('backoffice')->plainTextToken;

        $this->withToken($token)
            ->postJson("/api/v1/fieldops/maintenance-work-orders/{$order->id}/override")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('override_reason');

        $this->withToken($token)
            ->postJson("/api/v1/fieldops/maintenance-work-orders/{$order->id}/override", [
                'override_reason' => 'Paper field report verified after tablet failure.',
            ])->assertOk()->assertJsonPath('data.status', 'completed');

        $order->refresh();
        self::assertSame('Paper field report verified after tablet failure.', $order->override_reason);
        self::assertTrue((bool) $order->maintenanceRecord->details['backoffice_override']);
        self::assertSame($order->override_reason, $order->maintenanceRecord->details['override_reason']);
    }

    public function test_recurring_order_creates_plan_and_due_generator_is_idempotent_per_cycle(): void
    {
        $luminaire = Luminaire::factory()->create();
        $type = FoMaintenanceType::factory()->preventive()->create();
        $order = app(MaintenanceWorkOrderService::class)->create([
            'maintainable_type' => Luminaire::class,
            'maintainable_id' => $luminaire->id,
            'fo_maintenance_type_id' => $type->id,
            'scheduled_for' => now()->subMonths(2),
            'priority' => 'medium',
            'recurrence_unit' => 'months',
            'recurrence_interval' => 1,
        ], null);

        self::assertNotNull($order->maintenance_plan_id);
        $plan = FoMaintenancePlan::findOrFail($order->maintenance_plan_id);
        $plan->update(['next_due_at' => now()->subMinute()]);

        $this->artisan('fieldops:generate-maintenance-work-orders', ['--dry-run' => true])
            ->expectsOutput('1 maintenance plan(s) are due. No work orders were created.')
            ->assertSuccessful();
        self::assertSame(1, $plan->workOrders()->count());

        self::assertSame(1, app(MaintenanceWorkOrderService::class)->generateDueOrders());
        self::assertSame(2, $plan->workOrders()->count());
        self::assertSame(0, app(MaintenanceWorkOrderService::class)->generateDueOrders());
    }

    private function luminaireWithClientContext(): array
    {
        $client = FoClient::factory()->create();
        $complex = Complex::factory()->create(['client_id' => $client->id]);
        $terrain = Terrain::factory()->create(['complex_id' => $complex->id]);
        $structure = Structure::factory()->create();
        $structure->terrains()->attach($terrain);
        $frame = LuminaireFrame::factory()->create();
        $frame->structures()->attach($structure);
        $luminaire = Luminaire::factory()->create(['luminaire_frame_id' => $frame->id]);

        return [$luminaire, $client];
    }
}
