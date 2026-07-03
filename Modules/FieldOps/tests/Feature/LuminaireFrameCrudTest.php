<?php

namespace Modules\FieldOps\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Models\User;
use Modules\FieldOps\Models\LuminaireFrame;
use Modules\FieldOps\Models\LuminaireFrameType;
use Modules\FieldOps\Models\Structure;
use Modules\Intelligence\Services\GeminiService;
use Tests\TestCase;

class LuminaireFrameCrudTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mock(GeminiService::class, fn ($m) => $m->shouldReceive('translateAndDetect')->andReturn(['translations' => [], 'detected_locale' => 'nl']));
        $this->user = User::factory()->create();
    }

    // ── AUTH ──────────────────────────────────────────────────────────────

    public function test_index_requires_auth(): void
    {
        $this->getJson('/api/v1/fieldops/luminaire-frames')->assertUnauthorized();
    }

    public function test_store_requires_auth(): void
    {
        $this->postJson('/api/v1/fieldops/luminaire-frames', [])->assertUnauthorized();
    }

    // ── INDEX ─────────────────────────────────────────────────────────────

    public function test_index_returns_paginated_list(): void
    {
        LuminaireFrame::factory()->count(3)->create();

        $this->actingAs($this->user)
            ->getJson('/api/v1/fieldops/luminaire-frames')
            ->assertOk()
            ->assertJsonStructure(['success', 'data']);
    }

    public function test_index_does_not_include_luminaires(): void
    {
        LuminaireFrame::factory()->create();

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/fieldops/luminaire-frames')
            ->assertOk();

        $this->assertArrayNotHasKey('luminaires', $response->json('data.0'));
    }

    // ── STORE ─────────────────────────────────────────────────────────────

    public function test_store_creates_frame_201(): void
    {
        $frameType = LuminaireFrameType::factory()->create();

        $this->actingAs($this->user)
            ->postJson('/api/v1/fieldops/luminaire-frames', [
                'luminaire_frame_type_id' => $frameType->id,
            ])
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.frame_type.id', $frameType->id);

        $this->assertDatabaseHas('fo_luminaire_frames', [
            'luminaire_frame_type_id' => $frameType->id,
            'created_by_user_id'      => $this->user->id,
        ]);
    }

    public function test_store_attaches_structure_ids(): void
    {
        $frameType  = LuminaireFrameType::factory()->create();
        $structures = Structure::factory()->count(2)->create();

        $this->actingAs($this->user)
            ->postJson('/api/v1/fieldops/luminaire-frames', [
                'luminaire_frame_type_id' => $frameType->id,
                'structure_ids'           => $structures->pluck('id')->all(),
            ])
            ->assertCreated();

        $frame = LuminaireFrame::latest()->first();
        $this->assertCount(2, $frame->structures);
    }

    public function test_store_fails_without_frame_type(): void
    {
        $this->actingAs($this->user)
            ->postJson('/api/v1/fieldops/luminaire-frames', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['luminaire_frame_type_id']);
    }

    public function test_store_fails_with_duplicate_structure_ids(): void
    {
        $frameType = LuminaireFrameType::factory()->create();
        $structure = Structure::factory()->create();

        $this->actingAs($this->user)
            ->postJson('/api/v1/fieldops/luminaire-frames', [
                'luminaire_frame_type_id' => $frameType->id,
                'structure_ids'           => [$structure->id, $structure->id],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['structure_ids.0']);
    }

    // ── SHOW ──────────────────────────────────────────────────────────────

    public function test_show_returns_frame_with_luminaires(): void
    {
        $frame = LuminaireFrame::factory()->create();

        $this->actingAs($this->user)
            ->getJson("/api/v1/fieldops/luminaire-frames/{$frame->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $frame->id)
            ->assertJsonStructure(['data' => ['id', 'luminaires']]);
    }

    public function test_show_404_for_deleted(): void
    {
        $frame = LuminaireFrame::factory()->create();
        $frame->delete();

        $this->actingAs($this->user)
            ->getJson("/api/v1/fieldops/luminaire-frames/{$frame->id}")
            ->assertNotFound();
    }

    // ── UPDATE ────────────────────────────────────────────────────────────

    public function test_update_changes_frame_type(): void
    {
        $frame    = LuminaireFrame::factory()->create();
        $newType  = LuminaireFrameType::factory()->create();

        $this->actingAs($this->user)
            ->patchJson("/api/v1/fieldops/luminaire-frames/{$frame->id}", [
                'luminaire_frame_type_id' => $newType->id,
            ])
            ->assertOk()
            ->assertJsonPath('data.frame_type.id', $newType->id);
    }

    public function test_update_structure_ids_absent_does_not_change_pivots(): void
    {
        $frame     = LuminaireFrame::factory()->create();
        $structure = Structure::factory()->create();
        $frame->structures()->attach($structure->id);

        $this->actingAs($this->user)
            ->patchJson("/api/v1/fieldops/luminaire-frames/{$frame->id}", [
                'luminaire_frame_type_id' => $frame->luminaire_frame_type_id,
            ])
            ->assertOk();

        $this->assertCount(1, $frame->fresh()->structures);
    }

    public function test_update_structure_ids_null_detaches_all(): void
    {
        $frame     = LuminaireFrame::factory()->create();
        $structure = Structure::factory()->create();
        $frame->structures()->attach($structure->id);

        $this->actingAs($this->user)
            ->patchJson("/api/v1/fieldops/luminaire-frames/{$frame->id}", [
                'structure_ids' => null,
            ])
            ->assertOk();

        $this->assertCount(0, $frame->fresh()->structures);
    }

    public function test_update_structure_ids_array_syncs(): void
    {
        $frame      = LuminaireFrame::factory()->create();
        $oldStruct  = Structure::factory()->create();
        $newStruct  = Structure::factory()->create();
        $frame->structures()->attach($oldStruct->id);

        $this->actingAs($this->user)
            ->patchJson("/api/v1/fieldops/luminaire-frames/{$frame->id}", [
                'structure_ids' => [$newStruct->id],
            ])
            ->assertOk();

        $ids = $frame->fresh()->structures->pluck('id')->all();
        $this->assertContains($newStruct->id, $ids);
        $this->assertNotContains($oldStruct->id, $ids);
    }

    // ── DESTROY ───────────────────────────────────────────────────────────

    public function test_destroy_returns_204(): void
    {
        $frame = LuminaireFrame::factory()->create();

        $this->actingAs($this->user)
            ->deleteJson("/api/v1/fieldops/luminaire-frames/{$frame->id}")
            ->assertNoContent();
    }

    public function test_destroy_soft_deletes(): void
    {
        $frame = LuminaireFrame::factory()->create();
        $this->actingAs($this->user)
            ->deleteJson("/api/v1/fieldops/luminaire-frames/{$frame->id}");

        $this->assertSoftDeleted('fo_luminaire_frames', ['id' => $frame->id]);

        $this->actingAs($this->user)
            ->getJson("/api/v1/fieldops/luminaire-frames/{$frame->id}")
            ->assertNotFound();
    }
}
