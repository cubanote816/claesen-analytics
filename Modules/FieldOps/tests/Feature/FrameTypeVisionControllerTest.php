<?php

namespace Modules\FieldOps\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Modules\Core\Models\User;
use Modules\FieldOps\Models\LuminaireFrameType;
use Modules\Intelligence\Services\ClaudeVisionService;
use Modules\Intelligence\Services\GeminiService;
use Tests\TestCase;

/**
 * CLA-390 — the endpoint only ever suggests candidates against the
 * LuminaireFrameType catalog; it never creates or edits anything.
 * ClaudeVisionService is mocked so these tests never hit the network.
 */
class FrameTypeVisionControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mock(GeminiService::class, fn ($m) => $m->shouldReceive('translateAndDetect')->andReturn(['translations' => [], 'detected_locale' => 'nl']));

        $this->user = User::factory()->create();
    }

    public function test_suggest_requires_auth(): void
    {
        $this->postJson('/api/v1/fieldops/luminaire-frame-types/vision-suggestions')
            ->assertUnauthorized();
    }

    public function test_suggest_requires_a_valid_photo(): void
    {
        $this->actingAs($this->user)
            ->postJson('/api/v1/fieldops/luminaire-frame-types/vision-suggestions', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['photo']);
    }

    public function test_suggest_returns_candidates_from_the_vision_service(): void
    {
        $type = LuminaireFrameType::factory()->create(['name' => 'Fixed cross-arm headframe']);

        $this->mock(ClaudeVisionService::class, function ($mock) use ($type) {
            $mock->shouldReceive('identifyFrameType')
                ->once()
                ->andReturn([
                    'status' => 'probable',
                    'candidates' => [[
                        'catalog_id' => $type->id,
                        'confidence' => 0.6,
                        'evidence' => ['three-tier cross-arm layout'],
                        'status' => 'probable',
                    ]],
                ]);
        });

        $this->actingAs($this->user)
            ->postJson('/api/v1/fieldops/luminaire-frame-types/vision-suggestions', [
                'photo' => UploadedFile::fake()->image('frame.jpg'),
            ])
            ->assertOk()
            ->assertJson([
                'success' => true,
                'data' => ['status' => 'probable'],
            ])
            ->assertJsonPath('data.candidates.0.catalog_id', $type->id);
    }

    public function test_suggest_returns_unknown_status_as_a_valid_outcome_not_an_error(): void
    {
        LuminaireFrameType::factory()->create();

        $this->mock(ClaudeVisionService::class, function ($mock) {
            $mock->shouldReceive('identifyFrameType')
                ->once()
                ->andReturn(['status' => 'unknown', 'candidates' => []]);
        });

        $this->actingAs($this->user)
            ->postJson('/api/v1/fieldops/luminaire-frame-types/vision-suggestions', [
                'photo' => UploadedFile::fake()->image('frame.jpg'),
            ])
            ->assertOk()
            ->assertJson([
                'success' => true,
                'data' => ['status' => 'unknown', 'candidates' => []],
            ]);
    }

    public function test_suggest_only_passes_id_and_name_from_the_catalog_not_the_full_model(): void
    {
        LuminaireFrameType::factory()->create(['name' => 'Oval stadium headframe']);

        $this->mock(ClaudeVisionService::class, function ($mock) {
            $mock->shouldReceive('identifyFrameType')
                ->once()
                ->withArgs(function ($imageBase64, $mediaType, $catalog) {
                    return count($catalog) === 1
                        && array_keys($catalog[0]) === ['id', 'name']
                        && $catalog[0]['name'] === 'Oval stadium headframe';
                })
                ->andReturn(['status' => 'unknown', 'candidates' => []]);
        });

        $this->actingAs($this->user)
            ->postJson('/api/v1/fieldops/luminaire-frame-types/vision-suggestions', [
                'photo' => UploadedFile::fake()->image('frame.jpg'),
            ])
            ->assertOk();
    }
}
