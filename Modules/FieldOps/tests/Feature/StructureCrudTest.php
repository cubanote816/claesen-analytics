<?php

declare(strict_types=1);

namespace Modules\FieldOps\Tests\Feature;

use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\FieldOps\Models\AccessType;
use Modules\FieldOps\Models\Complex;
use Modules\FieldOps\Models\SafetyType;
use Modules\FieldOps\Models\Structure;
use Modules\FieldOps\Models\StructureType;
use Modules\FieldOps\Models\Terrain;
use Modules\FieldOps\Models\TerrainType;
use Modules\Intelligence\Services\GeminiService;
use Tests\TestCase;

class StructureCrudTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mock(GeminiService::class, fn ($m) => $m->shouldReceive('translateAndDetect')->andReturn(['translations' => [], 'detected_locale' => 'nl']));
    }

    private function user(): array
    {
        $user  = UserFactory::new()->create();
        $token = $user->createToken('test')->plainTextToken;

        return [$user, $token];
    }

    private function structureType(): StructureType
    {
        return StructureType::factory()->create();
    }

    private function terrain(): Terrain
    {
        return Terrain::factory()->create();
    }

    private function accessType(): AccessType
    {
        return AccessType::factory()->create();
    }

    private function safetyType(): SafetyType
    {
        return SafetyType::factory()->create();
    }

    // ── store ─────────────────────────────────────────────────────────────────

    public function test_store_creates_structure_and_returns_201(): void
    {
        [, $token] = $this->user();
        $type = $this->structureType();

        $response = $this->withToken($token)->postJson('/api/v1/fieldops/structures', [
            'structure_type_id' => $type->id,
            'height'            => 8,
            'lat'               => 50.85,
            'lng'               => 4.35,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.height', 8);

        $this->assertDatabaseHas('fo_structures', ['height' => 8]);
    }

    public function test_store_injects_created_by_user_id(): void
    {
        [$user, $token] = $this->user();

        $this->withToken($token)->postJson('/api/v1/fieldops/structures', [
            'structure_type_id' => $this->structureType()->id,
        ]);

        $this->assertDatabaseHas('fo_structures', ['created_by_user_id' => $user->id]);
    }

    public function test_store_requires_structure_type_id(): void
    {
        [, $token] = $this->user();

        $this->withToken($token)->postJson('/api/v1/fieldops/structures', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors('structure_type_id');
    }

    public function test_store_rejects_nonexistent_structure_type_id(): void
    {
        [, $token] = $this->user();

        $this->withToken($token)->postJson('/api/v1/fieldops/structures', [
            'structure_type_id' => 99999,
        ])->assertStatus(422)
            ->assertJsonValidationErrors('structure_type_id');
    }

    public function test_store_rejects_invalid_info_locale_key(): void
    {
        [, $token] = $this->user();

        $this->withToken($token)->postJson('/api/v1/fieldops/structures', [
            'structure_type_id' => $this->structureType()->id,
            'info'              => ['nl' => 'OK', 'es' => 'Prohibido'],
        ])->assertStatus(422);
    }

    public function test_store_attaches_terrain_ids(): void
    {
        [, $token] = $this->user();
        $terrain = $this->terrain();

        $response = $this->withToken($token)->postJson('/api/v1/fieldops/structures', [
            'structure_type_id' => $this->structureType()->id,
            'terrain_ids'       => [$terrain->id],
        ]);

        $response->assertStatus(201);
        $structureId = $response->json('data.id');

        $this->assertDatabaseHas('fo_structure_terrain', [
            'structure_id' => $structureId,
            'terrain_id'   => $terrain->id,
        ]);
    }

    public function test_store_rejects_duplicate_terrain_ids(): void
    {
        [, $token] = $this->user();
        $terrain = $this->terrain();

        $this->withToken($token)->postJson('/api/v1/fieldops/structures', [
            'structure_type_id' => $this->structureType()->id,
            'terrain_ids'       => [$terrain->id, $terrain->id],
        ])->assertStatus(422)
            ->assertJsonValidationErrors('terrain_ids.1');
    }

    public function test_store_rejects_nonexistent_terrain_id(): void
    {
        [, $token] = $this->user();

        $this->withToken($token)->postJson('/api/v1/fieldops/structures', [
            'structure_type_id' => $this->structureType()->id,
            'terrain_ids'       => [99999],
        ])->assertStatus(422)
            ->assertJsonValidationErrors('terrain_ids.0');
    }

    public function test_store_without_terrain_ids_creates_structure_without_pivot_rows(): void
    {
        [, $token] = $this->user();

        $response = $this->withToken($token)->postJson('/api/v1/fieldops/structures', [
            'structure_type_id' => $this->structureType()->id,
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseCount('fo_structure_terrain', 0);
    }

    public function test_store_requires_authentication(): void
    {
        $this->postJson('/api/v1/fieldops/structures', [
            'structure_type_id' => $this->structureType()->id,
        ])->assertStatus(401);
    }

    public function test_store_sets_access_and_safety(): void
    {
        [, $token] = $this->user();
        $access = $this->accessType();
        $safety = $this->safetyType();

        $response = $this->withToken($token)->postJson('/api/v1/fieldops/structures', [
            'structure_type_id' => $this->structureType()->id,
            'access_type_id'    => $access->id,
            'access_active'     => true,
            'safety_type_id'    => $safety->id,
            'safety_certified'  => true,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.access_type.id', $access->id)
            ->assertJsonPath('data.access_active', true)
            ->assertJsonPath('data.safety_type.id', $safety->id)
            ->assertJsonPath('data.safety_certified', true);

        $this->assertDatabaseHas('fo_structures', [
            'access_type_id'   => $access->id,
            'access_active'    => true,
            'safety_type_id'   => $safety->id,
            'safety_certified' => true,
        ]);
    }

    public function test_store_rejects_nonexistent_access_type_id(): void
    {
        [, $token] = $this->user();

        $this->withToken($token)->postJson('/api/v1/fieldops/structures', [
            'structure_type_id' => $this->structureType()->id,
            'access_type_id'    => 99999,
        ])->assertStatus(422)
            ->assertJsonValidationErrors('access_type_id');
    }

    public function test_store_rejects_nonexistent_safety_type_id(): void
    {
        [, $token] = $this->user();

        $this->withToken($token)->postJson('/api/v1/fieldops/structures', [
            'structure_type_id' => $this->structureType()->id,
            'safety_type_id'    => 99999,
        ])->assertStatus(422)
            ->assertJsonValidationErrors('safety_type_id');
    }

    public function test_store_defaults_access_and_safety_to_false(): void
    {
        [, $token] = $this->user();

        $response = $this->withToken($token)->postJson('/api/v1/fieldops/structures', [
            'structure_type_id' => $this->structureType()->id,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.access_active', false)
            ->assertJsonPath('data.safety_certified', false);
    }

    // ── update ────────────────────────────────────────────────────────────────

    public function test_update_patches_only_sent_fields(): void
    {
        [, $token] = $this->user();
        $structure = Structure::factory()->create(['height' => 5]);

        $this->withToken($token)->patchJson("/api/v1/fieldops/structures/{$structure->id}", [
            'height' => 10,
        ])->assertStatus(200)
            ->assertJsonPath('data.height', 10);

        $this->assertDatabaseHas('fo_structures', ['id' => $structure->id, 'height' => 10]);
    }

    public function test_update_via_put_also_works(): void
    {
        [, $token] = $this->user();
        $structure = Structure::factory()->create();

        $this->withToken($token)->putJson("/api/v1/fieldops/structures/{$structure->id}", [
            'height' => 7,
        ])->assertStatus(200);
    }

    public function test_update_info_nl_does_not_overwrite_other_locales(): void
    {
        [, $token] = $this->user();
        $structure = Structure::factory()->create([
            'info' => ['nl' => 'Oud NL', 'en' => 'Old EN', 'fr' => 'Ancien FR'],
        ]);

        $this->withToken($token)->patchJson("/api/v1/fieldops/structures/{$structure->id}", [
            'info' => ['nl' => 'Nieuw NL'],
        ])->assertStatus(200)
            ->assertJsonPath('data.info.nl', 'Nieuw NL')
            ->assertJsonPath('data.info.en', 'Old EN')
            ->assertJsonPath('data.info.fr', 'Ancien FR');
    }

    public function test_update_rejects_invalid_info_locale_key(): void
    {
        [, $token] = $this->user();
        $structure = Structure::factory()->create();

        $this->withToken($token)->patchJson("/api/v1/fieldops/structures/{$structure->id}", [
            'info' => ['nl' => 'OK', 'foo' => 'Ongeldig'],
        ])->assertStatus(422);
    }

    public function test_update_created_by_user_id_is_not_editable(): void
    {
        [$user, $token] = $this->user();
        $other     = UserFactory::new()->create();
        $structure = Structure::factory()->create(['created_by_user_id' => $user->id]);

        $this->withToken($token)->patchJson("/api/v1/fieldops/structures/{$structure->id}", [
            'created_by_user_id' => $other->id,
        ])->assertStatus(200);

        $this->assertDatabaseHas('fo_structures', [
            'id'                 => $structure->id,
            'created_by_user_id' => $user->id,
        ]);
    }

    // terrain_ids triple case ─────────────────────────────────────────────────

    public function test_update_absent_terrain_ids_leaves_pivot_untouched(): void
    {
        [, $token] = $this->user();
        $terrain   = $this->terrain();
        $structure = Structure::factory()->create();
        $structure->terrains()->attach($terrain->id);

        // Send update WITHOUT terrain_ids key
        $this->withToken($token)->patchJson("/api/v1/fieldops/structures/{$structure->id}", [
            'height' => 99,
        ])->assertStatus(200);

        $this->assertDatabaseHas('fo_structure_terrain', [
            'structure_id' => $structure->id,
            'terrain_id'   => $terrain->id,
        ]);
    }

    public function test_update_null_terrain_ids_detaches_all(): void
    {
        [, $token] = $this->user();
        $terrain   = $this->terrain();
        $structure = Structure::factory()->create();
        $structure->terrains()->attach($terrain->id);

        $this->withToken($token)->patchJson("/api/v1/fieldops/structures/{$structure->id}", [
            'terrain_ids' => null,
        ])->assertStatus(200);

        $this->assertDatabaseMissing('fo_structure_terrain', [
            'structure_id' => $structure->id,
        ]);
    }

    public function test_update_empty_array_terrain_ids_detaches_all(): void
    {
        [, $token] = $this->user();
        $terrain   = $this->terrain();
        $structure = Structure::factory()->create();
        $structure->terrains()->attach($terrain->id);

        $this->withToken($token)->patchJson("/api/v1/fieldops/structures/{$structure->id}", [
            'terrain_ids' => [],
        ])->assertStatus(200);

        $this->assertDatabaseMissing('fo_structure_terrain', [
            'structure_id' => $structure->id,
        ]);
    }

    public function test_update_terrain_ids_array_syncs_pivot(): void
    {
        [, $token] = $this->user();
        $terrainA  = $this->terrain();
        $terrainB  = $this->terrain();
        $terrainC  = $this->terrain();
        $structure = Structure::factory()->create();
        $structure->terrains()->attach([$terrainA->id, $terrainB->id]);

        // Sync to [B, C] — A should be removed, C should be added
        $this->withToken($token)->patchJson("/api/v1/fieldops/structures/{$structure->id}", [
            'terrain_ids' => [$terrainB->id, $terrainC->id],
        ])->assertStatus(200);

        $this->assertDatabaseMissing('fo_structure_terrain', [
            'structure_id' => $structure->id,
            'terrain_id'   => $terrainA->id,
        ]);
        $this->assertDatabaseHas('fo_structure_terrain', [
            'structure_id' => $structure->id,
            'terrain_id'   => $terrainB->id,
        ]);
        $this->assertDatabaseHas('fo_structure_terrain', [
            'structure_id' => $structure->id,
            'terrain_id'   => $terrainC->id,
        ]);
    }

    public function test_update_rejects_duplicate_terrain_ids(): void
    {
        [, $token] = $this->user();
        $terrain   = $this->terrain();
        $structure = Structure::factory()->create();

        $this->withToken($token)->patchJson("/api/v1/fieldops/structures/{$structure->id}", [
            'terrain_ids' => [$terrain->id, $terrain->id],
        ])->assertStatus(422)
            ->assertJsonValidationErrors('terrain_ids.1');
    }

    public function test_update_changes_access_and_safety(): void
    {
        [, $token] = $this->user();
        $structure = Structure::factory()->create();
        $access    = $this->accessType();
        $safety    = $this->safetyType();

        $this->withToken($token)->patchJson("/api/v1/fieldops/structures/{$structure->id}", [
            'access_type_id'   => $access->id,
            'access_active'    => true,
            'safety_type_id'   => $safety->id,
            'safety_certified' => true,
        ])->assertStatus(200)
            ->assertJsonPath('data.access_type.id', $access->id)
            ->assertJsonPath('data.access_active', true)
            ->assertJsonPath('data.safety_type.id', $safety->id)
            ->assertJsonPath('data.safety_certified', true);
    }

    public function test_update_rejects_nonexistent_access_type_id(): void
    {
        [, $token] = $this->user();
        $structure = Structure::factory()->create();

        $this->withToken($token)->patchJson("/api/v1/fieldops/structures/{$structure->id}", [
            'access_type_id' => 99999,
        ])->assertStatus(422)
            ->assertJsonValidationErrors('access_type_id');
    }

    public function test_update_returns_404_for_missing_structure(): void
    {
        [, $token] = $this->user();

        $this->withToken($token)->patchJson('/api/v1/fieldops/structures/99999', [
            'height' => 5,
        ])->assertStatus(404);
    }

    public function test_update_requires_authentication(): void
    {
        $structure = Structure::factory()->create();

        $this->patchJson("/api/v1/fieldops/structures/{$structure->id}", ['height' => 5])
            ->assertStatus(401);
    }

    // ── destroy ───────────────────────────────────────────────────────────────

    public function test_destroy_returns_204(): void
    {
        [, $token] = $this->user();
        $structure = Structure::factory()->create();

        $this->withToken($token)->deleteJson("/api/v1/fieldops/structures/{$structure->id}")
            ->assertStatus(204);
    }

    public function test_destroy_soft_deletes_record(): void
    {
        [, $token] = $this->user();
        $structure = Structure::factory()->create();

        $this->withToken($token)->deleteJson("/api/v1/fieldops/structures/{$structure->id}");

        $this->assertSoftDeleted('fo_structures', ['id' => $structure->id]);
    }

    public function test_destroy_then_get_returns_404(): void
    {
        [, $token] = $this->user();
        $structure = Structure::factory()->create();

        $this->withToken($token)->deleteJson("/api/v1/fieldops/structures/{$structure->id}")
            ->assertStatus(204);

        $this->withToken($token)->getJson("/api/v1/fieldops/structures/{$structure->id}")
            ->assertStatus(404);
    }

    public function test_destroy_already_deleted_returns_404(): void
    {
        [, $token] = $this->user();
        $structure = Structure::factory()->create();
        $structure->delete();

        $this->withToken($token)->deleteJson("/api/v1/fieldops/structures/{$structure->id}")
            ->assertStatus(404);
    }

    public function test_destroy_requires_authentication(): void
    {
        $structure = Structure::factory()->create();

        $this->deleteJson("/api/v1/fieldops/structures/{$structure->id}")
            ->assertStatus(401);
    }

    // ── show vs index shape ───────────────────────────────────────────────────

    public function test_show_includes_luminaire_frames_key_index_does_not(): void
    {
        [, $token] = $this->user();
        $structure = Structure::factory()->create();

        $showResponse  = $this->withToken($token)->getJson("/api/v1/fieldops/structures/{$structure->id}");
        $indexResponse = $this->withToken($token)->getJson('/api/v1/fieldops/structures');

        $showResponse->assertStatus(200)->assertJsonStructure(['data' => ['luminaire_frames']]);

        $firstIndex = $indexResponse->json('data.0');
        $this->assertArrayNotHasKey('luminaire_frames', $firstIndex ?? []);
    }

    // ── index filter: terrain_id ──────────────────────────────────────────────

    public function test_index_filters_by_terrain_id(): void
    {
        [, $token] = $this->user();
        $terrainA  = Terrain::factory()->create();
        $terrainB  = Terrain::factory()->create();

        $structureA = Structure::factory()->create();
        $structureB = Structure::factory()->create();
        $structureA->terrains()->attach($terrainA->id);
        $structureB->terrains()->attach($terrainB->id);

        $response = $this->withToken($token)
            ->getJson("/api/v1/fieldops/structures?terrain_id={$terrainA->id}")
            ->assertOk();

        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertContains($structureA->id, $ids);
        $this->assertNotContains($structureB->id, $ids);
    }

    public function test_index_without_terrain_id_returns_all(): void
    {
        [, $token] = $this->user();
        $terrainA  = Terrain::factory()->create();
        $terrainB  = Terrain::factory()->create();

        $structureA = Structure::factory()->create();
        $structureB = Structure::factory()->create();
        $structureA->terrains()->attach($terrainA->id);
        $structureB->terrains()->attach($terrainB->id);

        $response = $this->withToken($token)
            ->getJson('/api/v1/fieldops/structures')
            ->assertOk();

        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertContains($structureA->id, $ids);
        $this->assertContains($structureB->id, $ids);
    }

    public function test_terrain_id_filter_excludes_unlinked_structures(): void
    {
        [, $token] = $this->user();
        $terrain    = Terrain::factory()->create();

        $linked   = Structure::factory()->create();
        $unlinked = Structure::factory()->create();
        $linked->terrains()->attach($terrain->id);

        $response = $this->withToken($token)
            ->getJson("/api/v1/fieldops/structures?terrain_id={$terrain->id}")
            ->assertOk();

        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertContains($linked->id, $ids);
        $this->assertNotContains($unlinked->id, $ids);
    }
}
