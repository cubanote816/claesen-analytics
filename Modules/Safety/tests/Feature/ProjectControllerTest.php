<?php

declare(strict_types=1);

namespace Modules\Safety\Tests\Feature;

use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\Performance\Models\Mirror\MirrorProject;
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

    private function project(string $id, string $name, bool $active = true, ?int $relationId = null): MirrorProject
    {
        return MirrorProject::create([
            'id'          => $id,
            'name'        => $name,
            'fl_active'   => $active,
            'relation_id' => $relationId,
        ]);
    }

    private function relation(int $id, string $name): int
    {
        // MirrorRelation.id is a non-auto-increment integer PK (ERP-sourced).
        // Using DB::table avoids Eloquent's $incrementing=true behaviour which
        // would return last_insert_id()=0 instead of the provided id, breaking
        // the FK match with intelligence_mirror_projects.relation_id.
        DB::table('intelligence_mirror_relations')->insert([
            'id'         => $id,
            'name'       => $name,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        return $id;
    }

    // ── Test 1: Proyecto activo con relación devuelve relation_name ───────────

    public function test_active_project_with_relation_returns_relation_name(): void
    {
        $token = $this->tokenFor('project_manager');
        $relId = $this->relation(1, 'TC Tenkie');
        $this->project('P-001', 'Limburg Diepenbeek', true, $relId);

        $this->withToken($token)
            ->getJson('/api/v1/safety/projects')
            ->assertOk()
            ->assertJsonPath('data.0.id', 'P-001')
            ->assertJsonPath('data.0.name', 'Limburg Diepenbeek')
            ->assertJsonPath('data.0.relation_name', 'TC Tenkie');
    }

    // ── Test 2: Proyecto activo sin relación devuelve relation_name: null ─────

    public function test_active_project_without_relation_returns_null_relation_name(): void
    {
        $token = $this->tokenFor('project_manager');
        $this->project('P-002', 'Onbekend Project', true, null);

        $this->withToken($token)
            ->getJson('/api/v1/safety/projects')
            ->assertOk()
            ->assertJsonPath('data.0.id', 'P-002')
            ->assertJsonPath('data.0.relation_name', null);
    }

    // ── Test 3: Proyecto inactivo excluido de la respuesta ────────────────────

    public function test_inactive_project_is_excluded(): void
    {
        $token = $this->tokenFor('project_manager');
        $this->project('P-ACTIVE', 'Active Project',   active: true);
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
            ->assertExactJson(['data' => []]);
    }

    // ── Test 5: El controller no importa el modelo Cafca (SQL Server) ─────────

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
