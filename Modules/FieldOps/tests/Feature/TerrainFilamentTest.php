<?php

declare(strict_types=1);

namespace Modules\FieldOps\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Modules\Core\Models\User;
use Modules\FieldOps\Filament\Resources\ElectricalBoardResource;
use Modules\FieldOps\Filament\Resources\ElectricalBoards\Pages\CreateElectricalBoard;
use Modules\FieldOps\Filament\Resources\StructureResource;
use Modules\FieldOps\Filament\Resources\Structures\Pages\CreateStructure;
use Modules\FieldOps\Filament\Resources\Terrains\Pages\CreateTerrain;
use Modules\FieldOps\Models\Complex;
use Modules\FieldOps\Models\ElectricalBoard;
use Modules\FieldOps\Models\ElectricalBoardType;
use Modules\FieldOps\Models\Structure;
use Modules\FieldOps\Models\StructureType;
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
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $terrain = Terrain::factory()->create([
            'lat' => 51.164145,
            'lng' => 5.163746,
        ]);
        $structure = Structure::factory()->create([
            'lat' => null,
            'lng' => null,
        ]);
        $terrain->structures()->attach($structure->id);
        $board = ElectricalBoard::factory()->create([
            'lat' => null,
            'lng' => null,
        ]);
        $terrain->electricalBoards()->attach($board->id);

        $this->get('/terrains')->assertOk();
        $this->get("/terrains/{$terrain->id}")
            ->assertOk()
            ->assertSee('data-fieldops-map-panel', false)
            ->assertSee('Desktop map overview')
            ->assertDontSee(__('fieldops::resource.terrains.actions.attach'))
            ->assertDontSee(__('fieldops::resource.structures.actions.create'))
            ->assertSee('Unmapped')
            ->assertSee('No coordinates yet')
            ->assertSee(ElectricalBoardResource::getUrl('view', [
                'record' => $board,
                'via_terrain' => $terrain->id,
            ]), false);
        $this->get("/terrains/{$terrain->id}/edit")
            ->assertOk()
            ->assertDontSee('Create electrical board');
    }

    public function test_terrain_edit_page_hydrates_translatable_name_as_string(): void
    {
        $user = User::factory()->create();
        $user->assignRole('super_admin');
        $this->actingAs($user);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $terrain = Terrain::factory()->create([
            'name' => [
                'nl' => 'Terrein A',
                'en' => 'Terrain A',
            ],
        ]);

        $this->get("/terrains/{$terrain->id}/edit")
            ->assertOk()
            ->assertDontSee('[object Object]', false);
    }

    public function test_create_terrain_uses_the_selected_complex_coordinates_as_its_initial_pin_location(): void
    {
        $user = User::factory()->create();
        $user->assignRole('super_admin');
        $this->actingAs($user);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $complex = Complex::factory()->create([
            'lat' => 51.164145,
            'lng' => 5.163746,
        ]);

        Livewire::withQueryParams(['complex_id' => $complex->id])
            ->test(CreateTerrain::class)
            ->assertSet('data.complex_id', $complex->id)
            ->assertSet('data.lat', 51.164145)
            ->assertSet('data.lng', 5.163746)
            ->assertSet('data.map_center_lat', 51.164145)
            ->assertSet('data.map_center_lng', 5.163746);
    }

    public function test_structure_creation_from_terrain_attaches_the_current_terrain(): void
    {
        $user = User::factory()->create();
        $user->assignRole('super_admin');
        $this->actingAs($user);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $terrain = Terrain::factory()->create();
        $type = StructureType::factory()->create();

        Livewire::test(CreateStructure::class)
            ->set('terrainIds', [$terrain->id])
            ->set('data.structure_type_id', $type->id)
            ->set('data.height', 900)
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('fo_structures', [
            'created_by_user_id' => $user->id,
            'structure_type_id' => $type->id,
            'height' => 900,
        ]);

        $this->assertDatabaseHas('fo_structure_terrain', [
            'terrain_id' => $terrain->id,
        ]);
    }

    public function test_create_structure_redirect_forwards_via_terrain(): void
    {
        // Regression guard: CreateStructure's custom getRedirectUrl() used to
        // redirect to the new record's View page with no via_terrain — the
        // breadcrumb would then fall back to Structure::resolveTerrain()'s
        // deterministic "lowest id" instead of the terrain just created under.
        $user = User::factory()->create();
        $user->assignRole('super_admin');
        $this->actingAs($user);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $terrain = Terrain::factory()->create();
        $type = StructureType::factory()->create();

        $component = Livewire::test(CreateStructure::class)
            ->set('terrainIds', [$terrain->id])
            ->set('data.structure_type_id', $type->id)
            ->set('data.height', 900)
            ->call('create')
            ->assertHasNoFormErrors();

        $structure = Structure::firstOrFail();

        $component->assertRedirect(StructureResource::getUrl('view', [
            'record' => $structure,
            'via_terrain' => $terrain->id,
        ]));
    }

    public function test_electrical_board_creation_from_terrain_attaches_the_current_terrain(): void
    {
        $user = User::factory()->create();
        $user->assignRole('super_admin');
        $this->actingAs($user);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $terrain = Terrain::factory()->create();
        $type = ElectricalBoardType::factory()->create();

        Livewire::test(CreateElectricalBoard::class)
            ->set('terrainIds', [$terrain->id])
            ->set('data.electrical_board_type_id', $type->id)
            ->set('data.location_description', 'Terrace board')
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('fo_electrical_boards', [
            'created_by_user_id' => $user->id,
            'electrical_board_type_id' => $type->id,
        ]);

        $this->assertDatabaseHas('fo_electrical_board_terrain', [
            'terrain_id' => $terrain->id,
        ]);
    }

    public function test_terrain_without_relations_renders(): void
    {
        $user = User::factory()->create();
        $user->assignRole('super_admin');
        $this->actingAs($user);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $terrain = Terrain::factory()->create();

        $this->get("/terrains/{$terrain->id}")
            ->assertOk()
            ->assertSee('data-fieldops-map-panel', false);
    }
}
