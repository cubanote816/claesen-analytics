<?php

declare(strict_types=1);

namespace Modules\Safety\Tests\Feature;

use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Performance\Models\Mirror\MirrorProject;
use Modules\Safety\Services\ComplianceService;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ComplianceControllerTest extends TestCase
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

    public function test_super_admin_200_with_missing_projects(): void
    {
        [, $token] = $this->userWithRole('super_admin');

        $this->mock(ComplianceService::class)
            ->shouldReceive('getMissingInspections')
            ->once()
            ->andReturn(collect([
                new MirrorProject(['id' => 'P-001', 'name' => 'Project Alpha']),
                new MirrorProject(['id' => 'P-002', 'name' => 'Project Beta']),
            ]));

        $response = $this->withToken($token)->getJson('/api/v1/safety/compliance');

        $response->assertOk()
            ->assertJsonStructure(['data' => [['project_id', 'name']], 'count'])
            ->assertJsonPath('count', 2)
            ->assertJsonPath('data.0.project_id', 'P-001')
            ->assertJsonPath('data.0.name', 'Project Alpha');
    }

    public function test_super_admin_200_empty_when_service_returns_empty(): void
    {
        [, $token] = $this->userWithRole('super_admin');

        $this->mock(ComplianceService::class)
            ->shouldReceive('getMissingInspections')
            ->once()
            ->andReturn(collect());

        $response = $this->withToken($token)->getJson('/api/v1/safety/compliance');

        $response->assertOk()
            ->assertExactJson(['data' => [], 'count' => 0]);
    }

    public function test_project_manager_gets_403(): void
    {
        [, $token] = $this->userWithRole('project_manager');

        $response = $this->withToken($token)->getJson('/api/v1/safety/compliance');

        $response->assertForbidden();
    }

    public function test_unauthenticated_gets_401(): void
    {
        $this->getJson('/api/v1/safety/compliance')->assertUnauthorized();
    }
}
