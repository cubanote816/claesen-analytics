<?php

declare(strict_types=1);

namespace Modules\FieldOps\Tests\Feature;

use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
        $this->mock(GeminiService::class, fn ($mock) => $mock->shouldReceive('translateAndDetect')->andReturn(['translations' => [], 'detected_locale' => 'nl']));
    }

    public function test_history_endpoints_require_authentication(): void
    {
        $luminaire = Luminaire::factory()->create();

        $this->getJson("/api/v1/fieldops/luminaires/{$luminaire->id}/maintenance-records")
            ->assertUnauthorized();
    }

    public function test_history_can_be_read_for_luminaire_and_electrical_board(): void
    {
        $token = $this->token();
        $luminaire = Luminaire::factory()->create();
        $board = ElectricalBoard::factory()->create();
        $type = FoMaintenanceType::factory()->preventive()->create();
        $luminaireRecord = FoMaintenanceRecord::factory()->forMaintainable($luminaire)->create(['fo_maintenance_type_id' => $type->id]);
        $boardRecord = FoMaintenanceRecord::factory()->forMaintainable($board)->create(['fo_maintenance_type_id' => $type->id]);

        $this->withToken($token)
            ->getJson("/api/v1/fieldops/luminaires/{$luminaire->id}/maintenance-records")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $luminaireRecord->id);

        $this->withToken($token)
            ->getJson("/api/v1/fieldops/electrical-boards/{$board->id}/maintenance-records")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $boardRecord->id);
    }

    public function test_history_detail_remains_read_only(): void
    {
        $token = $this->token();
        $luminaire = Luminaire::factory()->create();
        $record = FoMaintenanceRecord::factory()->forMaintainable($luminaire)->create(['notes' => 'Validated evidence']);

        $this->withToken($token)
            ->getJson("/api/v1/fieldops/maintenance-records/{$record->id}")
            ->assertOk()
            ->assertJsonPath('data.notes', 'Validated evidence');
    }

    public function test_direct_history_creation_is_not_routable(): void
    {
        $token = $this->token();
        $luminaire = Luminaire::factory()->create();
        $board = ElectricalBoard::factory()->create();
        $type = FoMaintenanceType::factory()->preventive()->create();
        $payload = [
            'fo_maintenance_type_id' => $type->id,
            'maintenance_at' => '2026-07-22 10:00:00',
        ];

        $this->withToken($token)
            ->postJson("/api/v1/fieldops/luminaires/{$luminaire->id}/maintenance-records", $payload)
            ->assertMethodNotAllowed();
        $this->withToken($token)
            ->postJson("/api/v1/fieldops/electrical-boards/{$board->id}/maintenance-records", $payload)
            ->assertMethodNotAllowed();

        $this->assertDatabaseCount('fo_maintenance_records', 0);
    }

    public function test_validated_history_cannot_be_updated_or_deleted_through_api(): void
    {
        $token = $this->token();
        $luminaire = Luminaire::factory()->create();
        $record = FoMaintenanceRecord::factory()->forMaintainable($luminaire)->create(['notes' => 'Original evidence']);

        $this->withToken($token)
            ->patchJson("/api/v1/fieldops/maintenance-records/{$record->id}", ['notes' => 'Tampered'])
            ->assertMethodNotAllowed();
        $this->withToken($token)
            ->deleteJson("/api/v1/fieldops/maintenance-records/{$record->id}")
            ->assertMethodNotAllowed();

        $record->refresh();
        self::assertSame('Original evidence', $record->notes);
        self::assertNull($record->deleted_at);
    }

    public function test_legacy_client_reported_writes_cannot_create_or_resolve_history(): void
    {
        $token = $this->token();
        $luminaire = Luminaire::factory()->create();
        $record = FoMaintenanceRecord::factory()->forMaintainable($luminaire)->clientReported()->create();

        $this->withToken($token)
            ->postJson('/api/v1/fieldops/maintenance-records/client-reported', [
                'maintainable_type' => Luminaire::class,
                'maintainable_id' => $luminaire->id,
                'problem_description' => 'Direct history write',
            ])->assertMethodNotAllowed();
        $this->withToken($token)
            ->patchJson("/api/v1/fieldops/maintenance-records/client-reported/{$record->id}/resolve", [
                'solution_applied' => 'Bypassed validation',
            ])->assertNotFound();

        self::assertNull($record->fresh()->problem_solved_at);
        $this->assertDatabaseCount('fo_maintenance_records', 1);
    }

    public function test_maintenance_catalog_and_corrective_stats_remain_available(): void
    {
        $token = $this->token();
        $luminaire = Luminaire::factory()->create();
        $corrective = FoMaintenanceType::factory()->corrective()->create();
        FoMaintenanceType::factory()->preventive()->create();
        FoMaintenanceRecord::factory()->forMaintainable($luminaire)->create(['fo_maintenance_type_id' => $corrective->id]);

        $this->withToken($token)
            ->getJson('/api/v1/fieldops/maintenance-types')
            ->assertOk();
        $this->withToken($token)
            ->getJson('/api/v1/fieldops/maintenance-records/stats/corrective')
            ->assertOk()
            ->assertJsonPath('data.total_corrective', 1);
    }

    private function token(): string
    {
        return UserFactory::new()->create()->createToken('test')->plainTextToken;
    }
}
