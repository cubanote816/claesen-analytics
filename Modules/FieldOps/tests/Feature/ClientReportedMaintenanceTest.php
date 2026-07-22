<?php

declare(strict_types=1);

namespace Modules\FieldOps\Tests\Feature;

use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\FieldOps\Models\FoMaintenanceRecord;
use Modules\FieldOps\Models\Luminaire;
use Modules\Intelligence\Services\GeminiService;
use Tests\TestCase;

class ClientReportedMaintenanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mock(GeminiService::class, fn ($mock) => $mock->shouldReceive('translateAndDetect')->andReturn(['translations' => [], 'detected_locale' => 'nl']));
    }

    public function test_legacy_client_reported_history_remains_available_for_reading(): void
    {
        $token = $this->token();
        $luminaire = Luminaire::factory()->create();
        $pending = FoMaintenanceRecord::factory()->forMaintainable($luminaire)->clientReported()->create();
        FoMaintenanceRecord::factory()->forMaintainable($luminaire)->clientReported()->create(['problem_solved_at' => now()]);

        $this->withToken($token)
            ->getJson('/api/v1/fieldops/maintenance-records/client-reported/pending')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $pending->id);

        $this->withToken($token)
            ->getJson('/api/v1/fieldops/maintenance-records/client-reported/statistics')
            ->assertOk()
            ->assertJsonPath('data.total_reported', 2)
            ->assertJsonPath('data.pending_count', 1)
            ->assertJsonPath('data.resolved_count', 1);
    }

    public function test_legacy_client_reported_history_is_not_a_write_entrypoint(): void
    {
        $token = $this->token();
        $luminaire = Luminaire::factory()->create();
        $record = FoMaintenanceRecord::factory()->forMaintainable($luminaire)->clientReported()->create();

        $this->withToken($token)
            ->postJson('/api/v1/fieldops/maintenance-records/client-reported', [
                'maintainable_type' => Luminaire::class,
                'maintainable_id' => $luminaire->id,
                'problem_description' => 'Must become a maintenance request in CLA-268',
            ])->assertMethodNotAllowed();

        $this->withToken($token)
            ->patchJson("/api/v1/fieldops/maintenance-records/client-reported/{$record->id}/resolve", [
                'solution_applied' => 'Must be executed through a work order',
            ])->assertNotFound();

        self::assertNull($record->fresh()->problem_solved_at);
        $this->assertDatabaseCount('fo_maintenance_records', 1);
    }

    private function token(): string
    {
        return UserFactory::new()->create()->createToken('test')->plainTextToken;
    }
}
