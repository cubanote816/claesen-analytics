<?php

namespace Modules\FieldOps\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Modules\Core\Models\User;
use Modules\FieldOps\Models\LuminaireFrame;
use Modules\FieldOps\Models\LuminaireType;
use Modules\Intelligence\Services\ClaudeVisionService;
use Modules\Intelligence\Services\GeminiService;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * CLA-386 — the endpoint only ever suggests candidates; it never creates or
 * edits a luminaire. ClaudeVisionService is mocked (same pattern already
 * used for GeminiService elsewhere in this suite) so these tests never hit
 * the network.
 */
class LuminaireVisionControllerTest extends TestCase
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

    public function test_suggest_requires_auth(): void
    {
        $this->postJson("/api/v1/fieldops/luminaire-frames/{$this->frame->id}/vision-suggestions")
            ->assertUnauthorized();
    }

    public function test_suggest_requires_a_valid_photo(): void
    {
        $this->actingAs($this->user)
            ->postJson("/api/v1/fieldops/luminaire-frames/{$this->frame->id}/vision-suggestions", [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['photo']);
    }

    public function test_suggest_returns_candidates_from_the_vision_service(): void
    {
        $type = LuminaireType::factory()->create(['name' => 'Thorn Altis Gen5']);

        $this->mock(ClaudeVisionService::class, function ($mock) use ($type) {
            $mock->shouldReceive('identifyLuminaires')
                ->once()
                ->andReturn([
                    'status' => 'probable',
                    'candidates' => [[
                        'catalog_id' => $type->id,
                        'confidence' => 0.72,
                        'evidence' => ['4-module rectangular housing'],
                        'status' => 'probable',
                    ]],
                ]);
        });

        $response = $this->actingAs($this->user)
            ->postJson("/api/v1/fieldops/luminaire-frames/{$this->frame->id}/vision-suggestions", [
                'photo' => UploadedFile::fake()->image('frame.jpg'),
            ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [
                    'luminaire_frame_id' => $this->frame->id,
                    'status' => 'probable',
                ],
            ])
            ->assertJsonPath('data.candidates.0.catalog_id', $type->id);
    }

    public function test_suggest_returns_unknown_status_as_a_valid_outcome_not_an_error(): void
    {
        $this->mock(ClaudeVisionService::class, function ($mock) {
            $mock->shouldReceive('identifyLuminaires')
                ->once()
                ->andReturn(['status' => 'unknown', 'candidates' => []]);
        });

        $this->actingAs($this->user)
            ->postJson("/api/v1/fieldops/luminaire-frames/{$this->frame->id}/vision-suggestions", [
                'photo' => UploadedFile::fake()->image('frame.jpg'),
            ])
            ->assertOk()
            ->assertJson([
                'success' => true,
                'data' => ['status' => 'unknown', 'candidates' => []],
            ]);
    }
}
