<?php

declare(strict_types=1);

namespace Modules\FieldOps\Tests\Feature;

use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\FieldOps\Models\Complex;
use Modules\FieldOps\Models\ElectricalBoard;
use Modules\FieldOps\Models\ElectricalBoardType;
use Modules\FieldOps\Models\Structure;
use Modules\FieldOps\Models\Terrain;
use Modules\Intelligence\Services\GeminiService;
use Tests\TestCase;

class ElectricalBoardCrudTest extends TestCase
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

    private function boardType(): ElectricalBoardType
    {
        return ElectricalBoardType::factory()->create();
    }

    // ── store ─────────────────────────────────────────────────────────────────

    public function test_store_creates_electrical_board_and_returns_201(): void
    {
        [, $token] = $this->user();
        $type = $this->boardType();

        $response = $this->withToken($token)->postJson('/api/v1/fieldops/electrical-boards', [
            'electrical_board_type_id' => $type->id,
            'lat'                      => 50.85,
            'lng'                      => 4.35,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.lat', 50.85);

        $this->assertDatabaseHas('fo_electrical_boards', ['lat' => 50.85]);
    }

    public function test_store_injects_created_by_user_id(): void
    {
        [$user, $token] = $this->user();

        $this->withToken($token)->postJson('/api/v1/fieldops/electrical-boards', [
            'electrical_board_type_id' => $this->boardType()->id,
        ]);

        $this->assertDatabaseHas('fo_electrical_boards', ['created_by_user_id' => $user->id]);
    }

    public function test_store_requires_electrical_board_type_id(): void
    {
        [, $token] = $this->user();

        $this->withToken($token)->postJson('/api/v1/fieldops/electrical-boards', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors('electrical_board_type_id');
    }

    public function test_store_rejects_nonexistent_electrical_board_type_id(): void
    {
        [, $token] = $this->user();

        $this->withToken($token)->postJson('/api/v1/fieldops/electrical-boards', [
            'electrical_board_type_id' => 99999,
        ])->assertStatus(422)
            ->assertJsonValidationErrors('electrical_board_type_id');
    }

    public function test_store_rejects_invalid_location_description_locale_key(): void
    {
        [, $token] = $this->user();

        $this->withToken($token)->postJson('/api/v1/fieldops/electrical-boards', [
            'electrical_board_type_id' => $this->boardType()->id,
            'location_description'     => ['nl' => 'OK', 'es' => 'Prohibido'],
        ])->assertStatus(422);
    }

    public function test_store_attaches_complex_terrain_and_structure_ids(): void
    {
        [, $token] = $this->user();
        $complex   = Complex::factory()->create();
        $terrain   = Terrain::factory()->create();
        $structure = Structure::factory()->create();

        $response = $this->withToken($token)->postJson('/api/v1/fieldops/electrical-boards', [
            'electrical_board_type_id' => $this->boardType()->id,
            'complex_ids'              => [$complex->id],
            'terrain_ids'              => [$terrain->id],
            'structure_ids'            => [$structure->id],
        ]);

        $response->assertStatus(201);
        $boardId = $response->json('data.id');

        $this->assertDatabaseHas('fo_complex_electrical_board', ['electrical_board_id' => $boardId, 'complex_id' => $complex->id]);
        $this->assertDatabaseHas('fo_electrical_board_terrain', ['electrical_board_id' => $boardId, 'terrain_id' => $terrain->id]);
        $this->assertDatabaseHas('fo_electrical_board_structure', ['electrical_board_id' => $boardId, 'structure_id' => $structure->id]);
    }

    public function test_store_rejects_nonexistent_complex_id(): void
    {
        [, $token] = $this->user();

        $this->withToken($token)->postJson('/api/v1/fieldops/electrical-boards', [
            'electrical_board_type_id' => $this->boardType()->id,
            'complex_ids'              => [99999],
        ])->assertStatus(422)
            ->assertJsonValidationErrors('complex_ids.0');
    }

    public function test_store_without_pivot_ids_creates_board_without_pivot_rows(): void
    {
        [, $token] = $this->user();

        $response = $this->withToken($token)->postJson('/api/v1/fieldops/electrical-boards', [
            'electrical_board_type_id' => $this->boardType()->id,
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseCount('fo_complex_electrical_board', 0);
        $this->assertDatabaseCount('fo_electrical_board_terrain', 0);
        $this->assertDatabaseCount('fo_electrical_board_structure', 0);
    }

    public function test_store_requires_authentication(): void
    {
        $this->postJson('/api/v1/fieldops/electrical-boards', [
            'electrical_board_type_id' => $this->boardType()->id,
        ])->assertStatus(401);
    }

    // ── update ────────────────────────────────────────────────────────────────

    public function test_update_patches_only_sent_fields(): void
    {
        [, $token] = $this->user();
        $board = ElectricalBoard::factory()->create(['lat' => 50.0]);

        $this->withToken($token)->patchJson("/api/v1/fieldops/electrical-boards/{$board->id}", [
            'lat' => 51.25,
        ])->assertStatus(200)
            ->assertJsonPath('data.lat', 51.25);

        $this->assertDatabaseHas('fo_electrical_boards', ['id' => $board->id, 'lat' => 51.25]);
    }

    public function test_update_location_description_nl_does_not_overwrite_other_locales(): void
    {
        [, $token] = $this->user();
        $board = ElectricalBoard::factory()->create([
            'location_description' => ['nl' => 'Oud NL', 'en' => 'Old EN'],
        ]);

        $this->withToken($token)->patchJson("/api/v1/fieldops/electrical-boards/{$board->id}", [
            'location_description' => ['nl' => 'Nieuw NL'],
        ])->assertStatus(200)
            ->assertJsonPath('data.location_description.nl', 'Nieuw NL')
            ->assertJsonPath('data.location_description.en', 'Old EN');
    }

    public function test_update_created_by_user_id_is_not_editable(): void
    {
        [$user, $token] = $this->user();
        $other = UserFactory::new()->create();
        $board = ElectricalBoard::factory()->create(['created_by_user_id' => $user->id]);

        $this->withToken($token)->patchJson("/api/v1/fieldops/electrical-boards/{$board->id}", [
            'created_by_user_id' => $other->id,
        ])->assertStatus(200);

        $this->assertDatabaseHas('fo_electrical_boards', [
            'id'                 => $board->id,
            'created_by_user_id' => $user->id,
        ]);
    }

    // pivot triple-case, tested in depth for complex_ids only (terrain/structure share the same code path)

    public function test_update_absent_complex_ids_leaves_pivot_untouched(): void
    {
        [, $token] = $this->user();
        $complex = Complex::factory()->create();
        $board   = ElectricalBoard::factory()->create();
        $board->complexes()->attach($complex->id);

        $this->withToken($token)->patchJson("/api/v1/fieldops/electrical-boards/{$board->id}", [
            'lat' => 51.0,
        ])->assertStatus(200);

        $this->assertDatabaseHas('fo_complex_electrical_board', [
            'electrical_board_id' => $board->id,
            'complex_id'          => $complex->id,
        ]);
    }

    public function test_update_null_complex_ids_detaches_all(): void
    {
        [, $token] = $this->user();
        $complex = Complex::factory()->create();
        $board   = ElectricalBoard::factory()->create();
        $board->complexes()->attach($complex->id);

        $this->withToken($token)->patchJson("/api/v1/fieldops/electrical-boards/{$board->id}", [
            'complex_ids' => null,
        ])->assertStatus(200);

        $this->assertDatabaseMissing('fo_complex_electrical_board', [
            'electrical_board_id' => $board->id,
        ]);
    }

    public function test_update_complex_ids_array_syncs_pivot(): void
    {
        [, $token] = $this->user();
        $complexA = Complex::factory()->create();
        $complexB = Complex::factory()->create();
        $board    = ElectricalBoard::factory()->create();
        $board->complexes()->attach($complexA->id);

        $this->withToken($token)->patchJson("/api/v1/fieldops/electrical-boards/{$board->id}", [
            'complex_ids' => [$complexB->id],
        ])->assertStatus(200);

        $this->assertDatabaseMissing('fo_complex_electrical_board', [
            'electrical_board_id' => $board->id,
            'complex_id'          => $complexA->id,
        ]);
        $this->assertDatabaseHas('fo_complex_electrical_board', [
            'electrical_board_id' => $board->id,
            'complex_id'          => $complexB->id,
        ]);
    }

    public function test_update_syncs_terrain_ids(): void
    {
        [, $token] = $this->user();
        $terrainA = Terrain::factory()->create();
        $terrainB = Terrain::factory()->create();
        $board    = ElectricalBoard::factory()->create();
        $board->terrains()->attach($terrainA->id);

        $this->withToken($token)->patchJson("/api/v1/fieldops/electrical-boards/{$board->id}", [
            'terrain_ids' => [$terrainB->id],
        ])->assertStatus(200);

        $this->assertDatabaseMissing('fo_electrical_board_terrain', [
            'electrical_board_id' => $board->id,
            'terrain_id'          => $terrainA->id,
        ]);
        $this->assertDatabaseHas('fo_electrical_board_terrain', [
            'electrical_board_id' => $board->id,
            'terrain_id'          => $terrainB->id,
        ]);
    }

    public function test_update_syncs_structure_ids(): void
    {
        [, $token] = $this->user();
        $structureA = Structure::factory()->create();
        $structureB = Structure::factory()->create();
        $board      = ElectricalBoard::factory()->create();
        $board->structures()->attach($structureA->id);

        $this->withToken($token)->patchJson("/api/v1/fieldops/electrical-boards/{$board->id}", [
            'structure_ids' => [$structureB->id],
        ])->assertStatus(200);

        $this->assertDatabaseMissing('fo_electrical_board_structure', [
            'electrical_board_id' => $board->id,
            'structure_id'        => $structureA->id,
        ]);
        $this->assertDatabaseHas('fo_electrical_board_structure', [
            'electrical_board_id' => $board->id,
            'structure_id'        => $structureB->id,
        ]);
    }

    public function test_update_returns_404_for_missing_board(): void
    {
        [, $token] = $this->user();

        $this->withToken($token)->patchJson('/api/v1/fieldops/electrical-boards/99999', [
            'lat' => 5,
        ])->assertStatus(404);
    }

    public function test_update_requires_authentication(): void
    {
        $board = ElectricalBoard::factory()->create();

        $this->patchJson("/api/v1/fieldops/electrical-boards/{$board->id}", ['lat' => 5])
            ->assertStatus(401);
    }

    // ── destroy ───────────────────────────────────────────────────────────────

    public function test_destroy_returns_204(): void
    {
        [, $token] = $this->user();
        $board = ElectricalBoard::factory()->create();

        $this->withToken($token)->deleteJson("/api/v1/fieldops/electrical-boards/{$board->id}")
            ->assertStatus(204);
    }

    public function test_destroy_soft_deletes_record(): void
    {
        [, $token] = $this->user();
        $board = ElectricalBoard::factory()->create();

        $this->withToken($token)->deleteJson("/api/v1/fieldops/electrical-boards/{$board->id}");

        $this->assertSoftDeleted('fo_electrical_boards', ['id' => $board->id]);
    }

    public function test_destroy_then_get_returns_404(): void
    {
        [, $token] = $this->user();
        $board = ElectricalBoard::factory()->create();

        $this->withToken($token)->deleteJson("/api/v1/fieldops/electrical-boards/{$board->id}")
            ->assertStatus(204);

        $this->withToken($token)->getJson("/api/v1/fieldops/electrical-boards/{$board->id}")
            ->assertStatus(404);
    }

    public function test_destroy_requires_authentication(): void
    {
        $board = ElectricalBoard::factory()->create();

        $this->deleteJson("/api/v1/fieldops/electrical-boards/{$board->id}")
            ->assertStatus(401);
    }

    // ── index ─────────────────────────────────────────────────────────────────

    public function test_index_returns_all_boards(): void
    {
        [, $token] = $this->user();
        ElectricalBoard::factory()->count(2)->create();

        $response = $this->withToken($token)->getJson('/api/v1/fieldops/electrical-boards')->assertOk();

        $this->assertCount(2, $response->json('data'));
    }

    public function test_show_returns_board_with_relations(): void
    {
        [, $token] = $this->user();
        $board   = ElectricalBoard::factory()->create();
        $complex = Complex::factory()->create();
        $board->complexes()->attach($complex->id);

        $response = $this->withToken($token)->getJson("/api/v1/fieldops/electrical-boards/{$board->id}")->assertOk();

        $response->assertJsonPath('data.complexes.0.id', $complex->id);
    }
}
