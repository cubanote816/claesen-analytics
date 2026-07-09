<?php

declare(strict_types=1);

namespace Modules\FieldOps\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Models\User;
use Modules\FieldOps\Models\ElectricalBoard;
use Modules\FieldOps\Models\Structure;
use Modules\FieldOps\Models\Terrain;
use Modules\Intelligence\Services\GeminiService;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TerrainFilamentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mock(GeminiService::class, fn ($m) => $m->shouldReceive('translateAndDetect')->andReturn(['translations' => [], 'detected_locale' => 'nl']));
        Role::create(['name' => 'super_admin', 'guard_name' => 'web']);
    }

    public function test_terrain_pages_render_with_relations(): void
    {
        $user = User::factory()->create();
        $user->assignRole('super_admin');
        $this->actingAs($user);

        $terrain = Terrain::factory()->create();
        $structure = Structure::factory()->create();
        $terrain->structures()->attach($structure->id);
        $board = ElectricalBoard::factory()->create();
        $terrain->electricalBoards()->attach($board->id);

        $this->get('/terrains')->assertOk();
        $this->get("/terrains/{$terrain->id}")
            ->assertOk()
            ->assertSee('data-fieldops-map-panel', false)
            ->assertSee('Desktop map overview');
        $this->get("/terrains/{$terrain->id}/edit")->assertOk();
    }

    public function test_terrain_without_relations_renders(): void
    {
        $user = User::factory()->create();
        $user->assignRole('super_admin');
        $this->actingAs($user);

        $terrain = Terrain::factory()->create();

        $this->get("/terrains/{$terrain->id}")
            ->assertOk()
            ->assertSee('data-fieldops-map-panel', false);
    }
}
