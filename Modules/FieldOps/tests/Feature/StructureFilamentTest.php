<?php

declare(strict_types=1);

namespace Modules\FieldOps\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Modules\Core\Models\User;
use Modules\FieldOps\Filament\Resources\Structures\Pages\CreateStructure;
use Modules\FieldOps\Models\AccessType;
use Modules\FieldOps\Models\ElectricalBoard;
use Modules\FieldOps\Models\LuminaireFrame;
use Modules\FieldOps\Models\SafetyType;
use Modules\FieldOps\Models\Structure;
use Modules\FieldOps\Models\StructureType;
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
        Filament::setCurrentPanel(Filament::getPanel('admin'));

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
            'lat' => null,
            'lng' => null,
        ]);
        $structure->electricalBoards()->attach($board->id);

        $this->get('/structures')->assertOk();
        $this->get("/structures/{$structure->id}")
            ->assertOk()
            ->assertSee('data-fieldops-map-panel', false)
            ->assertSee('Map objects')
            ->assertSee('Unmapped')
            ->assertSee('No coordinates yet');
        $this->get("/structures/{$structure->id}/edit")->assertOk();
    }

    public function test_structure_create_page_uses_map_picker_for_coordinates(): void
    {
        $user = User::factory()->create();
        $user->assignRole('super_admin');
        $this->actingAs($user);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $terrain = Terrain::factory()->create([
            'lat' => 51.164145,
            'lng' => 5.163746,
        ]);
        StructureType::factory()->create();

        $this->get('/structures/create?terrain_ids%5B0%5D='.$terrain->id)
            ->assertOk()
            ->assertSee('fieldops-structure-location-picker', false)
            ->assertSee('Adjust the structure pin')
            ->assertDontSee('Latitude')
            ->assertDontSee('Longitude');

        Livewire::test(CreateStructure::class)
            ->set('terrainIds', [$terrain->id])
            ->assertHasNoFormErrors();
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
