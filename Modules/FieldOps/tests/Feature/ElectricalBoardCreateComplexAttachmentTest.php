<?php

declare(strict_types=1);

namespace Modules\FieldOps\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\Core\Models\User;
use Modules\FieldOps\Filament\Resources\ElectricalBoardResource;
use Modules\FieldOps\Filament\Resources\ElectricalBoards\Pages\CreateElectricalBoard;
use Modules\FieldOps\Models\Complex;
use Modules\FieldOps\Models\ElectricalBoard;
use Modules\FieldOps\Models\ElectricalBoardType;
use Modules\FieldOps\Models\Structure;
use Modules\FieldOps\Models\Terrain;
use Modules\Intelligence\Services\GeminiService;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ElectricalBoardCreateComplexAttachmentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mock(GeminiService::class, fn ($m) => $m->shouldReceive('translateAndDetect')->andReturn(['translations' => [], 'detected_locale' => 'nl']));
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    }

    public function test_create_page_attaches_board_to_complex_when_complex_id_is_present(): void
    {
        $user = User::factory()->create();
        $user->assignRole('super_admin');
        $this->actingAs($user);

        $complex = Complex::factory()->create();
        $type = ElectricalBoardType::factory()->create();

        Livewire::withQueryParams(['complex_id' => $complex->id])
            ->test(CreateElectricalBoard::class)
            ->set('data.electrical_board_type_id', $type->id)
            ->set('data.lat', 51.1635)
            ->set('data.lng', 5.1640)
            ->set('data.location_description', 'Test board')
            ->call('create');

        $this->assertDatabaseHas('fo_electrical_boards', [
            'electrical_board_type_id' => $type->id,
            'created_by_user_id' => $user->id,
        ]);

        $this->assertDatabaseHas('fo_complex_electrical_board', [
            'complex_id' => $complex->id,
        ]);
    }

    public function test_create_page_redirect_forwards_via_context_so_the_new_boards_breadcrumb_survives(): void
    {
        // Regression guard for the real bug reported by the user: Filament's
        // default post-create redirect goes to the new record's View page with
        // NO extra query params. Unlike Structure/LuminaireFrame (which have a
        // deterministic "lowest id" fallback), ElectricalBoard has no stored FK
        // to any of Complex/Terrain/Structure — without via context forwarded
        // through the redirect, the breadcrumb on the page you land on right
        // after creating collapses to a bare "Electrical Boards > #new > View",
        // losing the exact hierarchy just built under.
        $user = User::factory()->create();
        $user->assignRole('super_admin');
        $this->actingAs($user);

        $complex = Complex::factory()->create();
        $terrain = Terrain::factory()->create(['complex_id' => $complex->id]);
        $structure = Structure::factory()->create();
        $structure->terrains()->attach($terrain->id);
        $type = ElectricalBoardType::factory()->create();

        $component = Livewire::withQueryParams([
            'structure_ids' => [$structure->id],
            'terrain_ids' => [$terrain->id],
        ])->test(CreateElectricalBoard::class)
            ->set('data.electrical_board_type_id', $type->id)
            ->call('create')
            ->assertHasNoFormErrors();

        $board = ElectricalBoard::firstOrFail();

        $component->assertRedirect(ElectricalBoardResource::getUrl('view', [
            'record' => $board,
            'via_structure' => $structure->id,
            'via_terrain' => $terrain->id,
        ]));
    }

    public function test_create_page_defaults_pin_to_terrain_coordinates_when_terrain_has_its_own(): void
    {
        $user = User::factory()->create();
        $user->assignRole('super_admin');
        $this->actingAs($user);

        $terrain = Terrain::factory()->create(['lat' => 51.201, 'lng' => 5.301]);

        Livewire::withQueryParams(['terrain_ids' => [$terrain->id]])
            ->test(CreateElectricalBoard::class)
            ->assertSet('data.lat', 51.201)
            ->assertSet('data.lng', 5.301);
    }

    public function test_create_page_falls_back_to_terrains_complex_coordinates_when_terrain_has_none(): void
    {
        $user = User::factory()->create();
        $user->assignRole('super_admin');
        $this->actingAs($user);

        $complex = Complex::factory()->create(['lat' => 51.202, 'lng' => 5.302]);
        $terrain = Terrain::factory()->create(['complex_id' => $complex->id, 'lat' => null, 'lng' => null]);

        Livewire::withQueryParams(['terrain_ids' => [$terrain->id]])
            ->test(CreateElectricalBoard::class)
            ->assertSet('data.lat', 51.202)
            ->assertSet('data.lng', 5.302);
    }

    public function test_create_page_defaults_pin_to_structures_own_coordinates(): void
    {
        $user = User::factory()->create();
        $user->assignRole('super_admin');
        $this->actingAs($user);

        $structure = Structure::factory()->create(['lat' => 51.203, 'lng' => 5.303]);

        Livewire::withQueryParams(['structure_ids' => [$structure->id]])
            ->test(CreateElectricalBoard::class)
            ->assertSet('data.lat', 51.203)
            ->assertSet('data.lng', 5.303);
    }

    public function test_create_page_falls_back_to_structures_terrain_coordinates_when_structure_has_none(): void
    {
        $user = User::factory()->create();
        $user->assignRole('super_admin');
        $this->actingAs($user);

        $terrain = Terrain::factory()->create(['lat' => 51.204, 'lng' => 5.304]);
        $structure = Structure::factory()->create(['lat' => null, 'lng' => null]);
        $structure->terrains()->attach($terrain->id);

        Livewire::withQueryParams(['structure_ids' => [$structure->id]])
            ->test(CreateElectricalBoard::class)
            ->assertSet('data.lat', 51.204)
            ->assertSet('data.lng', 5.304);
    }

    public function test_create_page_falls_back_to_structures_terrains_complex_coordinates_as_last_resort(): void
    {
        $user = User::factory()->create();
        $user->assignRole('super_admin');
        $this->actingAs($user);

        $complex = Complex::factory()->create(['lat' => 51.205, 'lng' => 5.305]);
        $terrain = Terrain::factory()->create(['complex_id' => $complex->id, 'lat' => null, 'lng' => null]);
        $structure = Structure::factory()->create(['lat' => null, 'lng' => null]);
        $structure->terrains()->attach($terrain->id);

        Livewire::withQueryParams(['structure_ids' => [$structure->id]])
            ->test(CreateElectricalBoard::class)
            ->assertSet('data.lat', 51.205)
            ->assertSet('data.lng', 5.305);
    }

    public function test_create_page_falls_back_to_configured_default_map_when_nothing_has_coordinates(): void
    {
        $user = User::factory()->create();
        $user->assignRole('super_admin');
        $this->actingAs($user);

        config(['fieldops.default_map' => ['lat' => 12.345, 'lng' => 67.891, 'zoom' => 11]]);

        $complex = Complex::factory()->create(['lat' => null, 'lng' => null]);

        Livewire::withQueryParams(['complex_id' => $complex->id])
            ->test(CreateElectricalBoard::class)
            ->assertSet('data.lat', 12.345)
            ->assertSet('data.lng', 67.891);
    }

    public function test_create_page_aborts_when_no_parent_context_is_present(): void
    {
        $user = User::factory()->create();
        $user->assignRole('super_admin');
        $this->actingAs($user);

        $this->get('/electrical-boards/create')->assertForbidden();
    }

    public function test_create_page_aborts_when_context_ids_do_not_resolve_to_real_records(): void
    {
        $user = User::factory()->create();
        $user->assignRole('super_admin');
        $this->actingAs($user);

        $this->get('/electrical-boards/create?complex_id=999999')->assertForbidden();
    }

    public function test_create_page_renders_when_a_valid_parent_context_is_present(): void
    {
        $user = User::factory()->create();
        $user->assignRole('super_admin');
        $this->actingAs($user);

        $complex = Complex::factory()->create();

        $this->get('/electrical-boards/create?complex_id='.$complex->id)->assertOk();
    }
}
