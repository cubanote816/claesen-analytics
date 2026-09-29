<?php

declare(strict_types=1);

namespace Modules\FieldOps\Tests\Feature;

use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\Cafca\Models\Employee;
use Modules\Core\Models\User;
use Modules\FieldOps\Enums\MaintenanceWorkOrderStatus;
use Modules\FieldOps\Models\Complex;
use Modules\FieldOps\Models\ElectricalBoard;
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

/** CLA-594 — conteos por complejo en ComplexResource (lista y detalle). */
class ComplexCountsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mock(GeminiService::class, fn ($m) => $m->shouldReceive('translateAndDetect')->andReturn(['translations' => [], 'detected_locale' => 'nl']));
        foreach (['super_admin', 'admin', 'technician'] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }
    }

    private function actor(string $role, array $attributes = []): array
    {
        $user = UserFactory::new()->create($attributes);
        $user->assignRole($role);
        $user->givePermissionTo(Permission::findOrCreate('fieldops.view-all-clients', 'web'));

        return [$user, $user->createToken('test')->plainTextToken];
    }

    /** @return array{Complex, Terrain, Structure, Luminaire} */
    private function hierarchy(): array
    {
        $complex = Complex::factory()->create();
        $terrain = Terrain::factory()->create(['complex_id' => $complex->id]);
        $structure = Structure::factory()->create();
        $structure->terrains()->attach($terrain);
        $frame = LuminaireFrame::factory()->create();
        $frame->structures()->attach($structure);
        $luminaire = Luminaire::factory()->create(['luminaire_frame_id' => $frame->id]);

        return [$complex, $terrain, $structure, $luminaire];
    }

    private function order(Luminaire|ElectricalBoard $equipment, MaintenanceWorkOrderStatus $status, ?string $employeeId = null): FoMaintenanceWorkOrder
    {
        $type = FoMaintenanceType::query()->where('code', 'preventive')->first()
            ?? FoMaintenanceType::factory()->preventive()->create();

        return FoMaintenanceWorkOrder::factory()->create([
            'fo_maintenance_type_id' => $type->id,
            'maintainable_id' => $equipment->id,
            'maintainable_type' => $equipment::class,
            'status' => $status,
            'assigned_employee_id' => $employeeId,
        ]);
    }

    public function test_index_and_show_expose_counts_per_complex(): void
    {
        [, $token] = $this->actor('admin');
        [$complex, $terrain, $structure] = $this->hierarchy();

        $second = Terrain::factory()->create(['complex_id' => $complex->id]);
        $structure->terrains()->attach($second); // misma estructura en 2 terrenos: cuenta 1
        $other = Structure::factory()->create();
        $other->terrains()->attach($second);
        $emptyComplex = Complex::factory()->create();

        $expected = ['terrains' => 2, 'structures' => 2, 'luminaires' => 1, 'open_work_orders' => 0];

        $index = $this->withToken($token)->getJson('/api/v1/fieldops/complexes')->assertOk();
        $rows = collect($index->json('data'))->keyBy('id');
        $this->assertSame($expected, $rows[$complex->id]['counts']);
        $this->assertSame(
            ['terrains' => 0, 'structures' => 0, 'luminaires' => 0, 'open_work_orders' => 0],
            $rows[$emptyComplex->id]['counts'],
        );

        $this->withToken($token)->getJson("/api/v1/fieldops/complexes/{$complex->id}")
            ->assertOk()
            ->assertJsonPath('data.counts', $expected);
    }

    public function test_soft_deleted_and_removed_items_are_not_counted(): void
    {
        [, $token] = $this->actor('admin');
        [$complex, $terrain, $structure, $luminaire] = $this->hierarchy();

        $gone = Terrain::factory()->create(['complex_id' => $complex->id]);
        $gone->delete();
        $goneStructure = Structure::factory()->create();
        $goneStructure->terrains()->attach($terrain);
        $goneStructure->delete();
        $luminaire->delete();

        $this->withToken($token)->getJson("/api/v1/fieldops/complexes/{$complex->id}")
            ->assertOk()
            ->assertJsonPath('data.counts', ['terrains' => 1, 'structures' => 1, 'luminaires' => 0, 'open_work_orders' => 0]);
    }

    public function test_open_work_orders_count_distinct_orders_and_exclude_closed_ones(): void
    {
        [, $token] = $this->actor('admin');
        [$complex, $terrain, $structure, $luminaire] = $this->hierarchy();

        // Un tablero enlazado al complejo por las 3 rutas a la vez: la misma orden cuenta una vez.
        $board = ElectricalBoard::factory()->create();
        $complex->electricalBoards()->attach($board);
        $terrain->electricalBoards()->attach($board);
        $structure->electricalBoards()->attach($board);

        $this->order($luminaire, MaintenanceWorkOrderStatus::PLANNED);
        $this->order($luminaire, MaintenanceWorkOrderStatus::IN_PROGRESS);
        $this->order($board, MaintenanceWorkOrderStatus::AWAITING_VALIDATION);
        $this->order($luminaire, MaintenanceWorkOrderStatus::COMPLETED);
        $this->order($board, MaintenanceWorkOrderStatus::CANCELLED);

        $this->withToken($token)->getJson("/api/v1/fieldops/complexes/{$complex->id}")
            ->assertOk()
            ->assertJsonPath('data.counts.open_work_orders', 3);
    }

    public function test_technician_only_counts_orders_assigned_to_them(): void
    {
        $mine = Employee::create(['id' => 'FIELD-MINE', 'name' => 'Mine', 'fl_active' => true]);
        $theirs = Employee::create(['id' => 'FIELD-THEIRS', 'name' => 'Theirs', 'fl_active' => true]);
        [, $token] = $this->actor('technician', ['employee_id' => $mine->id]);
        [$complex, , , $luminaire] = $this->hierarchy();

        $this->order($luminaire, MaintenanceWorkOrderStatus::ASSIGNED, $mine->id);
        $this->order($luminaire, MaintenanceWorkOrderStatus::ASSIGNED, $theirs->id);
        $this->order($luminaire, MaintenanceWorkOrderStatus::PLANNED);

        $this->withToken($token)->getJson("/api/v1/fieldops/complexes/{$complex->id}")
            ->assertOk()
            ->assertJsonPath('data.counts.open_work_orders', 1);
    }

    public function test_count_queries_do_not_grow_with_the_number_of_complexes(): void
    {
        [, $token] = $this->actor('admin');
        foreach (range(1, 3) as $ignored) {
            $this->hierarchy();
        }

        $countQueries = function (): int {
            DB::flushQueryLog();
            $this->withToken($this->token)->getJson('/api/v1/fieldops/complexes')->assertOk();

            return collect(DB::getQueryLog())
                ->filter(fn ($q) => str_contains($q['query'], 'fo_structure_terrain') || str_contains($q['query'], 'fo_maintenance_work_orders'))
                ->count();
        };

        $this->token = $token;
        DB::enableQueryLog();
        $few = $countQueries();

        foreach (range(1, 6) as $ignored) {
            $this->hierarchy();
        }
        $many = $countQueries();

        $this->assertGreaterThan(0, $few);
        $this->assertSame($few, $many, 'los conteos no deben crecer con el número de complejos (N+1)');
    }

    private string $token = '';
}
