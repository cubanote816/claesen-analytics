<?php

declare(strict_types=1);

namespace Modules\Safety\Tests\Feature;

use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Modules\Safety\Models\Answer;
use Modules\Safety\Models\Checklist;
use Modules\Safety\Models\Inspection;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class InspectionServePhotoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
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

    private function makeInspectionWithPhoto($owner): array
    {
        $checklist  = Checklist::factory()->create();
        $inspection = Inspection::factory()->create([
            'user_id'      => $owner->id,
            'checklist_id' => $checklist->id,
        ]);

        $file      = UploadedFile::fake()->image('photo.jpg');
        $photoPath = "safety-inspections/{$inspection->id}/photo.jpg";
        Storage::disk(config('safety.disk'))->put($photoPath, $file->getContent());

        $answer = Answer::factory()->create([
            'inspection_id' => $inspection->id,
            'photo_path'    => $photoPath,
        ]);

        return [$inspection, $answer];
    }

    public function test_owner_gets_200_with_photo(): void
    {
        [$owner, $token] = $this->userWithRole('project_manager');
        [$inspection, $answer] = $this->makeInspectionWithPhoto($owner);

        $this->withToken($token)
            ->get("/api/v1/safety/inspections/{$inspection->id}/answers/{$answer->id}/photo")
            ->assertOk()
            ->assertHeader('cache-control', 'max-age=900, private');
    }

    public function test_foreign_user_gets_403(): void
    {
        [$owner]          = $this->userWithRole('project_manager');
        [, $foreignToken] = $this->userWithRole('project_manager');
        [$inspection, $answer] = $this->makeInspectionWithPhoto($owner);

        $this->withToken($foreignToken)
            ->get("/api/v1/safety/inspections/{$inspection->id}/answers/{$answer->id}/photo")
            ->assertForbidden();
    }

    public function test_answer_from_different_inspection_gets_404(): void
    {
        [$owner, $token] = $this->userWithRole('project_manager');
        $checklist       = Checklist::factory()->create();

        $inspection1 = Inspection::factory()->create([
            'user_id'      => $owner->id,
            'checklist_id' => $checklist->id,
        ]);
        $inspection2 = Inspection::factory()->create([
            'user_id'      => $owner->id,
            'checklist_id' => $checklist->id,
        ]);

        $answer = Answer::factory()->create([
            'inspection_id' => $inspection2->id,
            'photo_path'    => 'safety-inspections/other/photo.jpg',
        ]);

        // answer belongs to inspection2 but path uses inspection1's id
        $this->withToken($token)
            ->get("/api/v1/safety/inspections/{$inspection1->id}/answers/{$answer->id}/photo")
            ->assertNotFound();
    }

    public function test_answer_without_photo_gets_404(): void
    {
        [$owner, $token] = $this->userWithRole('project_manager');
        $checklist       = Checklist::factory()->create();
        $inspection      = Inspection::factory()->create([
            'user_id'      => $owner->id,
            'checklist_id' => $checklist->id,
        ]);

        $answer = Answer::factory()->create([
            'inspection_id' => $inspection->id,
            'photo_path'    => null,
        ]);

        $this->withToken($token)
            ->get("/api/v1/safety/inspections/{$inspection->id}/answers/{$answer->id}/photo")
            ->assertNotFound();
    }

    public function test_super_admin_gets_200_on_foreign_inspection(): void
    {
        [$owner]        = $this->userWithRole('project_manager');
        [, $adminToken] = $this->userWithRole('super_admin');
        [$inspection, $answer] = $this->makeInspectionWithPhoto($owner);

        $this->withToken($adminToken)
            ->get("/api/v1/safety/inspections/{$inspection->id}/answers/{$answer->id}/photo")
            ->assertOk()
            ->assertHeader('cache-control', 'max-age=900, private');
    }
}
