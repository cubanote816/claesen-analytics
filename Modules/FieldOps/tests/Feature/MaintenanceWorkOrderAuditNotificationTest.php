<?php

declare(strict_types=1);

namespace Modules\FieldOps\Tests\Feature;

use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use LogicException;
use Modules\Cafca\Models\Employee;
use Modules\FieldOps\Enums\MaintenanceWorkOrderEventType;
use Modules\FieldOps\Enums\MaintenanceWorkOrderStatus;
use Modules\FieldOps\Models\Complex;
use Modules\FieldOps\Models\FoClient;
use Modules\FieldOps\Models\FoMaintenanceType;
use Modules\FieldOps\Models\FoMaintenanceWorkOrder;
use Modules\FieldOps\Models\Luminaire;
use Modules\FieldOps\Models\LuminaireFrame;
use Modules\FieldOps\Models\Structure;
use Modules\FieldOps\Models\Terrain;
use Modules\FieldOps\Notifications\WorkOrderOperationalNotification;
use Modules\FieldOps\Services\MaintenanceWorkOrderService;
use Modules\Intelligence\Services\GeminiService;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MaintenanceWorkOrderAuditNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mock(GeminiService::class, fn ($mock) => $mock->shouldReceive('translateAndDetect')->andReturn(['translations' => [], 'detected_locale' => 'nl']));
        foreach (['admin', 'project_manager'] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }
    }

    public function test_assignment_requires_active_app_user_and_records_the_assigner(): void
    {
        Notification::fake();
        $luminaire = $this->luminaireWithClientContext();
        $employee = Employee::create(['id' => 'AUDIT-001', 'name' => 'Field Worker', 'fl_active' => true]);
        $admin = UserFactory::new()->create();
        $admin->assignRole('admin');
        $type = FoMaintenanceType::factory()->corrective()->create();

        try {
            $this->createOrder($luminaire, $type, $admin->id, $employee->id);
            self::fail('Assignment without an active application user should fail.');
        } catch (ValidationException $exception) {
            self::assertArrayHasKey('assigned_employee_id', $exception->errors());
        }

        $worker = UserFactory::new()->create(['employee_id' => $employee->id, 'is_active' => true]);
        $worker->assignRole('project_manager');
        $order = $this->createOrder($luminaire, $type, $admin->id, $employee->id);

        self::assertSame($admin->id, $order->assigned_by_user_id);
        self::assertNotNull($order->assigned_at);
        self::assertSame(
            [MaintenanceWorkOrderEventType::CREATED, MaintenanceWorkOrderEventType::ASSIGNED],
            $order->events()->get()->pluck('event_type')->all(),
        );
        Notification::assertSentTo($worker, WorkOrderOperationalNotification::class);
    }

    public function test_return_for_correction_is_audited_and_notifies_the_worker(): void
    {
        Notification::fake();
        $luminaire = $this->luminaireWithClientContext();
        $employee = Employee::create(['id' => 'AUDIT-002', 'name' => 'Field Worker', 'fl_active' => true]);
        $worker = UserFactory::new()->create(['employee_id' => $employee->id]);
        $worker->assignRole('project_manager');
        $admin = UserFactory::new()->create();
        $admin->assignRole('admin');
        $order = $this->createOrder(
            $luminaire,
            FoMaintenanceType::factory()->corrective()->create(),
            $admin->id,
            $employee->id,
        );
        $service = app(MaintenanceWorkOrderService::class);

        $service->start($order, $worker->id);
        $service->submit($order, ['solution_applied' => 'Driver replaced'], $worker->id);
        $returned = $service->returnForCorrection($order, $admin->id, 'Add a final output measurement.');

        self::assertSame(MaintenanceWorkOrderStatus::IN_PROGRESS, $returned->status);
        self::assertSame('Add a final output measurement.', $returned->return_reason);
        self::assertSame($admin->id, $returned->returned_by_user_id);
        self::assertSame(MaintenanceWorkOrderEventType::RETURNED, $returned->events()->reorder('id', 'desc')->firstOrFail()->event_type);
        Notification::assertSentToTimes($worker, WorkOrderOperationalNotification::class, 2);

        $this->actingAs($admin)
            ->postJson("/api/v1/fieldops/maintenance-work-orders/{$order->id}/return", [
                'return_reason' => 'Cannot return twice.',
            ])
            ->assertUnprocessable();
    }

    public function test_reassignment_notifies_both_workers_and_preserves_the_audit_chain(): void
    {
        Notification::fake();
        $luminaire = $this->luminaireWithClientContext();
        $firstEmployee = Employee::create(['id' => 'AUDIT-FIRST', 'name' => 'First worker', 'fl_active' => true]);
        $secondEmployee = Employee::create(['id' => 'AUDIT-SECOND', 'name' => 'Second worker', 'fl_active' => true]);
        $firstWorker = UserFactory::new()->create(['employee_id' => $firstEmployee->id]);
        $secondWorker = UserFactory::new()->create(['employee_id' => $secondEmployee->id]);
        $admin = UserFactory::new()->create();
        $order = $this->createOrder(
            $luminaire,
            FoMaintenanceType::factory()->preventive()->create(),
            $admin->id,
            $firstEmployee->id,
        );

        Notification::fake();
        $updated = app(MaintenanceWorkOrderService::class)->updatePlanning($order, [
            'assigned_employee_id' => $secondEmployee->id,
            'fo_maintenance_type_id' => $order->fo_maintenance_type_id,
            'scheduled_for' => $order->scheduled_for,
            'priority' => $order->priority,
        ], $admin->id);

        self::assertSame($secondEmployee->id, $updated->assigned_employee_id);
        self::assertSame($admin->id, $updated->assigned_by_user_id);
        self::assertSame(MaintenanceWorkOrderEventType::REASSIGNED, $updated->events()->reorder('id', 'desc')->firstOrFail()->event_type);
        Notification::assertSentTo($firstWorker, WorkOrderOperationalNotification::class);
        Notification::assertSentTo($secondWorker, WorkOrderOperationalNotification::class);
    }

    public function test_notification_channels_honor_user_preferences(): void
    {
        $user = UserFactory::new()->create([
            'preferences_data' => ['notifications' => ['fieldopsDatabase' => false, 'fieldopsEmail' => true]],
        ]);
        $order = FoMaintenanceWorkOrder::factory()->create();
        $notification = new WorkOrderOperationalNotification($order, 'assigned', 'worker');

        self::assertSame(['mail'], $notification->via($user));

        $user->preferences_data = ['notifications' => ['fieldopsDatabase' => true, 'fieldopsEmail' => false]];
        self::assertSame(['database'], $notification->via($user));
    }

    public function test_notification_api_is_user_and_module_scoped(): void
    {
        $user = UserFactory::new()->create();
        $other = UserFactory::new()->create();
        $fieldOpsId = $this->databaseNotification($user, 'fieldops');
        $otherModuleId = $this->databaseNotification($user, 'safety');
        $otherUserId = $this->databaseNotification($other, 'fieldops');
        $token = $user->createToken('field')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/v1/fieldops/notifications')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $fieldOpsId);

        $this->withToken($token)
            ->postJson('/api/v1/fieldops/notifications/read-all')
            ->assertOk();

        self::assertNotNull($user->notifications()->findOrFail($fieldOpsId)->read_at);
        self::assertNull($user->notifications()->findOrFail($otherModuleId)->read_at);
        $this->withToken($token)
            ->postJson("/api/v1/fieldops/notifications/{$otherUserId}/read")
            ->assertNotFound();
    }

    public function test_work_order_events_are_append_only(): void
    {
        $order = FoMaintenanceWorkOrder::factory()->create();
        $event = $order->events()->create([
            'event_type' => MaintenanceWorkOrderEventType::CREATED,
            'occurred_at' => now(),
        ]);

        try {
            $event->update(['data' => ['changed' => true]]);
            self::fail('Updating an audit event should fail.');
        } catch (LogicException) {
            self::assertTrue(true);
        }

        try {
            $event->delete();
            self::fail('Deleting an audit event should fail.');
        } catch (LogicException) {
            self::assertTrue(true);
        }
    }

    private function createOrder(
        Luminaire $luminaire,
        FoMaintenanceType $type,
        int $adminId,
        ?string $employeeId,
    ): FoMaintenanceWorkOrder {
        return app(MaintenanceWorkOrderService::class)->create([
            'maintainable_type' => Luminaire::class,
            'maintainable_id' => $luminaire->id,
            'fo_maintenance_type_id' => $type->id,
            'assigned_employee_id' => $employeeId,
            'scheduled_for' => now(),
            'priority' => 'high',
        ], $adminId);
    }

    private function luminaireWithClientContext(): Luminaire
    {
        $client = FoClient::factory()->create();
        $complex = Complex::factory()->create(['client_id' => $client->id]);
        $terrain = Terrain::factory()->create(['complex_id' => $complex->id]);
        $structure = Structure::factory()->create();
        $structure->terrains()->attach($terrain);
        $frame = LuminaireFrame::factory()->create();
        $frame->structures()->attach($structure);

        return Luminaire::factory()->create(['luminaire_frame_id' => $frame->id]);
    }

    private function databaseNotification($user, string $module): string
    {
        $id = (string) Str::uuid();
        $user->notifications()->create([
            'id' => $id,
            'type' => WorkOrderOperationalNotification::class,
            'data' => ['title' => 'Test', 'viewData' => ['module' => $module]],
        ]);

        return $id;
    }
}
