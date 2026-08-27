<?php

namespace Modules\FieldOps\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Models\User;
use Modules\FieldOps\Models\LuminaireFrameType;
use Modules\Intelligence\Services\GeminiService;
use Tests\TestCase;

/**
 * CLA-409 (CLA-390 Fase 3) — creates a LuminaireFrameType from an
 * AI-generated image the technician already reviewed and accepted (the
 * image itself was produced by /vision-generate, this endpoint only
 * persists it). Marked source=ai_generated, same governance pattern as
 * storeLuminaireTypeFromSuggestion (CLA-389).
 */
class LuminaireFrameTypeFromGeneratedControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mock(GeminiService::class, fn ($m) => $m->shouldReceive('translateAndDetect')->andReturn(['translations' => [], 'detected_locale' => 'nl']));

        $this->user = User::factory()->create();
    }

    public function test_store_requires_auth(): void
    {
        $this->postJson('/api/v1/fieldops/luminaire-frame-types/from-generated')
            ->assertUnauthorized();
    }

    public function test_store_requires_name_and_image(): void
    {
        $this->actingAs($this->user)
            ->postJson('/api/v1/fieldops/luminaire-frame-types/from-generated', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'image_base64']);
    }

    public function test_store_creates_a_frame_type_marked_as_ai_generated_and_unverified(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/fieldops/luminaire-frame-types/from-generated', [
                'name' => 'Custom lowering headframe',
                'image_base64' => base64_encode('fake-png-bytes'),
            ]);

        $response->assertCreated()
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('fo_luminaire_frame_types', [
            'name' => 'Custom lowering headframe',
            'source' => 'ai_generated',
            'verified_by_user_id' => null,
            'created_by_user_id' => $this->user->id,
        ]);

        $frameType = LuminaireFrameType::where('name', 'Custom lowering headframe')->firstOrFail();
        // CLA-444 — must be a relative path (resolved client-side by
        // resolveApiAssetUrl), never an absolute URL baked from this
        // server's own APP_URL at write time.
        $this->assertNotNull($frameType->image);
        $this->assertStringStartsWith('/storage/', $frameType->image);
    }
}
