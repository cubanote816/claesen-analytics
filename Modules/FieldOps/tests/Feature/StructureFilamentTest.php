<?php

declare(strict_types=1);

namespace Modules\FieldOps\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Modules\Core\Models\User;
use Modules\FieldOps\Filament\Resources\ElectricalBoards\Pages\CreateElectricalBoard;
use Modules\FieldOps\Filament\Resources\LuminaireFrames\Pages\CreateLuminaireFrame;
use Modules\FieldOps\Filament\Resources\Structures\Pages\CreateStructure;
use Modules\FieldOps\Filament\Resources\Terrains\Pages\CreateTerrain;
use Modules\FieldOps\Models\AccessType;
use Modules\FieldOps\Models\Complex;
use Modules\FieldOps\Models\ElectricalBoard;
use Modules\FieldOps\Models\ElectricalBoardType;
use Modules\FieldOps\Models\LuminaireFrame;
use Modules\FieldOps\Models\LuminaireFrameType;
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
        $complex = Complex::factory()->create();
        $terrain = Terrain::factory()->create([
            'complex_id' => $complex->id,
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
            ->assertSee('Create terrain')
            ->assertSee('Create luminaire frame')
            ->assertSee('Create electrical board')
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

    public function test_structure_create_shortcuts_relink_new_records_back_to_the_structure(): void
    {
        $user = User::factory()->create();
        $user->assignRole('super_admin');
        $this->actingAs($user);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $structure = Structure::factory()->create();
        $complex = Complex::factory()->create();
        $terrain = Terrain::factory()->create([
            'complex_id' => $complex->id,
        ]);
        $structure->terrains()->attach($terrain->id);

        $frameType = LuminaireFrameType::factory()->create();
        Livewire::test(CreateLuminaireFrame::class)
            ->set('structureIds', [$structure->id])
            ->set('data.luminaire_frame_type_id', $frameType->id)
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('fo_luminaire_frame_structure', [
            'structure_id' => $structure->id,
        ]);

        $boardType = ElectricalBoardType::factory()->create();
        Livewire::test(CreateElectricalBoard::class)
            ->set('structureIds', [$structure->id])
            ->set('terrainIds', [$terrain->id])
            ->set('data.electrical_board_type_id', $boardType->id)
            ->set('data.location_description', 'Structure board')
            ->set('data.lat', 51.163912)
            ->set('data.lng', 5.163982)
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('fo_electrical_board_structure', [
            'structure_id' => $structure->id,
        ]);

        $this->assertDatabaseHas('fo_electrical_board_terrain', [
            'terrain_id' => $terrain->id,
        ]);

        Livewire::test(CreateTerrain::class)
            ->set('structureIds', [$structure->id])
            ->set('data.name', 'Structure terrain')
            ->set('data.complex_id', $complex->id)
            ->set('data.lat', 51.163912)
            ->set('data.lng', 5.163982)
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('fo_structure_terrain', [
            'structure_id' => $structure->id,
        ]);
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
