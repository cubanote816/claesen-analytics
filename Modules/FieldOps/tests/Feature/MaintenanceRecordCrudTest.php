<?php

declare(strict_types=1);

namespace Modules\FieldOps\Tests\Feature;

use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Cafca\Models\Employee;
use Modules\FieldOps\Models\ElectricalBoard;
use Modules\FieldOps\Models\FoMaintenanceRecord;
use Modules\FieldOps\Models\FoMaintenanceType;
use Modules\FieldOps\Models\Luminaire;
use Modules\Intelligence\Services\GeminiService;
use Tests\TestCase;

class MaintenanceRecordCrudTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mock(GeminiService::class, fn ($m) => $m->shouldReceive('translateAndDetect')->andReturn(['translations' => [], 'detected_locale' => 'nl']));
    }

    private function user(): array
    {
        $user  = UserFactory::new()->create();
        $token = $user->createToken('test')->plainTextToken;

        return [$user, $token];
    }

    // ── store for luminaire ──────────────────────────────────────────────────

    public function test_store_for_luminaire_creates_record_and_returns_201(): void
    {
        [, $token] = $this->user();
        $luminaire = Luminaire::factory()->create();
        $type      = FoMaintenanceType::factory()->preventive()->create();

        $response = $this->withToken($token)->postJson("/api/v1/fieldops/luminaires/{$luminaire->id}/maintenance-records", [
            'fo_maintenance_type_id' => $type->id,
            'maintenance_at'         => '2026-07-01 10:00:00',
            'details'                => ['inspection' => true, 'cleaning' => true],
            'notes'                  => 'Routine check',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.maintainable_type', Luminaire::class)
            ->assertJsonPath('data.maintainable_id', $luminaire->id);

        $this->assertDatabaseHas('fo_maintenance_records', [
            'maintainable_id'   => $luminaire->id,
            'maintainable_type' => Luminaire::class,
            'notes'             => 'Routine check',
        ]);
    }

    public function test_store_for_luminaire_injects_created_by_user_id(): void
    {
        [$user, $token] = $this->user();
        $luminaire = Luminaire::factory()->create();
        $type      = FoMaintenanceType::factory()->preventive()->create();

        $this->withToken($token)->postJson("/api/v1/fieldops/luminaires/{$luminaire->id}/maintenance-records", [
            'fo_maintenance_type_id' => $type->id,
            'maintenance_at'         => '2026-07-01 10:00:00',
        ]);

        $this->assertDatabaseHas('fo_maintenance_records', ['created_by_user_id' => $user->id]);
    }

    public function test_store_for_luminaire_requires_maintenance_type(): void
    {
        [, $token] = $this->user();
        $luminaire = Luminaire::factory()->create();

        $this->withToken($token)->postJson("/api/v1/fieldops/luminaires/{$luminaire->id}/maintenance-records", [
            'maintenance_at' => '2026-07-01 10:00:00',
        ])->assertStatus(422)->assertJsonValidationErrors('fo_maintenance_type_id');
    }

    public function test_store_for_luminaire_rejects_nonexistent_employee_id(): void
    {
        [, $token] = $this->user();
        $luminaire = Luminaire::factory()->create();
        $type      = FoMaintenanceType::factory()->preventive()->create();

        $this->withToken($token)->postJson("/api/v1/fieldops/luminaires/{$luminaire->id}/maintenance-records", [
            'fo_maintenance_type_id' => $type->id,
            'maintenance_at'         => '2026-07-01 10:00:00',
            'employee_id'            => 'DOES-NOT-EXIST',
        ])->assertStatus(422)->assertJsonValidationErrors('employee_id');
    }

    public function test_store_for_luminaire_accepts_existing_employee_id(): void
    {
        [, $token] = $this->user();
        $luminaire = Luminaire::factory()->create();
        $type      = FoMaintenanceType::factory()->preventive()->create();
        $employee  = Employee::create(['id' => 'EMP-001', 'name' => 'Jan Jansen']);

        $this->withToken($token)->postJson("/api/v1/fieldops/luminaires/{$luminaire->id}/maintenance-records", [
            'fo_maintenance_type_id' => $type->id,
            'maintenance_at'         => '2026-07-01 10:00:00',
            'employee_id'            => $employee->id,
        ])->assertStatus(201)
            ->assertJsonPath('data.employee.id', 'EMP-001');
    }

    public function test_store_for_luminaire_requires_authentication(): void
    {
        $luminaire = Luminaire::factory()->create();
        $type      = FoMaintenanceType::factory()->preventive()->create();

        $this->postJson("/api/v1/fieldops/luminaires/{$luminaire->id}/maintenance-records", [
            'fo_maintenance_type_id' => $type->id,
            'maintenance_at'         => '2026-07-01 10:00:00',
        ])->assertStatus(401);
    }

    // ── store for electrical board ───────────────────────────────────────────

    public function test_store_for_electrical_board_creates_record(): void
    {
        [, $token] = $this->user();
        $board = ElectricalBoard::factory()->create();
        $type  = FoMaintenanceType::factory()->corrective()->create();

        $response = $this->withToken($token)->postJson("/api/v1/fieldops/electrical-boards/{$board->id}/maintenance-records", [
            'fo_maintenance_type_id' => $type->id,
            'maintenance_at'         => '2026-07-01 10:00:00',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.maintainable_type', ElectricalBoard::class)
            ->assertJsonPath('data.maintainable_id', $board->id);
    }

    // ── index ─────────────────────────────────────────────────────────────────

    public function test_index_for_luminaire_returns_only_its_own_records(): void
    {
        [, $token] = $this->user();
        $luminaireA = Luminaire::factory()->create();
        $luminaireB = Luminaire::factory()->create();
        $type       = FoMaintenanceType::factory()->preventive()->create();

        FoMaintenanceRecord::factory()->forMaintainable($luminaireA)->create(['fo_maintenance_type_id' => $type->id]);
        FoMaintenanceRecord::factory()->forMaintainable($luminaireB)->create(['fo_maintenance_type_id' => $type->id]);

        $response = $this->withToken($token)->getJson("/api/v1/fieldops/luminaires/{$luminaireA->id}/maintenance-records")->assertOk();

        $this->assertCount(1, $response->json('data'));
    }

    // ── show / update / destroy ──────────────────────────────────────────────

    public function test_show_returns_record_with_relations(): void
    {
        [, $token] = $this->user();
        $luminaire = Luminaire::factory()->create();
        $record    = FoMaintenanceRecord::factory()->forMaintainable($luminaire)->create();

        $response = $this->withToken($token)->getJson("/api/v1/fieldops/maintenance-records/{$record->id}")->assertOk();

        $response->assertJsonPath('data.id', $record->id);
    }

    public function test_update_patches_only_sent_fields(): void
    {
        [, $token] = $this->user();
        $luminaire = Luminaire::factory()->create();
        $record    = FoMaintenanceRecord::factory()->forMaintainable($luminaire)->create(['notes' => 'Old note']);

        $this->withToken($token)->patchJson("/api/v1/fieldops/maintenance-records/{$record->id}", [
            'notes' => 'Updated note',
        ])->assertStatus(200)->assertJsonPath('data.notes', 'Updated note');

        $this->assertDatabaseHas('fo_maintenance_records', ['id' => $record->id, 'notes' => 'Updated note']);
    }

    public function test_destroy_soft_deletes_record(): void
    {
        [, $token] = $this->user();
        $luminaire = Luminaire::factory()->create();
        $record    = FoMaintenanceRecord::factory()->forMaintainable($luminaire)->create();

        $this->withToken($token)->deleteJson("/api/v1/fieldops/maintenance-records/{$record->id}")
            ->assertStatus(204);

        $this->assertSoftDeleted('fo_maintenance_records', ['id' => $record->id]);
    }

    // ── maintenance types catalog ────────────────────────────────────────────

    public function test_maintenance_types_endpoint_lists_catalog(): void
    {
        [, $token] = $this->user();
        FoMaintenanceType::factory()->preventive()->create();
        FoMaintenanceType::factory()->corrective()->create();

        $response = $this->withToken($token)->getJson('/api/v1/fieldops/maintenance-types')->assertOk();

        $this->assertCount(2, $response->json('data'));
    }

    // ── stats ─────────────────────────────────────────────────────────────────

    public function test_corrective_stats_counts_only_corrective_records(): void
    {
        [, $token] = $this->user();
        $luminaire  = Luminaire::factory()->create();
        $corrective = FoMaintenanceType::factory()->corrective()->create();
        $preventive = FoMaintenanceType::factory()->preventive()->create();

        FoMaintenanceRecord::factory()->forMaintainable($luminaire)->create(['fo_maintenance_type_id' => $corrective->id]);
        FoMaintenanceRecord::factory()->forMaintainable($luminaire)->create(['fo_maintenance_type_id' => $preventive->id]);

        $response = $this->withToken($token)->getJson('/api/v1/fieldops/maintenance-records/stats/corrective')->assertOk();

        $response->assertJsonPath('data.total_corrective', 1);
    }
}
