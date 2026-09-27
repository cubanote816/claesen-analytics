<?php

namespace Modules\FieldOps\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Models\User;
use Modules\FieldOps\Models\LuminaireFrameType;
use Modules\Intelligence\Services\GeminiService;
use Spatie\Permission\Models\Permission;
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

    // CLA-502: a minimal real 1x1 transparent PNG, base64-encoded — the same
    // shape OpenAiImageGenerationService would actually produce. Using genuine
    // image bytes here (not an arbitrary string) is what exercises the new
    // getimagesizefromstring() check as a pass-through, not just skip it.
    private const VALID_PNG_BASE64 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=';

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mock(GeminiService::class, fn ($m) => $m->shouldReceive('translateAndDetect')->andReturn(['translations' => [], 'detected_locale' => 'nl']));

        $this->user = User::factory()->create();
        // CLA-502: the endpoint now requires this explicitly.
        $this->user->givePermissionTo(Permission::findOrCreate('fieldops.ai', 'web'));
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
                'image_base64' => self::VALID_PNG_BASE64,
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

    // CLA-502: base64_decode() alone only rejects invalid base64 characters —
    // it happily "decodes" arbitrary text into arbitrary bytes. This is the
    // exact gap the ticket flagged: non-image bytes must be rejected before
    // ever reaching Storage::put().
    public function test_store_rejects_non_image_bytes(): void
    {
        $this->actingAs($this->user)
            ->postJson('/api/v1/fieldops/luminaire-frame-types/from-generated', [
                'name' => 'Not actually an image',
                'image_base64' => base64_encode('this is definitely not a PNG'),
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['image_base64']);

        $this->assertDatabaseMissing('fo_luminaire_frame_types', ['name' => 'Not actually an image']);
    }

    public function test_store_rejects_undecodable_base64(): void
    {
        $this->actingAs($this->user)
            ->postJson('/api/v1/fieldops/luminaire-frame-types/from-generated', [
                'name' => 'Invalid base64',
                // '!!!' is not valid base64 alphabet — base64_decode(..., true) fails outright.
                'image_base64' => '!!!not-valid-base64!!!',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['image_base64']);
    }
}
