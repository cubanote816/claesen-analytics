<?php

declare(strict_types=1);

namespace Modules\Safety\Tests\Feature;

use Database\Factories\UserFactory;
use Illuminate\Contracts\Filesystem\Filesystem as FilesystemContract;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
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
        Storage::fake(config('safety.disk'));
        Role::firstOrCreate(['name' => 'project_manager', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'super_admin',     'guard_name' => 'web']);
    }

    private function userWithRole(string $role): array
    {
        $user  = UserFactory::new()->create();
        $user->assignRole(Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']));
        $token = $user->createToken('test', ['role:safety-access'])->plainTextToken;

        return [$user, $token];
    }

    public function test_store_photo_creates_inspection_without_warnings_when_storage_succeeds(): void
    {
        Bus::fake();

        [$owner, $token] = $this->userWithRole('project_manager');
        $checklist       = Checklist::factory()->create();
        $question        = Question::factory()->create(['checklist_id' => $checklist->id]);

        $response = $this->withToken($token)->post('/api/v1/safety/inspections', [
            'checklist_id'   => $checklist->id,
            'type'           => 'inspection',
            'project_id'     => 'P-PHOTO-OK',
            'present_workers' => [$owner->id],
            'answers'        => json_encode([[
                'question_id' => $question->id,
                'value'       => 'YES',
            ]]),
            // 'photos' must be a nested array so Laravel parses it as photos.<id>
            'photos'         => [$question->id => UploadedFile::fake()->image('photo.jpg')],
        ]);

        $response->assertStatus(201);
        $this->assertArrayNotHasKey('photo_warnings', $response->json('data'));
        Bus::assertDispatched(GenerateSafetyPdfJob::class);
    }

    public function test_store_photo_storage_failure_still_creates_inspection_with_warning(): void
    {
        Bus::fake();

        [$owner, $token] = $this->userWithRole('project_manager');
        $checklist       = Checklist::factory()->create();
        $question        = Question::factory()->create(['checklist_id' => $checklist->id]);

        // Sail runs as root so path-based tricks (invalid root) are useless —
        // root can mkdir anything. Instead inject a mock disk that throws on
        // putFileAs, which is the exact call UploadedFile::storeAs() makes.
        $diskName = config('safety.disk');
        $mockDisk = \Mockery::mock(FilesystemContract::class);
        $mockDisk->shouldIgnoreMissing();
        $mockDisk->shouldReceive('putFileAs')
            ->andThrow(new \RuntimeException('Simulated disk failure'));
        Storage::set($diskName, $mockDisk);

        $response = $this->withToken($token)->post('/api/v1/safety/inspections', [
            'checklist_id'   => $checklist->id,
            'type'           => 'inspection',
            'project_id'     => 'P-PHOTO-FAIL',
            'present_workers' => [$owner->id],
            'answers'        => json_encode([[
                'question_id' => $question->id,
                'value'       => 'YES',
            ]]),
            'photos'         => [$question->id => UploadedFile::fake()->image('photo.jpg')],
        ]);

        $response->assertStatus(201);
        $this->assertArrayHasKey('photo_warnings', $response->json('data'));
        $this->assertContains($question->id, $response->json('data.photo_warnings'));
        $this->assertDatabaseCount('safety_inspections', 1);
    }
}
