<?php

declare(strict_types=1);

namespace Modules\FieldOps\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Modules\Core\Models\User;
use Modules\FieldOps\Filament\Resources\ElectricalBoardResource;
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
use Modules\FieldOps\Models\TerrainType;
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
            ->assertSee(__('fieldops::resource.terrains.actions.attach'))
            ->assertDontSee('Create terrain')
            ->assertSee('Unmapped')
            ->assertSee('No coordinates yet')
            ->assertSee(ElectricalBoardResource::getUrl('view', [
                'record' => $board,
                'via_structure' => $structure->id,
            ]), false);
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
            ->assertSee('fieldopsStructureLocationPicker', false)
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

        // CLA-278: a LuminaireFrame can no longer be created without at least one
        // Structure — complex_id/terrain_id are pure UI scaffolding that narrow the
        // `structures` relationship field's options, filled explicitly here the same
        // way the real "create from this structure" shortcut auto-derives them from
        // the structure_ids query param (LuminaireFrameResource::contextualTerrain()).
        $frameType = LuminaireFrameType::factory()->create();
        Livewire::test(CreateLuminaireFrame::class)
            ->fillForm([
                'complex_id' => $complex->id,
                'terrain_id' => $terrain->id,
                'structures' => [$structure->id],
                'luminaire_frame_type_id' => $frameType->id,
            ])
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
            ->set('data.terrain_type_id', TerrainType::factory()->create()->id)
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

        $this->get("/structures/{$structure->id}")
            ->assertOk()
            ->assertSee(__('fieldops::resource.terrains.actions.attach'))
            ->assertDontSee(__('fieldops::resource.terrains.actions.detach'));
    }

    public function test_structure_map_passes_terrain_type_code_and_color_for_sport_pin(): void
    {
        $user = User::factory()->create();
        $user->assignRole('super_admin');
        $this->actingAs($user);

        $structure = Structure::factory()->create();
        $terrainType = TerrainType::factory()->create(['code' => 'tennis', 'pin_color' => '#a7b23c']);
        $terrain = Terrain::factory()->create([
            'terrain_type_id' => $terrainType->id,
            'lat' => 51.163912,
            'lng' => 5.163982,
        ]);
        $structure->terrains()->attach($terrain->id);

        // @js() (Illuminate\Support\Js) escapes every literal double quote in the
        // JSON payload to a 6-character backslash-u-0022 sequence so it survives
        // sitting inside the double-quoted x-data HTML attribute.
        $q = chr(92).'u0022';
        $this->get("/structures/{$structure->id}")
            ->assertOk()
            ->assertSee("terrainTypeCode{$q}:{$q}tennis", false)
            ->assertSee("terrainTypeColor{$q}:{$q}#a7b23c", false);
    }

    public function test_structure_map_passes_structure_type_code_and_color_for_mast_pin(): void
    {
        $user = User::factory()->create();
        $user->assignRole('super_admin');
        $this->actingAs($user);

        $structureType = StructureType::factory()->create(['code' => 'conical', 'pin_color' => '#f5a524']);
        $structure = Structure::factory()->create([
            'structure_type_id' => $structureType->id,
            'lat' => 51.163145,
            'lng' => 5.163746,
        ]);

        // @js() (Illuminate\Support\Js) escapes every literal double quote in the
        // JSON payload to a 6-character backslash-u-0022 sequence so it survives
        // sitting inside the double-quoted x-data HTML attribute.
        $q = chr(92).'u0022';
        $this->get("/structures/{$structure->id}")
            ->assertOk()
            ->assertSee("structureTypeCode{$q}:{$q}conical", false)
            ->assertSee("structureTypeColor{$q}:{$q}#f5a524", false);
    }

    public function test_structure_edit_resolves_translated_info_instead_of_raw_json(): void
    {
        $user = User::factory()->create();
        $user->assignRole('super_admin');
        $this->actingAs($user);

        app()->setLocale('en');

        $structure = Structure::factory()->create([
            'info' => ['nl' => 'Testwaarde', 'en' => 'Test value', 'fr' => 'Valeur de test', 'de' => 'Testwert'],
        ]);

        $this->get("/structures/{$structure->id}/edit")
            ->assertOk()
            ->assertSee('Test value', false)
            ->assertDontSee('[object Object]', false);
    }
}
