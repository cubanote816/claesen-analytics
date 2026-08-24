<?php

namespace Modules\FieldOps\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Modules\Core\Models\User;
use Modules\Intelligence\Services\GeminiImageGenerationService;
use Modules\Intelligence\Services\GeminiService;
use Tests\TestCase;

/**
 * CLA-409 (CLA-390 Fase 3) — the endpoint only ever returns a generated
 * preview; it never creates or edits anything. GeminiImageGenerationService
 * is mocked so these tests never hit the network.
 */
class FrameTypeVisionGenerateControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mock(GeminiService::class, fn ($m) => $m->shouldReceive('translateAndDetect')->andReturn(['translations' => [], 'detected_locale' => 'nl']));

        $this->user = User::factory()->create();
    }

    public function test_generate_requires_auth(): void
    {
        $this->postJson('/api/v1/fieldops/luminaire-frame-types/vision-generate')
            ->assertUnauthorized();
    }

    public function test_generate_requires_a_valid_photo(): void
    {
        $this->actingAs($this->user)
            ->postJson('/api/v1/fieldops/luminaire-frame-types/vision-generate', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['photo']);
    }

    public function test_generate_returns_the_generated_image_and_suggested_name(): void
    {
        $this->mock(GeminiImageGenerationService::class, function ($mock) {
            $mock->shouldReceive('generateFrameTypeImage')
                ->once()
                ->andReturn([
                    'status' => 'generated',
                    'image_base64' => base64_encode('fake-png-bytes'),
                    'mime_type' => 'image/png',
                    'suggested_name' => 'Test lowering headframe',
                ]);
        });

        $this->actingAs($this->user)
            ->postJson('/api/v1/fieldops/luminaire-frame-types/vision-generate', [
                'photo' => UploadedFile::fake()->image('frame.jpg'),
            ])
            ->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [
                    'status' => 'generated',
                    'mime_type' => 'image/png',
                    'suggested_name' => 'Test lowering headframe',
                ],
            ]);
    }

    public function test_generate_returns_failed_status_as_a_valid_outcome_not_an_error(): void
    {
        $this->mock(GeminiImageGenerationService::class, function ($mock) {
            $mock->shouldReceive('generateFrameTypeImage')
                ->once()
                ->andReturn(['status' => 'failed', 'image_base64' => null, 'mime_type' => null, 'suggested_name' => null]);
        });

        $this->actingAs($this->user)
            ->postJson('/api/v1/fieldops/luminaire-frame-types/vision-generate', [
                'photo' => UploadedFile::fake()->image('frame.jpg'),
            ])
            ->assertOk()
            ->assertJson([
                'success' => true,
                'data' => ['status' => 'failed', 'image_base64' => null],
            ]);
    }
}
