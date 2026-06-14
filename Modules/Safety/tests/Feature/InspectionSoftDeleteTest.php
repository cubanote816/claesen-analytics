<?php

declare(strict_types=1);

namespace Modules\Safety\Tests\Feature;

use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Safety\Models\Answer;
use Modules\Safety\Models\Checklist;
use Modules\Safety\Models\Inspection;
use Modules\Safety\Models\Question;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class InspectionSoftDeleteTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['name' => 'project_manager', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'super_admin',     'guard_name' => 'web']);
    }

    private function userWithRole(string $role): array
    {
        $user = UserFactory::new()->create();
        $user->assignRole(Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']));
        $token = $user->createToken('test', ['role:safety-access'])->plainTextToken;

        return [$user, $token];
    }

    private function makeInspectionWithAnswer(): Inspection
    {
        $checklist = Checklist::factory()->create();
        $inspection = Inspection::factory()->create([
            'checklist_id' => $checklist->id,
            'pdf_path'     => 'safety-inspections/1/report.pdf',
        ]);

        $question = Question::factory()->create(['checklist_id' => $checklist->id]);

        Answer::create([
            'inspection_id' => $inspection->id,
            'question_id'   => $question->id,
            'status'        => 'ok',
            'photo_path'    => 'safety-inspections/1/1/photo.jpg',
        ]);

        return $inspection->fresh();
    }

    // ── SAF-017: SoftDeletes behaviour ───────────────────────────────────────

    public function test_soft_deleted_inspection_hidden_from_normal_query(): void
    {
        $inspection = $this->makeInspectionWithAnswer();
        $inspection->delete();

        $this->assertNull(Inspection::find($inspection->id));
    }

    public function test_with_trashed_finds_soft_deleted_inspection(): void
    {
        $inspection = $this->makeInspectionWithAnswer();
        $inspection->delete();

        $this->assertNotNull(Inspection::withTrashed()->find($inspection->id));
    }

    public function test_soft_delete_does_not_delete_answers(): void
    {
        $inspection = $this->makeInspectionWithAnswer();
        $answerId   = $inspection->answers->first()->id;

        $inspection->delete();

        $this->assertDatabaseHas('safety_answers', ['id' => $answerId]);
    }

    public function test_soft_delete_preserves_pdf_and_photo_paths(): void
    {
        $inspection = $this->makeInspectionWithAnswer();
        $answerId   = $inspection->answers->first()->id;

        $inspection->delete();

        $this->assertDatabaseHas('safety_inspections', [
            'id'       => $inspection->id,
            'pdf_path' => 'safety-inspections/1/report.pdf',
        ]);

        $this->assertDatabaseHas('safety_answers', [
            'id'         => $answerId,
            'photo_path' => 'safety-inspections/1/1/photo.jpg',
        ]);
    }

    // ── SAF-018: Policy ──────────────────────────────────────────────────────

    public function test_super_admin_can_soft_delete_inspection(): void
    {
        [$admin] = $this->userWithRole('super_admin');
        $inspection = $this->makeInspectionWithAnswer();

        $this->assertTrue($admin->can('delete', $inspection));
    }

    public function test_project_manager_cannot_delete_inspection(): void
    {
        [$pm] = $this->userWithRole('project_manager');
        $inspection = $this->makeInspectionWithAnswer();

        $this->assertFalse($pm->can('delete', $inspection));
    }

    public function test_super_admin_can_restore_inspection(): void
    {
        [$admin] = $this->userWithRole('super_admin');
        $inspection = $this->makeInspectionWithAnswer();
        $inspection->delete();

        $trashed = Inspection::withTrashed()->find($inspection->id);

        $this->assertTrue($admin->can('restore', $trashed));
    }

    public function test_project_manager_cannot_restore_inspection(): void
    {
        [$pm] = $this->userWithRole('project_manager');
        $inspection = $this->makeInspectionWithAnswer();
        $inspection->delete();

        $trashed = Inspection::withTrashed()->find($inspection->id);

        $this->assertFalse($pm->can('restore', $trashed));
    }

    // forceDelete: not tested via Gate (super_admin has wildcard before() in Spatie).
    // Protection is at the UI/API layer: no forceDelete action exists in Filament
    // or API routes. Policy method returns false for all other roles by default.

    // ── SAF-021: API returns 404 for deleted inspections ─────────────────────

    public function test_soft_deleted_inspection_hidden_from_api_index(): void
    {
        [$owner, $token] = $this->userWithRole('project_manager');
        $checklist   = Checklist::factory()->create();
        $inspection  = Inspection::factory()->create([
            'user_id'      => $owner->id,
            'checklist_id' => $checklist->id,
        ]);
        $inspection->delete();

        $response = $this->withToken($token)
            ->getJson('/api/v1/safety/inspections');

        $response->assertOk();
        $ids = collect($response->json('data'))->pluck('id');
        $this->assertNotContains($inspection->id, $ids);
    }

    public function test_soft_deleted_inspection_show_returns_404(): void
    {
        [$owner, $token] = $this->userWithRole('project_manager');
        $checklist   = Checklist::factory()->create();
        $inspection  = Inspection::factory()->create([
            'user_id'      => $owner->id,
            'checklist_id' => $checklist->id,
        ]);
        $inspection->delete();

        $this->withToken($token)
            ->getJson("/api/v1/safety/inspections/{$inspection->id}")
            ->assertNotFound();
    }

    // ── Restore works ────────────────────────────────────────────────────────

    public function test_super_admin_can_restore_via_model(): void
    {
        $inspection = $this->makeInspectionWithAnswer();
        $inspection->delete();

        Inspection::withTrashed()->find($inspection->id)->restore();

        $this->assertNotNull(Inspection::find($inspection->id));
        $this->assertNull(Inspection::withTrashed()->find($inspection->id)->deleted_at);
    }
}
