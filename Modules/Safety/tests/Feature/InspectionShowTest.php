<?php

declare(strict_types=1);

namespace Modules\Safety\Tests\Feature;

use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Models\User;
use Modules\Safety\Models\Checklist;
use Modules\Safety\Models\Inspection;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class InspectionShowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // ChecklistObserver queries these roles on every Checklist save; they must exist first
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

    private function makeInspection(User $owner): Inspection
    {
        $checklist = Checklist::factory()->create();

        return Inspection::factory()->create([
            'user_id'      => $owner->id,
            'checklist_id' => $checklist->id,
            'pdf_path'     => null,
        ]);
    }

    public function test_owner_gets_200_with_correct_shape(): void
    {
        [$owner, $token] = $this->userWithRole('project_manager');
        $inspection = $this->makeInspection($owner);

        $this->withToken($token)
            ->getJson("/api/v1/safety/inspections/{$inspection->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $inspection->id)
            ->assertJsonPath('data.type', $inspection->type)
            ->assertJsonPath('data.project_id', $inspection->project_id)
            ->assertJsonPath('data.pdf_status', 'pending')
            ->assertJsonPath('data.pdf_url', null)
            ->assertJsonStructure(['data' => [
                'id', 'type', 'project_id', 'completed_at',
                'pdf_status', 'pdf_url',
                'inspector' => ['id', 'name', 'email'],
                'incident_worker', 'present_workers',
                'checklist' => ['id', 'name', 'type'],
                'answers',
            ]]);
    }

    public function test_foreign_user_gets_403(): void
    {
        [$owner]           = $this->userWithRole('project_manager');
        [, $foreignToken]  = $this->userWithRole('project_manager');
        $inspection        = $this->makeInspection($owner);

        $this->withToken($foreignToken)
            ->getJson("/api/v1/safety/inspections/{$inspection->id}")
            ->assertForbidden();
    }

    public function test_nonexistent_inspection_gets_404(): void
    {
        [, $token] = $this->userWithRole('project_manager');

        $this->withToken($token)
            ->getJson('/api/v1/safety/inspections/99999')
            ->assertNotFound();
    }

    public function test_super_admin_sees_foreign_inspection(): void
    {
        [$owner]          = $this->userWithRole('project_manager');
        [, $adminToken]   = $this->userWithRole('super_admin');
        $inspection       = $this->makeInspection($owner);

        $this->withToken($adminToken)
            ->getJson("/api/v1/safety/inspections/{$inspection->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $inspection->id);
    }

    public function test_pdf_status_is_pending_when_pdf_path_is_null(): void
    {
        [$owner, $token] = $this->userWithRole('project_manager');
        $inspection      = $this->makeInspection($owner);

        $this->withToken($token)
            ->getJson("/api/v1/safety/inspections/{$inspection->id}")
            ->assertOk()
            ->assertJsonPath('data.pdf_status', 'pending');
    }
}
