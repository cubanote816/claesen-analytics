<?php

declare(strict_types=1);

namespace Modules\Safety\Tests\Feature;

use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Safety\Models\Checklist;
use Modules\Safety\Models\Question;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ChecklistQuestionAuthorAuditTest extends TestCase
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

    public function test_creating_question_stores_created_by_user_id(): void
    {
        $author   = UserFactory::new()->create();
        $checklist = Checklist::factory()->create();

        $this->actingAs($author);

        $question = Question::factory()->create(['checklist_id' => $checklist->id]);

        $this->assertEquals($author->id, $question->fresh()->created_by_user_id);
        $this->assertNull($question->fresh()->updated_by_user_id);
    }

    public function test_updating_question_stores_updated_by_user_id(): void
    {
        $creator = UserFactory::new()->create();
        $updater = UserFactory::new()->create();
        $checklist = Checklist::factory()->create();

        $this->actingAs($creator);
        $question = Question::factory()->create(['checklist_id' => $checklist->id]);

        $this->actingAs($updater);
        $question->update(['text_nl' => 'Bijgewerkte vraag']);

        $fresh = $question->fresh();
        $this->assertEquals($creator->id, $fresh->created_by_user_id);
        $this->assertEquals($updater->id, $fresh->updated_by_user_id);
    }

    public function test_checklist_show_returns_created_by(): void
    {
        [, $token] = $this->userWithRole('project_manager');

        $author    = UserFactory::new()->create(['name' => 'Vraag Auteur']);
        $checklist = Checklist::factory()->create(['type' => 'inspection', 'is_active' => true]);
        Question::factory()->create([
            'checklist_id'       => $checklist->id,
            'created_by_user_id' => $author->id,
            'updated_by_user_id' => null,
        ]);

        $response = $this->withToken($token)->getJson("/api/v1/safety/checklists/{$checklist->id}");

        $response->assertOk()
            ->assertJsonPath('data.questions.0.created_by.id', $author->id)
            ->assertJsonPath('data.questions.0.created_by.name', 'Vraag Auteur')
            ->assertJsonPath('data.questions.0.updated_by', null);
    }

    public function test_checklist_show_returns_updated_by_when_set(): void
    {
        [, $token] = $this->userWithRole('project_manager');

        $author    = UserFactory::new()->create(['name' => 'Auteur']);
        $updater   = UserFactory::new()->create(['name' => 'Bijwerker']);
        $checklist = Checklist::factory()->create(['type' => 'inspection', 'is_active' => true]);
        Question::factory()->create([
            'checklist_id'       => $checklist->id,
            'created_by_user_id' => $author->id,
            'updated_by_user_id' => $updater->id,
        ]);

        $response = $this->withToken($token)->getJson("/api/v1/safety/checklists/{$checklist->id}");

        $response->assertOk()
            ->assertJsonPath('data.questions.0.created_by.id', $author->id)
            ->assertJsonPath('data.questions.0.updated_by.id', $updater->id)
            ->assertJsonPath('data.questions.0.updated_by.name', 'Bijwerker');
    }

    public function test_checklist_show_does_not_fail_when_created_by_is_null(): void
    {
        [, $token] = $this->userWithRole('project_manager');

        $checklist = Checklist::factory()->create(['type' => 'inspection', 'is_active' => true]);
        // Legacy question: no auth context → created_by_user_id stays null
        Question::factory()->create([
            'checklist_id'       => $checklist->id,
            'created_by_user_id' => null,
            'updated_by_user_id' => null,
        ]);

        $response = $this->withToken($token)->getJson("/api/v1/safety/checklists/{$checklist->id}");

        $response->assertOk()
            ->assertJsonPath('data.questions.0.created_by', null)
            ->assertJsonPath('data.questions.0.updated_by', null);
    }

    public function test_checklist_show_does_not_expose_raw_author_ids(): void
    {
        [$author, $token] = $this->userWithRole('project_manager');

        $checklist = Checklist::factory()->create(['type' => 'inspection', 'is_active' => true]);

        $this->actingAs($author);
        Question::factory()->create(['checklist_id' => $checklist->id]);

        $response = $this->withToken($token)->getJson("/api/v1/safety/checklists/{$checklist->id}");

        $response->assertOk();
        $question = $response->json('data.questions.0');
        $this->assertArrayNotHasKey('created_by_user_id', $question);
        $this->assertArrayNotHasKey('updated_by_user_id', $question);
    }

    public function test_active_endpoint_returns_created_by(): void
    {
        [, $token] = $this->userWithRole('project_manager');

        $author    = UserFactory::new()->create(['name' => 'Actieve Auteur']);
        $checklist = Checklist::factory()->create(['type' => 'inspection', 'is_active' => true]);
        Question::factory()->create([
            'checklist_id'       => $checklist->id,
            'created_by_user_id' => $author->id,
        ]);

        $response = $this->withToken($token)->getJson('/api/v1/safety/checklists/active?type=inspection');

        $response->assertOk()
            ->assertJsonPath('data.questions.0.created_by.id', $author->id)
            ->assertJsonPath('data.questions.0.created_by.name', 'Actieve Auteur');
    }
}
