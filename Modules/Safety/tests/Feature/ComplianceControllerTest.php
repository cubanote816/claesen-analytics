<?php

declare(strict_types=1);

namespace Modules\Safety\Tests\Feature;

use Carbon\Carbon;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Performance\Models\Mirror\MirrorProject;
use Modules\Safety\Database\Factories\InspectionFactory;
use Modules\Safety\Models\Inspection;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ComplianceControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['super_admin', 'admin', 'project_manager', 'viewer'] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }
    }

    private function userWithRole(string $role): array
    {
        $user  = UserFactory::new()->create();
        $user->assignRole(Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']));
        $token = $user->createToken('test', ['role:safety-access'])->plainTextToken;

        return [$user, $token];
    }

    private function project(string $id, string $name = null, bool $active = true): MirrorProject
    {
        return MirrorProject::create(['id' => $id, 'name' => $name ?? "Project {$id}", 'fl_active' => $active]);
    }

    private function inspection(string $projectId, int $userId, Carbon $completedAt = null): Inspection
    {
        return InspectionFactory::new()->create([
            'user_id'      => $userId,
            'project_id'   => $projectId,
            'completed_at' => $completedAt ?? now(),
        ]);
    }

    // ── Authorization ─────────────────────────────────────────────────────────

    public function test_unauthenticated_gets_401(): void
    {
        $this->getJson('/api/v1/safety/compliance')->assertUnauthorized();
    }

    public function test_viewer_role_gets_403_from_middleware(): void
    {
        [, $token] = $this->userWithRole('viewer');

        $this->withToken($token)->getJson('/api/v1/safety/compliance')->assertForbidden();
    }

    // ── super_admin ────────────────────────────────────────────────────────────

    public function test_super_admin_sees_all_non_compliant_projects(): void
    {
        [, $token] = $this->userWithRole('super_admin');
        $this->project('P-001', 'Alpha');
        $this->project('P-002', 'Beta');

        $this->withToken($token)->getJson('/api/v1/safety/compliance')
            ->assertOk()
            ->assertJsonPath('count', 2);
    }

    public function test_super_admin_gets_200_empty_when_all_projects_compliant(): void
    {
        [$user, $token] = $this->userWithRole('super_admin');
        $this->project('P-RECENT');
        $this->inspection('P-RECENT', $user->id, now()->subDays(5));

        $this->withToken($token)->getJson('/api/v1/safety/compliance')
            ->assertOk()
            ->assertExactJson(['data' => [], 'count' => 0]);
    }

    // ── admin ──────────────────────────────────────────────────────────────────

    public function test_admin_sees_all_non_compliant_projects(): void
    {
        [, $token] = $this->userWithRole('admin');
        $this->project('P-001', 'Alpha');
        $this->project('P-002', 'Beta');

        $this->withToken($token)->getJson('/api/v1/safety/compliance')
            ->assertOk()
            ->assertJsonPath('count', 2);
    }

    // ── project_manager ────────────────────────────────────────────────────────

    public function test_project_manager_sees_all_projects_same_as_admin(): void
    {
        [$pm,    $pmToken]    = $this->userWithRole('project_manager');
        [,        $adminToken] = $this->userWithRole('admin');

        $this->project('P-001');
        $this->project('P-002');
        // P-001 only has an inspection by the PM, P-002 has none — both must
        // appear for both roles, since every PM can inspect any project.
        $this->inspection('P-001', $pm->id, now()->subDays(40));

        $pmResponse    = $this->withToken($pmToken)->getJson('/api/v1/safety/compliance');
        $adminResponse = $this->withToken($adminToken)->getJson('/api/v1/safety/compliance');

        $pmResponse->assertOk();
        $adminResponse->assertOk();

        $this->assertSame(
            $pmResponse->json('count'),
            $adminResponse->json('count'),
            'project_manager and admin must receive the same number of projects'
        );

        $pmIds    = collect($pmResponse->json('data'))->pluck('project_id')->sort()->values()->toArray();
        $adminIds = collect($adminResponse->json('data'))->pluck('project_id')->sort()->values()->toArray();
        $this->assertSame($pmIds, $adminIds, 'project_manager and admin must see the same project list');
    }

    // ── Compliance logic ───────────────────────────────────────────────────────

    public function test_project_inspected_within_30_days_is_not_non_compliant(): void
    {
        [$user, $token] = $this->userWithRole('super_admin');
        $this->project('P-RECENT');
        $this->inspection('P-RECENT', $user->id, now()->subDays(10));

        $this->withToken($token)->getJson('/api/v1/safety/compliance')
            ->assertOk()
            ->assertJsonPath('count', 0);
    }

    public function test_project_inspected_more_than_30_days_ago_is_non_compliant(): void
    {
        [$user, $token] = $this->userWithRole('super_admin');
        $this->project('P-OLD');
        $this->inspection('P-OLD', $user->id, now()->subDays(31));

        $this->withToken($token)->getJson('/api/v1/safety/compliance')
            ->assertOk()
            ->assertJsonPath('count', 1)
            ->assertJsonPath('data.0.project_id', 'P-OLD');
    }

    public function test_project_with_no_inspection_appears_with_null_date(): void
    {
        [, $token] = $this->userWithRole('super_admin');
        $this->project('P-NEVER', 'Never Inspected');

        $this->withToken($token)->getJson('/api/v1/safety/compliance')
            ->assertOk()
            ->assertJsonPath('count', 1)
            ->assertJsonPath('data.0.last_inspection_date', null)
            ->assertJsonPath('data.0.days_since_inspection', null);
    }

    // ── Response contract ──────────────────────────────────────────────────────

    public function test_response_includes_all_required_fields(): void
    {
        [, $token] = $this->userWithRole('super_admin');
        $this->project('P-CONTRACT', 'Contract Test');

        $this->withToken($token)->getJson('/api/v1/safety/compliance')
            ->assertOk()
            ->assertJsonStructure([
                'data'  => [['project_id', 'project_name', 'project_code', 'last_inspection_date', 'days_since_inspection']],
                'count',
            ])
            ->assertJsonPath('data.0.project_id',   'P-CONTRACT')
            ->assertJsonPath('data.0.project_name', 'Contract Test')
            ->assertJsonPath('data.0.project_code', null);
    }

    public function test_sorting_puts_most_overdue_first_and_null_date_last(): void
    {
        [$user, $token] = $this->userWithRole('super_admin');

        $this->project('P-NEVER');
        $this->project('P-OLD-60');
        $this->project('P-OLD-35');

        $this->inspection('P-OLD-60', $user->id, now()->subDays(60));
        $this->inspection('P-OLD-35', $user->id, now()->subDays(35));
        // P-NEVER has no inspection

        $response = $this->withToken($token)->getJson('/api/v1/safety/compliance');
        $response->assertOk()->assertJsonPath('count', 3);

        $ids = collect($response->json('data'))->pluck('project_id')->toArray();
        $this->assertSame('P-OLD-60', $ids[0]);
        $this->assertSame('P-OLD-35', $ids[1]);
        $this->assertSame('P-NEVER',  $ids[2]);
    }
}
