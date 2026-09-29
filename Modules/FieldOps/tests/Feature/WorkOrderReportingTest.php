<?php

declare(strict_types=1);

namespace Modules\FieldOps\Tests\Feature;

use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Cafca\Models\Employee;
use Modules\Core\Models\User;
use Modules\FieldOps\Enums\MaintenanceWorkOrderStatus;
use Modules\FieldOps\Models\Complex;
use Modules\FieldOps\Models\FoClient;
use Modules\FieldOps\Models\FoMaintenanceType;
use Modules\FieldOps\Models\FoMaintenanceWorkOrder;
use Modules\FieldOps\Models\Luminaire;
use Modules\FieldOps\Models\LuminaireFrame;
use Modules\FieldOps\Models\Structure;
use Modules\FieldOps\Models\Terrain;
use Modules\Intelligence\Services\GeminiService;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * CLA-578 — filters, pagination, aggregates, export and the luminaire index.
 */
class WorkOrderReportingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mock(GeminiService::class, fn ($mock) => $mock->shouldReceive('translateAndDetect')->andReturn(['translations' => [], 'detected_locale' => 'nl']));

        foreach (['super_admin', 'admin', 'project_manager', 'technician'] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }
    }

    // ── history: contract and filters ────────────────────────────────────────

    public function test_history_without_filters_keeps_returning_a_plain_data_array(): void
    {
        [$luminaire] = $this->luminaireWithClientContext();
        $admin = $this->admin();

        $this->closedOrder($luminaire, now()->subDays(3));

        $response = $this->withToken($this->tokenFor($admin))
            ->getJson('/api/v1/fieldops/maintenance-work-orders/history')
            ->assertOk();

        self::assertTrue($response->json('success'));
        self::assertIsArray($response->json('data'));
        self::assertCount(1, $response->json('data'));
        // Pagination is additive: "meta" appears, "data" stays an array.
        self::assertSame(1, $response->json('meta.total'));
    }

    public function test_history_filters_closed_orders_by_close_date(): void
    {
        [$luminaire] = $this->luminaireWithClientContext();
        $admin = $this->admin();

        $recent = $this->closedOrder($luminaire, now()->subDays(2));
        $this->closedOrder($luminaire, now()->subDays(40));

        $response = $this->withToken($this->tokenFor($admin))
            ->getJson('/api/v1/fieldops/maintenance-work-orders/history?from='.now()->subDays(7)->toDateString())
            ->assertOk();

        self::assertCount(1, $response->json('data'));
        self::assertSame($recent->id, $response->json('data.0.id'));
    }

    public function test_history_filters_by_complex_through_the_equipment_chain(): void
    {
        [$luminaireA, , , , $complexA] = $this->luminaireWithClientContext();
        [$luminaireB] = $this->luminaireWithClientContext();
        $admin = $this->admin();

        $inComplexA = $this->closedOrder($luminaireA, now()->subDay());
        $this->closedOrder($luminaireB, now()->subDay());

        $response = $this->withToken($this->tokenFor($admin))
            ->getJson('/api/v1/fieldops/maintenance-work-orders/history?complex_id='.$complexA->id)
            ->assertOk();

        self::assertCount(1, $response->json('data'));
        self::assertSame($inComplexA->id, $response->json('data.0.id'));
    }

    public function test_history_rejects_an_inverted_date_range(): void
    {
        $admin = $this->admin();

        $this->withToken($this->tokenFor($admin))
            ->getJson('/api/v1/fieldops/maintenance-work-orders/history?from=2026-09-10&to=2026-09-01')
            ->assertStatus(422);
    }

    // ── access scope ─────────────────────────────────────────────────────────

    public function test_technician_without_client_pivots_still_sees_own_assigned_orders(): void
    {
        // Regression guard for the CLA-578 scoping decision: tenant scoping by
        // allowed client ids would leave this technician with an empty list.
        [$luminaire] = $this->luminaireWithClientContext();
        $employee = Employee::create(['id' => 'FIELD-501', 'name' => 'Field Worker', 'fl_active' => true]);
        $technician = UserFactory::new()->create(['employee_id' => $employee->id]);
        $technician->assignRole('technician');

        FoMaintenanceWorkOrder::factory()->forMaintainable($luminaire)->create([
            'fo_maintenance_type_id' => $this->typeId('preventive'),
            'assigned_employee_id' => $employee->id,
            'status' => MaintenanceWorkOrderStatus::ASSIGNED,
            'scheduled_for' => now()->addDay(),
        ]);

        $response = $this->withToken($this->tokenFor($technician))
            ->getJson('/api/v1/fieldops/maintenance-work-orders/assigned')
            ->assertOk();

        self::assertCount(1, $response->json('data'));
    }

    public function test_reporting_endpoints_never_expose_another_technicians_work(): void
    {
        [$luminaire] = $this->luminaireWithClientContext();

        $mine = Employee::create(['id' => 'FIELD-502', 'name' => 'Mine', 'fl_active' => true]);
        $theirs = Employee::create(['id' => 'FIELD-503', 'name' => 'Theirs', 'fl_active' => true]);
        $technician = UserFactory::new()->create(['employee_id' => $mine->id]);
        $technician->assignRole('technician');

        $this->closedOrder($luminaire, now()->subDay(), $mine->id);
        $this->closedOrder($luminaire, now()->subDay(), $theirs->id);
        $this->closedOrder($luminaire, now()->subDay(), $theirs->id);

        $token = $this->tokenFor($technician);

        $history = $this->withToken($token)->getJson('/api/v1/fieldops/maintenance-work-orders/history')->assertOk();
        self::assertCount(1, $history->json('data'));

        $stats = $this->withToken($token)->getJson('/api/v1/fieldops/maintenance-work-orders/stats')->assertOk();
        self::assertSame(1, $stats->json('data.totals.completed'));

        $csv = $this->withToken($token)->get('/api/v1/fieldops/maintenance-work-orders/export');
        $csv->assertOk();
        // Header row + exactly one data row.
        self::assertCount(2, array_filter(explode("\n", trim($csv->streamedContent()))));
    }

    // ── stats ────────────────────────────────────────────────────────────────

    public function test_stats_reports_totals_first_time_right_and_a_gapless_weekly_series(): void
    {
        [$luminaire] = $this->luminaireWithClientContext();
        $admin = $this->admin();

        $clean = $this->closedOrder($luminaire, now()->subDays(2));
        $clean->forceFill(['assigned_at' => now()->subDays(3)])->save();

        $returned = $this->closedOrder($luminaire, now()->subDays(9));
        $returned->forceFill(['assigned_at' => now()->subDays(11), 'returned_at' => now()->subDays(10)])->save();

        FoMaintenanceWorkOrder::factory()->forMaintainable($luminaire)->create([
            'fo_maintenance_type_id' => $this->typeId('preventive'),
            'status' => MaintenanceWorkOrderStatus::CANCELLED,
            'cancelled_at' => now()->subDays(4),
        ]);

        FoMaintenanceWorkOrder::factory()->forMaintainable($luminaire)->create([
            'fo_maintenance_type_id' => $this->typeId('preventive'),
            'status' => MaintenanceWorkOrderStatus::ASSIGNED,
            'scheduled_for' => now()->subDays(5),
            'due_at' => now()->subDay(),
        ]);

        $data = $this->withToken($this->tokenFor($admin))
            ->getJson('/api/v1/fieldops/maintenance-work-orders/stats?from='.now()->subDays(21)->toDateString())
            ->assertOk()
            ->json('data');

        self::assertSame(2, $data['totals']['completed']);
        self::assertSame(1, $data['totals']['cancelled']);
        self::assertSame(1, $data['totals']['open']);
        self::assertSame(1, $data['totals']['overdue']);
        self::assertSame(0.5, $data['first_time_right_rate']);
        self::assertNotNull($data['avg_lead_time_hours']);

        // Four calendar weeks of a 21-day window, empty ones included.
        self::assertGreaterThanOrEqual(3, count($data['completed_per_week']));
        self::assertSame(2, array_sum(array_column($data['completed_per_week'], 'count')));
    }

    public function test_stats_counts_open_work_regardless_of_the_reporting_window(): void
    {
        [$luminaire] = $this->luminaireWithClientContext();
        $admin = $this->admin();

        FoMaintenanceWorkOrder::factory()->forMaintainable($luminaire)->create([
            'fo_maintenance_type_id' => $this->typeId('preventive'),
            'status' => MaintenanceWorkOrderStatus::ASSIGNED,
            'scheduled_for' => now()->subYear(),
            'due_at' => now()->subYear()->addDay(),
        ]);

        $data = $this->withToken($this->tokenFor($admin))
            ->getJson('/api/v1/fieldops/maintenance-work-orders/stats?from='.now()->subDays(7)->toDateString())
            ->assertOk()
            ->json('data');

        self::assertSame(1, $data['totals']['open']);
        self::assertSame(1, $data['totals']['overdue']);
    }

    // ── export ───────────────────────────────────────────────────────────────

    public function test_export_streams_a_csv_attachment_with_one_row_per_order(): void
    {
        [$luminaire] = $this->luminaireWithClientContext();
        $admin = $this->admin();

        $this->closedOrder($luminaire, now()->subDay());
        $this->closedOrder($luminaire, now()->subDays(2));

        $response = $this->withToken($this->tokenFor($admin))
            ->get('/api/v1/fieldops/maintenance-work-orders/export');

        $response->assertOk();
        self::assertStringContainsString('text/csv', $response->headers->get('content-type'));
        self::assertStringContainsString('attachment', (string) $response->headers->get('content-disposition'));

        $rows = array_filter(explode("\n", trim($response->streamedContent())));
        self::assertCount(3, $rows);
        self::assertStringContainsString('work_order_id', $rows[0]);
    }

    // ── assigned ─────────────────────────────────────────────────────────────

    public function test_assigned_accepts_a_schedule_range_for_the_calendar(): void
    {
        [$luminaire] = $this->luminaireWithClientContext();
        $admin = $this->admin();

        $thisWeek = FoMaintenanceWorkOrder::factory()->forMaintainable($luminaire)->create([
            'fo_maintenance_type_id' => $this->typeId('preventive'),
            'status' => MaintenanceWorkOrderStatus::ASSIGNED,
            'scheduled_for' => now()->addDay(),
        ]);
        FoMaintenanceWorkOrder::factory()->forMaintainable($luminaire)->create([
            'fo_maintenance_type_id' => $this->typeId('preventive'),
            'status' => MaintenanceWorkOrderStatus::ASSIGNED,
            'scheduled_for' => now()->addMonths(2),
        ]);

        $response = $this->withToken($this->tokenFor($admin))
            ->getJson('/api/v1/fieldops/maintenance-work-orders/assigned?from='.now()->toDateString().'&to='.now()->addDays(7)->toDateString())
            ->assertOk();

        self::assertCount(1, $response->json('data'));
        self::assertSame($thisWeek->id, $response->json('data.0.id'));
    }

    // ── luminaire index ──────────────────────────────────────────────────────

    public function test_luminaire_index_lists_paginated_results_for_broad_access_users(): void
    {
        $this->luminaireWithClientContext();
        $this->luminaireWithClientContext();
        $admin = $this->admin();

        $response = $this->withToken($this->tokenFor($admin))
            ->getJson('/api/v1/fieldops/luminaires')
            ->assertOk();

        self::assertTrue($response->json('success'));
        self::assertCount(2, $response->json('data'));
        self::assertSame(2, $response->json('meta.total'));
    }

    public function test_luminaire_index_filters_by_complex(): void
    {
        [$luminaireA, , , , $complexA] = $this->luminaireWithClientContext();
        $this->luminaireWithClientContext();
        $admin = $this->admin();

        $response = $this->withToken($this->tokenFor($admin))
            ->getJson('/api/v1/fieldops/luminaires?complex_id='.$complexA->id)
            ->assertOk();

        self::assertCount(1, $response->json('data'));
        self::assertSame($luminaireA->id, $response->json('data.0.id'));
    }

    public function test_luminaire_index_is_scoped_to_the_clients_a_restricted_user_may_see(): void
    {
        [$mineLuminaire, $mineClient] = $this->luminaireWithClientContext();
        $this->luminaireWithClientContext();

        $user = UserFactory::new()->create();
        $user->assignRole('project_manager');
        $user->fieldOpsClients()->attach($mineClient->id, ['is_active' => true, 'can_view' => true]);

        $response = $this->withToken($this->tokenFor($user))
            ->getJson('/api/v1/fieldops/luminaires')
            ->assertOk();

        self::assertCount(1, $response->json('data'));
        self::assertSame($mineLuminaire->id, $response->json('data.0.id'));
    }

    // ── helpers ──────────────────────────────────────────────────────────────

    private function admin(): User
    {
        $admin = UserFactory::new()->create();
        $admin->assignRole('admin');
        $admin->givePermissionTo(Permission::findOrCreate('fieldops.view-all-clients', 'web'));

        return $admin;
    }

    /**
     * One maintenance type per code for the whole test: fo_maintenance_types.code
     * is unique, and the factory would create a fresh row on every work order.
     */
    private function typeId(string $code): int
    {
        $existing = FoMaintenanceType::query()->where('code', $code)->value('id');

        if ($existing !== null) {
            return (int) $existing;
        }

        $factory = FoMaintenanceType::factory();

        return (int) ($code === 'corrective' ? $factory->corrective() : $factory->preventive())->create()->id;
    }

    private function tokenFor(User $user): string
    {
        return $user->createToken('test')->plainTextToken;
    }

    private function closedOrder(Luminaire $luminaire, \DateTimeInterface $completedAt, ?string $employeeId = null): FoMaintenanceWorkOrder
    {
        return FoMaintenanceWorkOrder::factory()->forMaintainable($luminaire)->create([
            'fo_maintenance_type_id' => $this->typeId('corrective'),
            'assigned_employee_id' => $employeeId,
            'status' => MaintenanceWorkOrderStatus::COMPLETED,
            'scheduled_for' => $completedAt,
            'completed_at' => $completedAt,
        ]);
    }

    /**
     * @return array{0: Luminaire, 1: FoClient, 2: Structure, 3: Terrain, 4: Complex}
     */
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

        return [$luminaire, $client, $structure, $terrain, $complex];
    }
}
