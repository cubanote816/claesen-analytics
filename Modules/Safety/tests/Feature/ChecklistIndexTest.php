<?php

declare(strict_types=1);

namespace Modules\Safety\Tests\Feature;

use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Safety\Models\Checklist;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ChecklistIndexTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['name' => 'project_manager', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    }

    private function userWithRole(string $role): array
    {
        $user  = UserFactory::new()->create();
        $model = Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        $user->assignRole($model);
        $token = $user->createToken('test', ['role:safety-access'])->plainTextToken;

        return [$user, $token];
    }

    public function test_index_returns_active_checklists_for_type(): void
    {
        [, $token] = $this->userWithRole('project_manager');

        Checklist::factory()->create(['type' => 'inspection', 'is_active' => true, 'name' => 'VCA Standaard']);
        Checklist::factory()->create(['type' => 'inspection', 'is_active' => true, 'name' => 'VCA Uitgebreid']);
        Checklist::factory()->create(['type' => 'incident', 'is_active' => true, 'name' => 'Incident Form']);
        Checklist::factory()->create(['type' => 'inspection', 'is_active' => false, 'name' => 'Inactive']);

        $response = $this->withToken($token)->getJson('/api/v1/safety/checklists?type=inspection');

        $response->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonFragment(['name' => 'VCA Standaard'])
            ->assertJsonFragment(['name' => 'VCA Uitgebreid']);

        // Each item has the expected structure with string ID
        $this->assertIsString($response->json('data.0.id'));
    }

    public function test_index_filters_by_incident_type(): void
    {
        [, $token] = $this->userWithRole('project_manager');

        Checklist::factory()->create(['type' => 'inspection', 'is_active' => true]);
        Checklist::factory()->create(['type' => 'incident', 'is_active' => true, 'name' => 'Incident Form']);

        $response = $this->withToken($token)->getJson('/api/v1/safety/checklists?type=incident');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonFragment(['name' => 'Incident Form', 'type' => 'incident']);
    }

    public function test_index_returns_empty_array_when_no_active_checklists(): void
    {
        [, $token] = $this->userWithRole('project_manager');

        $response = $this->withToken($token)->getJson('/api/v1/safety/checklists?type=inspection');

        $response->assertOk()->assertJson(['data' => []]);
    }

    public function test_index_requires_type_param(): void
    {
        [, $token] = $this->userWithRole('project_manager');

        $this->withToken($token)->getJson('/api/v1/safety/checklists')
            ->assertStatus(422);
    }

    public function test_index_rejects_invalid_type(): void
    {
        [, $token] = $this->userWithRole('project_manager');

        $this->withToken($token)->getJson('/api/v1/safety/checklists?type=unknown')
            ->assertStatus(422);
    }

    public function test_index_requires_auth(): void
    {
        $this->getJson('/api/v1/safety/checklists?type=inspection')
            ->assertUnauthorized();
    }
}
