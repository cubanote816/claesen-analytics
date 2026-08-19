<?php

namespace Modules\FieldOps\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Modules\Core\Models\User;
use Modules\FieldOps\Database\Seeders\PlaceholderLuminaireTypeSeeder;
use Modules\FieldOps\Models\LuminaireFrame;
use Modules\FieldOps\Models\LuminaireType;
use Modules\Intelligence\Services\ClaudeVisionService;
use Modules\Intelligence\Services\GeminiService;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * CLA-391 (CLA-390 Fase 2) — the endpoint only ever returns detections
 * (position + best-effort type match); it never creates or edits a
 * luminaire. ClaudeVisionService is mocked so these tests never hit the
 * network, same pattern as LuminaireVisionControllerTest (CLA-386).
 */
class LuminaireDetectionVisionControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private LuminaireFrame $frame;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mock(GeminiService::class, fn ($m) => $m->shouldReceive('translateAndDetect')->andReturn(['translations' => [], 'detected_locale' => 'nl']));

        $this->user = User::factory()->create();
        $this->user->givePermissionTo(Permission::findOrCreate('fieldops.view-all-clients', 'web'));

        $this->frame = LuminaireFrame::factory()->create();
    }

    public function test_detect_requires_auth(): void
    {
        $this->postJson("/api/v1/fieldops/luminaire-frames/{$this->frame->id}/vision-luminaire-detections")
            ->assertUnauthorized();
    }

    public function test_detect_requires_a_valid_photo(): void
    {
        $this->actingAs($this->user)
            ->postJson("/api/v1/fieldops/luminaire-frames/{$this->frame->id}/vision-luminaire-detections", [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['photo']);
    }

    public function test_detect_returns_detections_from_the_vision_service(): void
    {
        $type = LuminaireType::factory()->create(['name' => 'Thorn Altis Gen5']);

        $this->mock(ClaudeVisionService::class, function ($mock) use ($type) {
            $mock->shouldReceive('detectLuminairesInFrame')
                ->once()
                ->andReturn([
                    'status' => 'detected',
                    'detections' => [[
                        'x' => 0.32,
                        'y' => 0.18,
                        'catalog_id' => $type->id,
                        'confidence' => 0.7,
                        'evidence' => ['4-module rectangular housing'],
                        'status' => 'probable',
                    ]],
                ]);
        });

        $this->actingAs($this->user)
            ->postJson("/api/v1/fieldops/luminaire-frames/{$this->frame->id}/vision-luminaire-detections", [
                'photo' => UploadedFile::fake()->image('frame.jpg'),
            ])
            ->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [
                    'luminaire_frame_id' => $this->frame->id,
                    'status' => 'detected',
                ],
            ])
            ->assertJsonPath('data.detections.0.catalog_id', $type->id)
            ->assertJsonPath('data.detections.0.x', 0.32);
    }

    public function test_detect_returns_unknown_status_as_a_valid_outcome_not_an_error(): void
    {
        $this->mock(ClaudeVisionService::class, function ($mock) {
            $mock->shouldReceive('detectLuminairesInFrame')
                ->once()
                ->andReturn(['status' => 'unknown', 'detections' => []]);
        });

        $this->actingAs($this->user)
            ->postJson("/api/v1/fieldops/luminaire-frames/{$this->frame->id}/vision-luminaire-detections", [
                'photo' => UploadedFile::fake()->image('frame.jpg'),
            ])
            ->assertOk()
            ->assertJson([
                'success' => true,
                'data' => ['status' => 'unknown', 'detections' => []],
            ]);
    }

    public function test_detect_response_includes_the_seeded_placeholder_type_ids(): void
    {
        $this->seed(PlaceholderLuminaireTypeSeeder::class);
        $expected = PlaceholderLuminaireTypeSeeder::resolveIds();

        $this->mock(ClaudeVisionService::class, function ($mock) {
            $mock->shouldReceive('detectLuminairesInFrame')
                ->once()
                ->andReturn(['status' => 'unknown', 'detections' => []]);
        });

        $this->actingAs($this->user)
            ->postJson("/api/v1/fieldops/luminaire-frames/{$this->frame->id}/vision-luminaire-detections", [
                'photo' => UploadedFile::fake()->image('frame.jpg'),
            ])
            ->assertOk()
            ->assertJsonPath('data.placeholder.luminaire_type_id', $expected['luminaire_type_id'])
            ->assertJsonPath('data.placeholder.luminaire_subgroup_id', $expected['luminaire_subgroup_id']);
    }

    public function test_detect_returns_null_placeholder_when_the_seed_has_not_run(): void
    {
        $this->mock(ClaudeVisionService::class, function ($mock) {
            $mock->shouldReceive('detectLuminairesInFrame')
                ->once()
                ->andReturn(['status' => 'unknown', 'detections' => []]);
        });

        $this->actingAs($this->user)
            ->postJson("/api/v1/fieldops/luminaire-frames/{$this->frame->id}/vision-luminaire-detections", [
                'photo' => UploadedFile::fake()->image('frame.jpg'),
            ])
            ->assertOk()
            ->assertJsonPath('data.placeholder', null);
    }
}
