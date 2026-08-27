<?php

declare(strict_types=1);

namespace Modules\Safety\Tests\Feature;

use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Intelligence\Models\MirrorSyncRun;
use Modules\Performance\Models\Mirror\MirrorProject;
use Modules\Performance\Models\Mirror\MirrorRelation;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ProjectControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['super_admin', 'admin', 'project_manager', 'viewer'] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }
    }

    private function tokenFor(string $role): string
    {
        $user = UserFactory::new()->create();
        $user->assignRole(Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']));

        return $user->createToken('test', ['role:safety-access'])->plainTextToken;
    }

    private function project(string $id, string $name, bool $active = true, ?int $relationId = null, ?string $descr = null, ?string $projectAddressText = null): MirrorProject
    {
        return MirrorProject::create([
            'id' => $id,
            'name' => $name,
            'descr' => $descr,
            'fl_active' => $active,
            'relation_id' => $relationId,
            'project_address_text' => $projectAddressText,
        ]);
    }

    private function relation(int $id, string $name): MirrorRelation
    {
        return MirrorRelation::create(['id' => $id, 'name' => $name]);
    }

    // ── Test 1: Proyecto activo con relación devuelve descr + relation_name ─────

    public function test_active_project_with_relation_returns_descr_and_relation_name(): void
    {
        $token = $this->tokenFor('project_manager');
        $rel = $this->relation(1, 'TC Tenkie');
        $this->project('P-001', 'TC Tenkie', true, $rel->id, 'Limburg - Veldverlichting Diepenbeek');

        $this->withToken($token)
            ->getJson('/api/v1/safety/projects')
            ->assertOk()
            ->assertJsonPath('data.0.id', 'P-001')
            ->assertJsonPath('data.0.name', 'TC Tenkie')
            ->assertJsonPath('data.0.descr', 'Limburg - Veldverlichting Diepenbeek')
            ->assertJsonPath('data.0.relation_name', 'TC Tenkie');
    }

    // ── Test 2: Proyecto sin descr devuelve descr: null ───────────────────────

    public function test_active_project_without_descr_returns_null_descr(): void
    {
        $token = $this->tokenFor('project_manager');
        $this->project('P-002', 'Onbekend Project', true, null, null);

        $this->withToken($token)
            ->getJson('/api/v1/safety/projects')
            ->assertOk()
            ->assertJsonPath('data.0.id', 'P-002')
            ->assertJsonPath('data.0.descr', null)
            ->assertJsonPath('data.0.relation_name', null);
    }

    // ── Test 3: Proyecto inactivo excluido de la respuesta ────────────────────

    public function test_inactive_project_is_excluded(): void
    {
        $token = $this->tokenFor('project_manager');
        $this->project('P-ACTIVE', 'Active Project', active: true);
        $this->project('P-HIDDEN', 'Inactive Project', active: false);

        $response = $this->withToken($token)
            ->getJson('/api/v1/safety/projects')
            ->assertOk();

        $ids = collect($response->json('data'))->pluck('id');
        $this->assertContains('P-ACTIVE', $ids);
        $this->assertNotContains('P-HIDDEN', $ids);
    }

    // ── Test 4: Mirror vacío → data: [] — nunca DEV-001 ni DEV-002 ───────────

    public function test_empty_mirror_returns_empty_array_without_fake_projects(): void
    {
        $token = $this->tokenFor('project_manager');

        $this->withToken($token)
            ->getJson('/api/v1/safety/projects')
            ->assertOk()
            ->assertExactJson(['data' => [], 'meta' => ['last_synced_at' => null]]);
    }

    // ── CLA-404: meta.last_synced_at reflects the mirror sync history ────────

    public function test_meta_last_synced_at_reflects_the_latest_completed_run(): void
    {
        $token = $this->tokenFor('project_manager');

        // Older completed run — must be ignored in favor of the more recent one below.
        MirrorSyncRun::create([
            'status' => MirrorSyncRun::STATUS_COMPLETED,
            'trigger_source' => MirrorSyncRun::SOURCE_SCHEDULED,
            'started_at' => now()->subDays(1),
            'finished_at' => now()->subDays(1)->addMinutes(5),
        ]);

        // Most recent completed run — this is the one that must win.
        $mostRecentCompleted = MirrorSyncRun::create([
            'status' => MirrorSyncRun::STATUS_COMPLETED,
            'trigger_source' => MirrorSyncRun::SOURCE_MANUAL,
            'started_at' => now()->subMinutes(10),
            'finished_at' => now()->subMinutes(3),
        ]);

        // Failed run, more recent than any completed run — must never win just by
        // being newer; only status=completed rows are eligible.
        MirrorSyncRun::create([
            'status' => MirrorSyncRun::STATUS_FAILED,
            'trigger_source' => MirrorSyncRun::SOURCE_SCHEDULED,
            'started_at' => now()->subMinutes(2),
            'finished_at' => now()->subMinute(),
        ]);

        // Running run, more recent still, with no finished_at at all — must never
        // win either (would otherwise crash the null-safe finished_at?-> access).
        MirrorSyncRun::create([
            'status' => MirrorSyncRun::STATUS_RUNNING,
            'trigger_source' => MirrorSyncRun::SOURCE_SCHEDULED,
            'started_at' => now(),
        ]);

        $this->withToken($token)
            ->getJson('/api/v1/safety/projects')
            ->assertOk()
            ->assertJsonPath('meta.last_synced_at', $mostRecentCompleted->finished_at->toIso8601String());
    }

    public function test_meta_last_synced_at_is_null_when_no_run_has_completed(): void
    {
        $token = $this->tokenFor('project_manager');

        MirrorSyncRun::create([
            'status' => MirrorSyncRun::STATUS_RUNNING,
            'trigger_source' => MirrorSyncRun::SOURCE_SCHEDULED,
            'started_at' => now(),
        ]);

        $this->withToken($token)
            ->getJson('/api/v1/safety/projects')
            ->assertOk()
            ->assertJsonPath('meta.last_synced_at', null);
    }

    // ── Test 5: project_address_text con dirección multilinea real ───────────

    public function test_project_address_text_is_returned_as_stored_without_modification(): void
    {
        $token = $this->tokenFor('project_manager');
        $address = "Ladrie Tennis Club sc\nVoie des Maçons 2\n4630 Soumagne";
        $this->project('P-ADDR', 'Derriks', true, null, 'Soumagne Tennis Ladrie', $address);

        $this->withToken($token)
            ->getJson('/api/v1/safety/projects')
            ->assertOk()
            ->assertJsonPath('data.0.project_address_text', $address);
    }

    // ── Test 6: project_address_text null cuando no hay dirección ────────────

    public function test_project_address_text_is_null_when_not_set(): void
    {
        $token = $this->tokenFor('project_manager');
        $this->project('P-NOADDR', 'Musco Lighting Germany', true, null, 'DE-Ansbach Middle School', null);

        $this->withToken($token)
            ->getJson('/api/v1/safety/projects')
            ->assertOk()
            ->assertJsonPath('data.0.project_address_text', null);
    }

    // ── Test 7: El controller no importa el modelo Cafca (SQL Server) ─────────

    public function test_controller_does_not_import_cafca_project_model(): void
    {
        $source = file_get_contents(
            base_path('Modules/Safety/Http/Controllers/ProjectController.php')
        );

        $this->assertStringNotContainsString(
            'Modules\Cafca\Models\Project',
            $source,
            'ProjectController must not reference the Cafca SQL Server model.'
        );
    }
}
