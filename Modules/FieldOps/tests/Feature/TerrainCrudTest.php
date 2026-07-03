<?php

declare(strict_types=1);

namespace Modules\FieldOps\Tests\Feature;

use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\FieldOps\Models\Complex;
use Modules\FieldOps\Models\Terrain;
use Modules\FieldOps\Models\TerrainType;
use Modules\Intelligence\Services\GeminiService;
use Tests\TestCase;

class TerrainCrudTest extends TestCase
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

    private function payload(array $overrides = []): array
    {
        $complex     = Complex::factory()->create();
        $terrainType = TerrainType::factory()->create();

        return array_merge([
            'complex_id'      => $complex->id,
            'terrain_type_id' => $terrainType->id,
            'name'            => ['nl' => 'Terrein A', 'en' => 'Terrain A'],
            'lat'             => 50.8500,
            'lng'             => 4.3500,
        ], $overrides);
    }

    // ── store ─────────────────────────────────────────────────────────────────

    public function test_store_creates_terrain_and_returns_201(): void
    {
        [, $token] = $this->user();

        $response = $this->withToken($token)->postJson('/api/v1/fieldops/terrains', $this->payload());

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name.nl', 'Terrein A')
            ->assertJsonPath('data.name.en', 'Terrain A');

        $this->assertDatabaseHas('fo_terrains', ['lat' => 50.8500]);
    }

    public function test_store_injects_created_by_user_id(): void
    {
        [$user, $token] = $this->user();

        $this->withToken($token)->postJson('/api/v1/fieldops/terrains', $this->payload());

        $this->assertDatabaseHas('fo_terrains', ['created_by_user_id' => $user->id]);
    }

    public function test_store_requires_complex_id(): void
    {
        [, $token] = $this->user();
        $payload = $this->payload();
        unset($payload['complex_id']);

        $this->withToken($token)->postJson('/api/v1/fieldops/terrains', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors('complex_id');
    }

    public function test_store_requires_terrain_type_id(): void
    {
        [, $token] = $this->user();
        $payload = $this->payload();
        unset($payload['terrain_type_id']);

        $this->withToken($token)->postJson('/api/v1/fieldops/terrains', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors('terrain_type_id');
    }

    public function test_store_rejects_nonexistent_complex_id(): void
    {
        [, $token] = $this->user();

        $this->withToken($token)->postJson('/api/v1/fieldops/terrains', $this->payload(['complex_id' => 99999]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('complex_id');
    }

    public function test_store_rejects_nonexistent_terrain_type_id(): void
    {
        [, $token] = $this->user();

        $this->withToken($token)->postJson('/api/v1/fieldops/terrains', $this->payload(['terrain_type_id' => 99999]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('terrain_type_id');
    }

    public function test_store_with_null_name_returns_201(): void
    {
        [, $token] = $this->user();

        $this->withToken($token)->postJson('/api/v1/fieldops/terrains', $this->payload(['name' => null]))
            ->assertStatus(201)
            ->assertJsonPath('data.name', []);
    }

    public function test_store_rejects_invalid_locale_key(): void
    {
        [, $token] = $this->user();

        $this->withToken($token)->postJson('/api/v1/fieldops/terrains', $this->payload([
            'name' => ['nl' => 'Test', 'de' => 'Ungültig'],
        ]))->assertStatus(422);
    }

    public function test_store_requires_authentication(): void
    {
        $this->postJson('/api/v1/fieldops/terrains', $this->payload())
            ->assertStatus(401);
    }

    public function test_store_response_shape_matches_show(): void
    {
        [, $token] = $this->user();

        $storeResponse = $this->withToken($token)->postJson('/api/v1/fieldops/terrains', $this->payload());
        $storeResponse->assertStatus(201);

        $id           = $storeResponse->json('data.id');
        $showResponse = $this->withToken($token)->getJson("/api/v1/fieldops/terrains/{$id}");
        $showResponse->assertStatus(200);

        $storeKeys = array_keys($storeResponse->json('data'));
        $showKeys  = array_keys($showResponse->json('data'));

        foreach ($storeKeys as $key) {
            $this->assertContains($key, $showKeys, "Key '{$key}' present in store but missing in show");
        }
    }

    // ── update ────────────────────────────────────────────────────────────────

    public function test_update_patches_only_sent_fields(): void
    {
        [, $token] = $this->user();
        $newType = TerrainType::factory()->create();
        $terrain = Terrain::factory()->create();

        $this->withToken($token)->patchJson("/api/v1/fieldops/terrains/{$terrain->id}", [
            'terrain_type_id' => $newType->id,
        ])->assertStatus(200)
            ->assertJsonPath('data.terrain_type.id', $newType->id);

        $this->assertDatabaseHas('fo_terrains', [
            'id'              => $terrain->id,
            'terrain_type_id' => $newType->id,
        ]);
    }

    public function test_update_via_put_also_works(): void
    {
        [, $token] = $this->user();
        $newType = TerrainType::factory()->create();
        $terrain = Terrain::factory()->create();

        $this->withToken($token)->putJson("/api/v1/fieldops/terrains/{$terrain->id}", [
            'terrain_type_id' => $newType->id,
        ])->assertStatus(200);
    }

    public function test_update_patch_name_nl_does_not_overwrite_other_locales(): void
    {
        [, $token] = $this->user();
        $terrain = Terrain::factory()->create([
            'name' => ['nl' => 'Oud NL', 'en' => 'Old EN', 'fr' => 'Ancien FR'],
        ]);

        $this->withToken($token)->patchJson("/api/v1/fieldops/terrains/{$terrain->id}", [
            'name' => ['nl' => 'Nieuw NL'],
        ])->assertStatus(200)
            ->assertJsonPath('data.name.nl', 'Nieuw NL')
            ->assertJsonPath('data.name.en', 'Old EN')
            ->assertJsonPath('data.name.fr', 'Ancien FR');
    }

    public function test_update_rejects_invalid_locale_key(): void
    {
        [, $token] = $this->user();
        $terrain = Terrain::factory()->create();

        $this->withToken($token)->patchJson("/api/v1/fieldops/terrains/{$terrain->id}", [
            'name' => ['nl' => 'Geldig', 'foo' => 'Ongeldig'],
        ])->assertStatus(422);
    }

    public function test_update_complex_id_is_ignored(): void
    {
        [, $token] = $this->user();
        $terrain    = Terrain::factory()->create();
        $otherComplex = Complex::factory()->create();
        $originalComplexId = $terrain->complex_id;

        $this->withToken($token)->patchJson("/api/v1/fieldops/terrains/{$terrain->id}", [
            'complex_id' => $otherComplex->id,
        ])->assertStatus(200);

        $this->assertDatabaseHas('fo_terrains', [
            'id'         => $terrain->id,
            'complex_id' => $originalComplexId,
        ]);
    }

    public function test_update_returns_404_for_missing_terrain(): void
    {
        [, $token] = $this->user();

        $this->withToken($token)->patchJson('/api/v1/fieldops/terrains/99999', [
            'lat' => 51.0,
        ])->assertStatus(404);
    }

    public function test_update_requires_authentication(): void
    {
        $terrain = Terrain::factory()->create();

        $this->patchJson("/api/v1/fieldops/terrains/{$terrain->id}", ['lat' => 51.0])
            ->assertStatus(401);
    }

    // ── destroy ───────────────────────────────────────────────────────────────

    public function test_destroy_returns_204(): void
    {
        [, $token] = $this->user();
        $terrain = Terrain::factory()->create();

        $this->withToken($token)->deleteJson("/api/v1/fieldops/terrains/{$terrain->id}")
            ->assertStatus(204);
    }

    public function test_destroy_soft_deletes_record(): void
    {
        [, $token] = $this->user();
        $terrain = Terrain::factory()->create();

        $this->withToken($token)->deleteJson("/api/v1/fieldops/terrains/{$terrain->id}");

        $this->assertSoftDeleted('fo_terrains', ['id' => $terrain->id]);
    }

    public function test_destroy_then_get_returns_404(): void
    {
        [, $token] = $this->user();
        $terrain = Terrain::factory()->create();

        $this->withToken($token)->deleteJson("/api/v1/fieldops/terrains/{$terrain->id}")
            ->assertStatus(204);

        $this->withToken($token)->getJson("/api/v1/fieldops/terrains/{$terrain->id}")
            ->assertStatus(404);
    }

    public function test_destroy_already_deleted_returns_404(): void
    {
        [, $token] = $this->user();
        $terrain = Terrain::factory()->create();
        $terrain->delete();

        $this->withToken($token)->deleteJson("/api/v1/fieldops/terrains/{$terrain->id}")
            ->assertStatus(404);
    }

    public function test_destroy_requires_authentication(): void
    {
        $terrain = Terrain::factory()->create();

        $this->deleteJson("/api/v1/fieldops/terrains/{$terrain->id}")
            ->assertStatus(401);
    }

    public function test_destroyed_terrain_not_in_index(): void
    {
        [, $token] = $this->user();
        $complex  = Complex::factory()->create();
        $terrain  = Terrain::factory()->create([
            'complex_id' => $complex->id,
            'name'       => ['nl' => 'Te verwijderen'],
        ]);

        $this->withToken($token)->deleteJson("/api/v1/fieldops/terrains/{$terrain->id}");

        $response = $this->withToken($token)->getJson("/api/v1/fieldops/terrains?complex_id={$complex->id}");
        $ids      = collect($response->json('data'))->pluck('id')->all();

        $this->assertNotContains($terrain->id, $ids);
    }

    // ── index filter ──────────────────────────────────────────────────────────

    public function test_index_filters_by_complex_id(): void
    {
        [, $token] = $this->user();
        $complexA  = Complex::factory()->create();
        $complexB  = Complex::factory()->create();

        $terrainA = Terrain::factory()->create(['complex_id' => $complexA->id]);
        Terrain::factory()->create(['complex_id' => $complexB->id]);

        $response = $this->withToken($token)->getJson("/api/v1/fieldops/terrains?complex_id={$complexA->id}");
        $response->assertStatus(200);

        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertContains($terrainA->id, $ids);
        $this->assertCount(1, $ids);
    }
}
