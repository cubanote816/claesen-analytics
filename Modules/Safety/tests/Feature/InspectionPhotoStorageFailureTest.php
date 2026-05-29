<?php

declare(strict_types=1);

namespace Modules\Safety\Tests\Feature;

use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Modules\Safety\Jobs\GenerateSafetyPdfJob;
use Modules\Safety\Models\Checklist;
use Modules\Safety\Models\Question;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class InspectionPhotoStorageFailureTest extends TestCase
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

    public function test_store_photo_creates_inspection_without_warnings_when_storage_succeeds(): void
    {
        [$owner, $token] = $this->userWithRole('project_manager');
        $checklist       = Checklist::factory()->create();
        $question        = Question::factory()->create(['checklist_id' => $checklist->id]);

        $photo = UploadedFile::fake()->image('photo.jpg');

        $response = $this->withToken($token)->post('/api/v1/safety/inspections', [
            'checklist_id'    => $checklist->id,
            'type'            => 'inspection',
            'project_id'      => 'P-PHOTO-OK',
            'present_workers' => [$owner->id],
            'answers'         => json_encode([[
                'question_id' => $question->id,
                'value'       => 'YES',
            ]]),
            "photos[{$question->id}]" => $photo,
        ]);

        $response->assertStatus(201);
        $this->assertArrayNotHasKey('photo_warnings', $response->json('data'));
        Queue::assertDispatched(GenerateSafetyPdfJob::class);
    }

    public function test_store_photo_storage_failure_still_creates_inspection_with_warning(): void
    {
        [$owner, $token] = $this->userWithRole('project_manager');
        $checklist       = Checklist::factory()->create();
        $question        = Question::factory()->create(['checklist_id' => $checklist->id]);

        // Replace the safety disk with a mock that throws on put
        $failingDisk = $this->mock(\Illuminate\Contracts\Filesystem\Filesystem::class);
        $failingDisk->shouldReceive('putFileAs')->andThrow(new \RuntimeException('Disk full'));
        $failingDisk->shouldReceive('put')->andThrow(new \RuntimeException('Disk full'));
        app('filesystem')->set(config('safety.disk'), $failingDisk);

        $photo = UploadedFile::fake()->image('photo.jpg');

        $response = $this->withToken($token)->post('/api/v1/safety/inspections', [
            'checklist_id'    => $checklist->id,
            'type'            => 'inspection',
            'project_id'      => 'P-PHOTO-FAIL',
            'present_workers' => [$owner->id],
            'answers'         => json_encode([[
                'question_id' => $question->id,
                'value'       => 'YES',
            ]]),
            "photos[{$question->id}]" => $photo,
        ]);

        // Inspection is still created despite photo failure
        $response->assertStatus(201);
        $this->assertArrayHasKey('photo_warnings', $response->json('data'));
        $this->assertContains((string) $question->id, $response->json('data.photo_warnings'));
        $this->assertDatabaseCount('safety_inspections', 1);
    }
}
