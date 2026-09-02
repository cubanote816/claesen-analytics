<?php

declare(strict_types=1);

namespace Modules\FieldOps\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\Cafca\Models\Employee;
use Modules\Core\Models\User;
use Modules\FieldOps\Enums\MaintenanceWorkOrderStatus;
use Modules\FieldOps\Filament\Resources\ComplexResource;
use Modules\FieldOps\Filament\Resources\ElectricalBoardResource;
use Modules\FieldOps\Filament\Resources\FoMaintenanceWorkOrderResource;
use Modules\FieldOps\Filament\Resources\MaintenanceWorkOrders\Pages\CreateMaintenanceWorkOrder;
use Modules\FieldOps\Filament\Resources\MaintenanceWorkOrders\Pages\EditMaintenanceWorkOrder;
use Modules\FieldOps\Models\Complex;
use Modules\FieldOps\Models\ElectricalBoard;
use Modules\FieldOps\Models\FoClient;
use Modules\FieldOps\Models\FoMaintenancePlan;
use Modules\FieldOps\Models\FoMaintenanceType;
use Modules\FieldOps\Models\FoMaintenanceWorkOrder;
use Modules\FieldOps\Models\Luminaire;
use Modules\FieldOps\Models\LuminaireFrame;
use Modules\FieldOps\Models\Structure;
use Modules\FieldOps\Models\Terrain;
use Modules\Intelligence\Services\GeminiService;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MaintenanceWorkOrderFilamentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mock(GeminiService::class, fn ($mock) => $mock->shouldReceive('translateAndDetect')->andReturn(['translations' => [], 'detected_locale' => 'nl']));
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    }

    public function test_global_queue_and_contextual_creation_render(): void
    {
        $user = User::factory()->create();
        $user->assignRole('super_admin');
        $luminaire = $this->luminaireWithClientContext();
        $order = FoMaintenanceWorkOrder::factory()->forMaintainable($luminaire)->create([
            'status' => MaintenanceWorkOrderStatus::AWAITING_VALIDATION,
        ]);
        $this->actingAs($user);

        $this->withHeader('Accept-Language', 'en-US')
            ->get(FoMaintenanceWorkOrderResource::getUrl('index'))
            ->assertOk()
            ->assertSee('Work orders')
            ->assertDontSee('New work order');

        $this->withHeader('Accept-Language', 'en-US')
            ->get(FoMaintenanceWorkOrderResource::getUrl('create', [
                'maintainable_type' => Luminaire::class,
                'maintainable_id' => $luminaire->id,
            ]))->assertOk()->assertSee($luminaire->serial_number);

        $this->withHeader('Accept-Language', 'en-US')
            ->get(FoMaintenanceWorkOrderResource::getUrl('view', ['record' => $order]))
            ->assertOk()
            ->assertSee('Return for correction')
            ->assertSee('Operational timeline');
        $this->get('/fo-maintenance-plans')->assertOk();
    }

    public function test_luminaire_page_links_to_contextual_work_order_instead_of_direct_history_creation(): void
    {
        $user = User::factory()->create();
        $user->assignRole('super_admin');
        $luminaire = $this->luminaireWithClientContext();
        $order = FoMaintenanceWorkOrder::factory()->forMaintainable($luminaire)->create();
        $this->actingAs($user);

        $expected = FoMaintenanceWorkOrderResource::getUrl('create', [
            'maintainable_type' => Luminaire::class,
            'maintainable_id' => $luminaire->id,
        ]);

        $this->withHeader('Accept-Language', 'en-US')
            ->get("/luminaires/{$luminaire->id}")
            ->assertOk()
            ->assertSee('Schedule maintenance')
            ->assertSee('Open work orders')
            ->assertSee($expected)
            ->assertSee(FoMaintenanceWorkOrderResource::getUrl('view', ['record' => $order]), false);
    }

    public function test_contextual_filament_form_creates_one_order_and_recurring_plan(): void
    {
        $user = User::factory()->create();
        $user->assignRole('super_admin');
        $luminaire = $this->luminaireWithClientContext();
        $type = FoMaintenanceType::factory()->preventive()->create();
        $this->actingAs($user);

        Livewire::withQueryParams([
            'maintainable_type' => Luminaire::class,
            'maintainable_id' => $luminaire->id,
        ])->test(CreateMaintenanceWorkOrder::class)
            ->fillForm([
                'fo_maintenance_type_id' => $type->id,
                'scheduled_for' => now()->addWeek()->startOfHour(),
                'priority' => 'medium',
                'instructions' => 'Inspect optics and clean the luminaire.',
                'recurrence_unit' => 'months',
                'recurrence_interval' => 6,
            ])->call('create')->assertHasNoFormErrors();

        $order = FoMaintenanceWorkOrder::firstOrFail();
        self::assertSame($luminaire->id, $order->maintainable_id);
        self::assertSame($luminaire->luminaire_position_id, $order->luminaire_position_id);
        self::assertSame(1, FoMaintenancePlan::count());
        self::assertSame(FoMaintenancePlan::firstOrFail()->id, $order->maintenance_plan_id);
        $this->get('/fo-maintenance-plans')->assertOk()->assertSee('6 month(s)');
    }

    public function test_recurrence_interval_without_unit_fails_validation_and_creates_no_plan(): void
    {
        $user = User::factory()->create();
        $user->assignRole('super_admin');
        $luminaire = $this->luminaireWithClientContext();
        $type = FoMaintenanceType::factory()->preventive()->create();
        $this->actingAs($user);

        Livewire::withQueryParams([
            'maintainable_type' => Luminaire::class,
            'maintainable_id' => $luminaire->id,
        ])->test(CreateMaintenanceWorkOrder::class)
            ->fillForm([
                'fo_maintenance_type_id' => $type->id,
                'scheduled_for' => now()->addWeek()->startOfHour(),
                'priority' => 'medium',
                'instructions' => 'Inspect optics and clean the luminaire.',
                'recurrence_interval' => 3,
            ])->call('create')->assertHasFormErrors(['recurrence_unit' => 'required_with']);

        self::assertSame(0, FoMaintenanceWorkOrder::count());
        self::assertSame(0, FoMaintenancePlan::count());
    }

    public function test_recurrence_unit_without_interval_fails_validation_and_creates_no_plan(): void
    {
        $user = User::factory()->create();
        $user->assignRole('super_admin');
        $luminaire = $this->luminaireWithClientContext();
        $type = FoMaintenanceType::factory()->preventive()->create();
        $this->actingAs($user);

        Livewire::withQueryParams([
            'maintainable_type' => Luminaire::class,
            'maintainable_id' => $luminaire->id,
        ])->test(CreateMaintenanceWorkOrder::class)
            ->fillForm([
                'fo_maintenance_type_id' => $type->id,
                'scheduled_for' => now()->addWeek()->startOfHour(),
                'priority' => 'medium',
                'instructions' => 'Inspect optics and clean the luminaire.',
                'recurrence_unit' => 'months',
            ])->call('create')->assertHasFormErrors(['recurrence_interval' => 'required_with']);

        self::assertSame(0, FoMaintenanceWorkOrder::count());
        self::assertSame(0, FoMaintenancePlan::count());
    }

    public function test_recurrence_interval_has_no_misleading_default_value(): void
    {
        $user = User::factory()->create();
        $user->assignRole('super_admin');
        $luminaire = $this->luminaireWithClientContext();
        $this->actingAs($user);

        Livewire::withQueryParams([
            'maintainable_type' => Luminaire::class,
            'maintainable_id' => $luminaire->id,
        ])->test(CreateMaintenanceWorkOrder::class)
            ->assertFormSet(['recurrence_interval' => null, 'recurrence_unit' => null]);
    }

    public function test_creating_work_order_without_any_recurrence_fields_still_works(): void
    {
        $user = User::factory()->create();
        $user->assignRole('super_admin');
        $luminaire = $this->luminaireWithClientContext();
        $type = FoMaintenanceType::factory()->preventive()->create();
        $this->actingAs($user);

        Livewire::withQueryParams([
            'maintainable_type' => Luminaire::class,
            'maintainable_id' => $luminaire->id,
        ])->test(CreateMaintenanceWorkOrder::class)
            ->fillForm([
                'fo_maintenance_type_id' => $type->id,
                'scheduled_for' => now()->addWeek()->startOfHour(),
                'priority' => 'medium',
                'instructions' => 'One-off inspection, no recurrence.',
            ])->call('create')->assertHasNoFormErrors();

        self::assertSame(1, FoMaintenanceWorkOrder::count());
        self::assertSame(0, FoMaintenancePlan::count());
    }

    public function test_create_work_order_breadcrumb_reflects_luminaire_hierarchy(): void
    {
        $user = User::factory()->create();
        $user->assignRole('super_admin');
        $luminaire = $this->luminaireWithClientContext();
        $this->actingAs($user);

        $this->get(FoMaintenanceWorkOrderResource::getUrl('create', [
            'maintainable_type' => Luminaire::class,
            'maintainable_id' => $luminaire->id,
        ]))
            ->assertOk()
            ->assertSee('href="'.ComplexResource::getUrl().'"', false)
            ->assertSee((string) $luminaire->serial_number)
            ->assertSee('Work order');
    }

    public function test_view_work_order_breadcrumb_reflects_luminaire_hierarchy(): void
    {
        $user = User::factory()->create();
        $user->assignRole('super_admin');
        $luminaire = $this->luminaireWithClientContext();
        $order = FoMaintenanceWorkOrder::factory()->forMaintainable($luminaire)->create();
        $this->actingAs($user);

        $this->get(FoMaintenanceWorkOrderResource::getUrl('view', ['record' => $order]))
            ->assertOk()
            ->assertSee('href="'.ComplexResource::getUrl().'"', false)
            ->assertSee((string) $luminaire->serial_number);
    }

    public function test_electrical_board_work_order_breadcrumb_shows_board_context(): void
    {
        $user = User::factory()->create();
        $user->assignRole('super_admin');
        $board = ElectricalBoard::factory()->create();
        $order = FoMaintenanceWorkOrder::factory()->forMaintainable($board)->create();
        $this->actingAs($user);

        $html = $this->get(FoMaintenanceWorkOrderResource::getUrl('view', ['record' => $order]))
            ->assertOk()
            ->assertSee('href="'.ElectricalBoardResource::getUrl('view', ['record' => $board]).'"', false)
            ->getContent();

        // The M:N Complex/Terrain/Structure chain doesn't apply to ElectricalBoard
        // (it can belong to several of each) — its own index stays the anchor,
        // not a hierarchy trail like Luminaire gets. Scoped to the breadcrumb nav
        // itself (marked by the fi-breadcrumbs class), not the whole page —
        // "Complexes" is also a normal sidebar item present on every panel page
        // regardless of this resource's breadcrumb.
        $breadcrumbsPos = strpos($html, 'fi-breadcrumbs');
        $this->assertNotFalse($breadcrumbsPos);
        $breadcrumbNav = substr($html, $breadcrumbsPos, 4000);
        $this->assertStringNotContainsString('href="'.ComplexResource::getUrl().'"', $breadcrumbNav);
    }

    public function test_create_work_order_breadcrumb_reflects_electrical_board_via_context(): void
    {
        $user = User::factory()->create();
        $user->assignRole('super_admin');
        $complex = Complex::factory()->create(['client_id' => FoClient::factory()->create()->id]);
        $board = ElectricalBoard::factory()->create();
        $board->complexes()->attach($complex->id);
        $this->actingAs($user);

        $this->get(FoMaintenanceWorkOrderResource::getUrl('create', [
            'maintainable_type' => ElectricalBoard::class,
            'maintainable_id' => $board->id,
            'via_complex' => $complex->id,
        ]))
            ->assertOk()
            ->assertSee('href="'.ComplexResource::getUrl('view', ['record' => $complex]).'"', false);
    }

    public function test_editing_work_order_assignee_with_numeric_employee_id_does_not_throw(): void
    {
        // Regression for CLA-339: employees.id is a string column, but legacy
        // ERP values like "100" are numeric-looking. PHP auto-casts numeric
        // string array keys to int, so a Select built via pluck('name', 'id')
        // silently returns an int and breaks MaintenanceWorkOrderService's
        // strict_types(?string $employeeId) signature.
        $user = User::factory()->create();
        $user->assignRole('super_admin');
        $luminaire = $this->luminaireWithClientContext();
        $order = FoMaintenanceWorkOrder::factory()->forMaintainable($luminaire)->create([
            'assigned_employee_id' => null,
        ]);
        $employee = Employee::create(['id' => '100', 'name' => 'Numeric Id Worker', 'fl_active' => true]);
        User::factory()->create(['employee_id' => $employee->id, 'is_active' => true]);
        $this->actingAs($user);

        Livewire::test(EditMaintenanceWorkOrder::class, ['record' => $order->getKey()])
            ->fillForm(['assigned_employee_id' => $employee->id])
            ->call('save')
            ->assertHasNoFormErrors();

        self::assertSame($employee->id, $order->refresh()->assigned_employee_id);
    }

    public function test_backoffice_can_review_and_correct_field_report_before_validating(): void
    {
        // CLA-374: the backoffice can correct/complete what the field worker submitted
        // (root_cause/solution_applied/completion_notes/completion_details) while the order
        // sits awaiting_validation — before validating/closing it.
        $user = User::factory()->create();
        $user->assignRole('super_admin');
        $luminaire = $this->luminaireWithClientContext();
        $order = FoMaintenanceWorkOrder::factory()->forMaintainable($luminaire)->create([
            'status' => MaintenanceWorkOrderStatus::AWAITING_VALIDATION,
            'root_cause' => 'Original root cause',
            'solution_applied' => 'Original solution',
            'completion_notes' => 'Original notes',
            'completion_details' => ['inspection' => true],
        ]);
        $this->actingAs($user);

        Livewire::test(EditMaintenanceWorkOrder::class, ['record' => $order->getKey()])
            ->fillForm([
                'root_cause' => 'Corrected root cause',
                'solution_applied' => 'Corrected solution',
                'completion_notes' => 'Corrected notes',
                'completion_details' => ['inspection' => false, 'cleaning' => true, 'otherTasks' => 'Replaced a bolt'],
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $order->refresh();
        self::assertSame('Corrected root cause', $order->root_cause);
        self::assertSame('Corrected solution', $order->solution_applied);
        self::assertSame('Corrected notes', $order->completion_notes);
        self::assertFalse($order->completion_details['inspection']);
        self::assertTrue($order->completion_details['cleaning']);
        self::assertSame('Replaced a bolt', $order->completion_details['otherTasks']);
        self::assertSame(
            ['reviewed'],
            $order->events()->latest('id')->limit(1)->pluck('event_type')->map(fn ($type) => $type->value)->all(),
        );
    }

    public function test_view_page_renders_completion_details_with_multiple_tasks_marked(): void
    {
        // Regression for CLA-376: Filament's TextEntry fragments an array state into
        // one formatStateUsing() call per value once count($state) > 1 (here 2 keys),
        // so the closure received a bare `true`/`null` instead of the full array and
        // broke its `?array $state` typehint. Fixed via ->state() reading the record
        // directly instead of relying on Filament to hand over the array intact.
        $user = User::factory()->create();
        $user->assignRole('super_admin');
        $luminaire = $this->luminaireWithClientContext();
        $order = FoMaintenanceWorkOrder::factory()->forMaintainable($luminaire)->create([
            'completion_details' => ['inspection' => true, 'otherTasks' => null],
        ]);
        $this->actingAs($user);

        $this->withHeader('Accept-Language', 'en-US')
            ->get(FoMaintenanceWorkOrderResource::getUrl('view', ['record' => $order]))
            ->assertOk()
            ->assertSee(__('fieldops::resource.work_orders.tasks.inspection', [], 'en'));
    }

    public function test_edit_page_locks_planning_fields_when_awaiting_validation(): void
    {
        // The planning Section is disabled+dehydrated(false) once awaiting_validation — a
        // fillForm() attempt at those fields should have no effect, and updateReview()'s
        // own whitelist is the second line of defense even if the UI lock were bypassed.
        $user = User::factory()->create();
        $user->assignRole('super_admin');
        $luminaire = $this->luminaireWithClientContext();
        $type = FoMaintenanceType::factory()->create();
        $order = FoMaintenanceWorkOrder::factory()->forMaintainable($luminaire)->create([
            'status' => MaintenanceWorkOrderStatus::AWAITING_VALIDATION,
            'fo_maintenance_type_id' => $type->id,
            'priority' => 'medium',
        ]);
        $otherType = FoMaintenanceType::factory()->create();
        $this->actingAs($user);

        Livewire::test(EditMaintenanceWorkOrder::class, ['record' => $order->getKey()])
            ->fillForm([
                'fo_maintenance_type_id' => $otherType->id,
                'priority' => 'urgent',
                'solution_applied' => 'Solution to satisfy the required field',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $order->refresh();
        self::assertSame($type->id, $order->fo_maintenance_type_id);
        self::assertSame('medium', $order->priority);
    }

    public function test_cannot_edit_in_progress_or_completed_or_cancelled_orders(): void
    {
        $luminaire = $this->luminaireWithClientContext();
        $type = FoMaintenanceType::factory()->create();
        foreach ([MaintenanceWorkOrderStatus::IN_PROGRESS, MaintenanceWorkOrderStatus::COMPLETED, MaintenanceWorkOrderStatus::CANCELLED] as $status) {
            $order = FoMaintenanceWorkOrder::factory()->forMaintainable($luminaire)->create([
                'fo_maintenance_type_id' => $type->id,
                'status' => $status,
            ]);
            self::assertFalse(FoMaintenanceWorkOrderResource::canEdit($order), "Expected canEdit() to be false for status {$status->value}");
        }
    }

    public function test_service_rejects_review_on_a_non_awaiting_validation_order(): void
    {
        $luminaire = $this->luminaireWithClientContext();
        $order = FoMaintenanceWorkOrder::factory()->forMaintainable($luminaire)->create([
            'status' => MaintenanceWorkOrderStatus::PLANNED,
        ]);

        $this->expectException(\Illuminate\Validation\ValidationException::class);

        app(\Modules\FieldOps\Services\MaintenanceWorkOrderService::class)->updateReview(
            $order,
            ['solution_applied' => 'Should not be reachable'],
            1,
        );
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
}
