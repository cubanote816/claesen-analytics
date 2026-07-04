<?php

declare(strict_types=1);

namespace Modules\FieldOps\Tests\Feature;

use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Cafca\Models\Employee;
use Modules\FieldOps\Models\FoClient;
use Modules\FieldOps\Models\FoMaintenanceRecord;
use Modules\FieldOps\Models\FoMaintenanceType;
use Modules\FieldOps\Models\Luminaire;
use Modules\Intelligence\Services\GeminiService;
use Tests\TestCase;

class ClientReportedMaintenanceTest extends TestCase
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

    public function test_store_client_reported_creates_emergency_record(): void
    {
        [, $token] = $this->user();
        FoMaintenanceType::factory()->emergency()->create();
        $luminaire = Luminaire::factory()->create();
        $client    = FoClient::factory()->create();

        $response = $this->withToken($token)->postJson('/api/v1/fieldops/maintenance-records/client-reported', [
            'maintainable_id'     => $luminaire->id,
            'maintainable_type'   => Luminaire::class,
            'problem_description' => 'Light not working',
            'client_id'           => $client->id,
            'priority'            => 'high',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.reported_by_client', true)
            ->assertJsonPath('data.is_emergency', true)
            ->assertJsonPath('data.problem_status', 'in_progress');

        $this->assertDatabaseHas('fo_maintenance_records', [
            'maintainable_id'    => $luminaire->id,
            'reported_by_client' => true,
            'client_id'          => $client->id,
        ]);
    }

    public function test_store_client_reported_requires_client_id(): void
    {
        [, $token] = $this->user();
        FoMaintenanceType::factory()->emergency()->create();
        $luminaire = Luminaire::factory()->create();

        $this->withToken($token)->postJson('/api/v1/fieldops/maintenance-records/client-reported', [
            'maintainable_id'     => $luminaire->id,
            'maintainable_type'   => Luminaire::class,
            'problem_description' => 'Light not working',
        ])->assertStatus(422)->assertJsonValidationErrors('client_id');
    }

    public function test_store_client_reported_fails_without_emergency_type_configured(): void
    {
        [, $token] = $this->user();
        $luminaire = Luminaire::factory()->create();
        $client    = FoClient::factory()->create();

        $this->withToken($token)->postJson('/api/v1/fieldops/maintenance-records/client-reported', [
            'maintainable_id'     => $luminaire->id,
            'maintainable_type'   => Luminaire::class,
            'problem_description' => 'Light not working',
            'client_id'           => $client->id,
        ])->assertStatus(422);
    }

    public function test_pending_client_reported_excludes_resolved_records(): void
    {
        [, $token] = $this->user();
        $luminaire = Luminaire::factory()->create();

        FoMaintenanceRecord::factory()->forMaintainable($luminaire)->clientReported()->create();
        FoMaintenanceRecord::factory()->forMaintainable($luminaire)->clientReported()->create(['problem_solved_at' => now()]);

        $response = $this->withToken($token)->getJson('/api/v1/fieldops/maintenance-records/client-reported/pending')->assertOk();

        $this->assertCount(1, $response->json('data'));
    }

    public function test_client_reported_statistics_returns_counts(): void
    {
        [, $token] = $this->user();
        $luminaire = Luminaire::factory()->create();

        FoMaintenanceRecord::factory()->forMaintainable($luminaire)->clientReported()->create();
        FoMaintenanceRecord::factory()->forMaintainable($luminaire)->clientReported()->create(['problem_solved_at' => now()]);

        $response = $this->withToken($token)->getJson('/api/v1/fieldops/maintenance-records/client-reported/statistics')->assertOk();

        $response->assertJsonPath('data.total_reported', 2)
            ->assertJsonPath('data.pending_count', 1)
            ->assertJsonPath('data.resolved_count', 1);
    }

    public function test_resolve_client_reported_marks_record_solved(): void
    {
        [, $token] = $this->user();
        $luminaire = Luminaire::factory()->create();
        $employee  = Employee::create(['id' => 'EMP-002', 'name' => 'Piet Peters']);
        $record    = FoMaintenanceRecord::factory()->forMaintainable($luminaire)->clientReported()->create();

        $response = $this->withToken($token)->patchJson("/api/v1/fieldops/maintenance-records/client-reported/{$record->id}/resolve", [
            'solution_applied' => 'Replaced fuse',
            'employee_id'      => $employee->id,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.problem_status', 'resolved')
            ->assertJsonPath('data.solution_applied', 'Replaced fuse');

        $this->assertDatabaseHas('fo_maintenance_records', [
            'id'                => $record->id,
            'employee_id'       => 'EMP-002',
            'solution_applied'  => 'Replaced fuse',
        ]);
    }

    public function test_resolve_client_reported_rejects_non_client_reported_record(): void
    {
        [, $token] = $this->user();
        $luminaire = Luminaire::factory()->create();
        $employee  = Employee::create(['id' => 'EMP-003', 'name' => 'Ann Anders']);
        $record    = FoMaintenanceRecord::factory()->forMaintainable($luminaire)->create();

        $this->withToken($token)->patchJson("/api/v1/fieldops/maintenance-records/client-reported/{$record->id}/resolve", [
            'solution_applied' => 'Replaced fuse',
            'employee_id'      => $employee->id,
        ])->assertStatus(422)->assertJsonPath('success', false);
    }

    public function test_resolve_client_reported_rejects_already_resolved_record(): void
    {
        [, $token] = $this->user();
        $luminaire = Luminaire::factory()->create();
        $employee  = Employee::create(['id' => 'EMP-004', 'name' => 'Tom Thys']);
        $record    = FoMaintenanceRecord::factory()->forMaintainable($luminaire)->clientReported()->create(['problem_solved_at' => now()]);

        $this->withToken($token)->patchJson("/api/v1/fieldops/maintenance-records/client-reported/{$record->id}/resolve", [
            'solution_applied' => 'Replaced fuse',
            'employee_id'      => $employee->id,
        ])->assertStatus(422)->assertJsonPath('success', false);
    }
}
