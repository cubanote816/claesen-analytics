<?php

declare(strict_types=1);

namespace Modules\FieldOps\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Models\User;
use Modules\FieldOps\Models\AccessType;
use Modules\FieldOps\Models\ElectricalBoard;
use Modules\FieldOps\Models\LuminaireFrame;
use Modules\FieldOps\Models\SafetyType;
use Modules\FieldOps\Models\Structure;
use Modules\FieldOps\Models\Terrain;
use Modules\Intelligence\Services\GeminiService;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class StructureFilamentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mock(GeminiService::class, fn ($m) => $m->shouldReceive('translateAndDetect')->andReturn(['translations' => [], 'detected_locale' => 'nl']));
        Role::create(['name' => 'super_admin', 'guard_name' => 'web']);
    }

    public function test_structure_pages_render_with_relations(): void
    {
        $user = User::factory()->create();
        $user->assignRole('super_admin');
        $this->actingAs($user);

        $structure = Structure::factory()->create([
            'access_type_id' => AccessType::factory(),
            'access_active' => true,
            'safety_type_id' => SafetyType::factory(),
            'safety_certified' => true,
            'lat' => 51.163145,
            'lng' => 5.163746,
        ]);
        $terrain = Terrain::factory()->create([
            'lat' => 51.163912,
            'lng' => 5.163982,
        ]);
        $structure->terrains()->attach($terrain->id);
        $frame = LuminaireFrame::factory()->create();
        $structure->luminaireFrames()->attach($frame->id);
        $board = ElectricalBoard::factory()->create([
            'lat' => 51.164234,
            'lng' => 5.164098,
        ]);
        $structure->electricalBoards()->attach($board->id);

        $this->get('/structures')->assertOk();
        $this->get("/structures/{$structure->id}")
            ->assertOk()
            ->assertSee('data-fieldops-map-panel', false)
            ->assertSee('Map objects');
        $this->get("/structures/{$structure->id}/edit")->assertOk();
    }

    public function test_structure_without_access_or_safety_renders(): void
    {
        $user = User::factory()->create();
        $user->assignRole('super_admin');
        $this->actingAs($user);

        $structure = Structure::factory()->create([
            'access_type_id' => null,
            'safety_type_id' => null,
        ]);

        $this->get("/structures/{$structure->id}")->assertOk();
    }
}
