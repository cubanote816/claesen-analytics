<?php

declare(strict_types=1);

namespace Modules\Safety\Tests\Feature;

use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Modules\Safety\Jobs\GenerateSafetyPdfJob;
use Modules\Safety\Models\Checklist;
use Modules\Safety\Models\Inspection;
use Modules\Safety\Models\Question;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class InspectionAuthStoreIndexTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        Storage::fake(config('safety.disk'));
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

    // =========================================================================
    // Auth — POST /api/v1/login
    // =========================================================================

    public function test_login_success_project_manager(): void
    {
        $user = UserFactory::new()->create(['password' => bcrypt('secret123')]);
        $role = Role::firstOrCreate(['name' => 'project_manager', 'guard_name' => 'web']);
        $user->assignRole($role);

        $response = $this->postJson('/api/v1/login', [
            'email'    => $user->email,
            'password' => 'secret123',
        ]);

        $response->assertOk()
            ->assertJsonStructure(['token', 'token_type', 'user']);

        // Verify the token carries role:safety-access by hitting a protected endpoint
        $this->withToken($response->json('token'))
            ->getJson('/api/v1/safety/inspections')
            ->assertOk();
    }

    public function test_login_wrong_password_returns_401(): void
    {
        $user = UserFactory::new()->create(['password' => bcrypt('correct')]);

        $this->postJson('/api/v1/login', [
            'email'    => $user->email,
            'password' => 'wrong',
        ])->assertUnauthorized();
    }

    public function test_login_no_safety_role_returns_403(): void
    {
        $user = UserFactory::new()->create(['password' => bcrypt('secret123')]);
        // No role assigned — should be rejected by AuthController role check

        $this->postJson('/api/v1/login', [
            'email'    => $user->email,
            'password' => 'secret123',
        ])->assertForbidden();
    }

    // =========================================================================
    // Store — POST /api/v1/safety/inspections
    // =========================================================================

    public function test_store_inspection_returns_201(): void
    {
        [$owner, $token] = $this->userWithRole('project_manager');
        $checklist       = Checklist::factory()->create();
        $question        = Question::factory()->create(['checklist_id' => $checklist->id]);

        $response = $this->withToken($token)->postJson('/api/v1/safety/inspections', [
            'checklist_id'    => $checklist->id,
            'type'            => 'inspection',
            'project_id'      => 'P-TEST-001',
            'present_workers' => [$owner->id], // required_if:type,inspection — must be non-empty
            'answers'         => [
                ['question_id' => $question->id, 'value' => 'YES', 'remark' => null],
            ],
        ]);

        $response->assertCreated()
            ->assertJsonStructure(['data' => ['inspection_id']]);

        Queue::assertPushed(GenerateSafetyPdfJob::class);
    }

    public function test_store_validates_required_fields_returns_422(): void
    {
        [, $token] = $this->userWithRole('project_manager');

        $this->withToken($token)
            ->postJson('/api/v1/safety/inspections', [])
            ->assertUnprocessable();
    }

    public function test_store_requires_auth_returns_401(): void
    {
        $this->postJson('/api/v1/safety/inspections', [])
            ->assertUnauthorized();
    }

    // =========================================================================
    // Index — GET /api/v1/safety/inspections
    // =========================================================================

    public function test_index_pm_sees_only_own_inspections(): void
    {
        [$pm1, $token1] = $this->userWithRole('project_manager');
        [$pm2]          = $this->userWithRole('project_manager');
        $checklist      = Checklist::factory()->create();

        Inspection::factory()->count(2)->create(['user_id' => $pm1->id, 'checklist_id' => $checklist->id]);
        Inspection::factory()->create(['user_id' => $pm2->id, 'checklist_id' => $checklist->id]);

        $response = $this->withToken($token1)->getJson('/api/v1/safety/inspections');

        $response->assertOk();
        $this->assertCount(2, $response->json('data'));
    }

    public function test_index_admin_sees_all(): void
    {
        [$pm1]          = $this->userWithRole('project_manager');
        [$pm2]          = $this->userWithRole('project_manager');
        [, $adminToken] = $this->userWithRole('super_admin');
        $checklist      = Checklist::factory()->create();

        Inspection::factory()->count(2)->create(['user_id' => $pm1->id, 'checklist_id' => $checklist->id]);
        Inspection::factory()->create(['user_id' => $pm2->id, 'checklist_id' => $checklist->id]);

        $response = $this->withToken($adminToken)->getJson('/api/v1/safety/inspections');

        $response->assertOk();
        $this->assertCount(3, $response->json('data'));
    }

    public function test_index_filters_by_type(): void
    {
        [$owner, $token] = $this->userWithRole('project_manager');
        $checklist       = Checklist::factory()->create();

        Inspection::factory()->create(['user_id' => $owner->id, 'checklist_id' => $checklist->id, 'type' => 'inspection']);
        Inspection::factory()->create(['user_id' => $owner->id, 'checklist_id' => $checklist->id, 'type' => 'incident']);

        $response = $this->withToken($token)->getJson('/api/v1/safety/inspections?type=incident');

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
        $this->assertSame('incident', $response->json('data.0.category'));
    }

    public function test_index_paginates_per_page(): void
    {
        [$owner, $token] = $this->userWithRole('project_manager');
        $checklist       = Checklist::factory()->create();

        Inspection::factory()->count(3)->create(['user_id' => $owner->id, 'checklist_id' => $checklist->id]);

        $response = $this->withToken($token)->getJson('/api/v1/safety/inspections?per_page=2');

        $response->assertOk();
        $this->assertCount(2, $response->json('data'));
    }

    public function test_index_caps_per_page_max(): void
    {
        [, $token] = $this->userWithRole('project_manager');

        $response = $this->withToken($token)->getJson('/api/v1/safety/inspections?per_page=999');

        $response->assertOk();
        $this->assertLessThanOrEqual(50, $response->json('per_page'));
    }
}
