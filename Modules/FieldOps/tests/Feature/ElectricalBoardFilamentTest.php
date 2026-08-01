<?php

declare(strict_types=1);

namespace Modules\FieldOps\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\Core\Models\User;
use Modules\FieldOps\Filament\Resources\ComplexResource;
use Modules\FieldOps\Filament\Resources\ElectricalBoardResource;
use Modules\FieldOps\Filament\Resources\Structures\Pages\ViewStructure;
use Modules\FieldOps\Filament\Resources\Structures\RelationManagers\ElectricalBoardsRelationManager as StructureElectricalBoardsRelationManager;
use Modules\FieldOps\Models\Complex;
use Modules\FieldOps\Models\ElectricalBoard;
use Modules\FieldOps\Models\Structure;
use Modules\FieldOps\Models\Terrain;
use Modules\FieldOps\Models\TerrainType;
use Modules\Intelligence\Services\GeminiService;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ElectricalBoardFilamentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mock(GeminiService::class, fn ($m) => $m->shouldReceive('translateAndDetect')->andReturn(['translations' => [], 'detected_locale' => 'nl']));
        Role::create(['name' => 'super_admin', 'guard_name' => 'web']);
    }

    public function test_board_pages_render_with_used_by_backlinks(): void
    {
        $user = User::factory()->create();
        $user->assignRole('super_admin');
        $this->actingAs($user);

        $board = ElectricalBoard::factory()->create([
            'lat' => 51.163145,
            'lng' => 5.163746,
        ]);
        $board->complexes()->attach(Complex::factory()->create([
            'lat' => null,
            'lng' => null,
        ])->id);
        $board->terrains()->attach(Terrain::factory()->create([
            'lat' => null,
            'lng' => null,
        ])->id);
        $board->structures()->attach(Structure::factory()->create([
            'lat' => null,
            'lng' => null,
        ])->id);

        $this->get('/electrical-boards')->assertOk();
        $this->get("/electrical-boards/{$board->id}")
            ->assertOk()
            ->assertSee('data-fieldops-map-panel', false)
            ->assertSee('Desktop location map')
            ->assertSee('Unmapped')
            ->assertSee('No coordinates yet');
        $this->get("/electrical-boards/{$board->id}/edit")->assertOk();
    }

    public function test_board_without_any_usage_renders(): void
    {
        $user = User::factory()->create();
        $user->assignRole('super_admin');
        $this->actingAs($user);

        $board = ElectricalBoard::factory()->create();

        $this->get("/electrical-boards/{$board->id}")
            ->assertOk()
            ->assertSee('data-fieldops-map-panel', false);
    }

    public function test_board_edit_resolves_translated_location_description_instead_of_raw_json(): void
    {
        $user = User::factory()->create();
        $user->assignRole('super_admin');
        $this->actingAs($user);

        app()->setLocale('en');

        $board = ElectricalBoard::factory()->create([
            'location_description' => ['nl' => 'Testwaarde', 'en' => 'Test value', 'fr' => 'Valeur de test', 'de' => 'Testwert'],
        ]);

        $this->get("/electrical-boards/{$board->id}/edit")
            ->assertOk()
            ->assertSee('Test value', false)
            ->assertDontSee('[object Object]', false);
    }

    public function test_board_map_passes_terrain_type_code_and_color_for_sport_pin(): void
    {
        $user = User::factory()->create();
        $user->assignRole('super_admin');
        $this->actingAs($user);

        $board = ElectricalBoard::factory()->create();
        $terrainType = TerrainType::factory()->create(['code' => 'padel', 'pin_color' => '#2e9e8f']);
        $terrain = Terrain::factory()->create([
            'terrain_type_id' => $terrainType->id,
            'lat' => 51.163912,
            'lng' => 5.163982,
        ]);
        $board->terrains()->attach($terrain->id);

        // @js() (Illuminate\Support\Js) escapes every literal double quote in the
        // JSON payload to a 6-character backslash-u-0022 sequence so it survives
        // sitting inside the double-quoted x-data HTML attribute.
        $q = chr(92).'u0022';
        $this->get("/electrical-boards/{$board->id}")
            ->assertOk()
            ->assertSee("terrainTypeCode{$q}:{$q}padel", false)
            ->assertSee("terrainTypeColor{$q}:{$q}#2e9e8f", false);
    }

    public function test_board_view_and_edit_render_breadcrumb_reflecting_via_context(): void
    {
        $user = User::factory()->create();
        $user->assignRole('super_admin');
        $this->actingAs($user);

        $complex = Complex::factory()->create();
        $terrain = Terrain::factory()->create(['complex_id' => $complex->id]);
        $structure = Structure::factory()->create();
        $structure->terrains()->attach($terrain->id);
        $board = ElectricalBoard::factory()->create();
        $board->structures()->attach($structure->id);

        $this->get(ElectricalBoardResource::getUrl('view', [
            'record' => $board,
            'via_structure' => $structure->id,
            'via_terrain' => $terrain->id,
        ]))
            ->assertOk()
            ->assertSee('href="'.ComplexResource::getUrl('view', ['record' => $complex]).'"', false);

        $this->get(ElectricalBoardResource::getUrl('edit', [
            'record' => $board,
            'via_structure' => $structure->id,
            'via_terrain' => $terrain->id,
        ]))
            ->assertOk()
            ->assertSee('href="'.ComplexResource::getUrl('view', ['record' => $complex]).'"', false);

        // Reached without any via context (the flat "Electrical boards" sidebar
        // index) — no parent to show, must never error.
        $this->get(ElectricalBoardResource::getUrl('view', ['record' => $board]))->assertOk();
    }

    public function test_board_schedule_maintenance_action_forwards_via_context(): void
    {
        $user = User::factory()->create();
        $user->assignRole('super_admin');
        $this->actingAs($user);

        $complex = Complex::factory()->create();
        $board = ElectricalBoard::factory()->create();

        $this->get(ElectricalBoardResource::getUrl('view', [
            'record' => $board,
            'via_complex' => $complex->id,
        ]))
            ->assertOk()
            ->assertSee("via_complex={$complex->id}", false);
    }

    public function test_board_relation_manager_row_links_carry_via_context(): void
    {
        $user = User::factory()->create();
        $user->assignRole('super_admin');
        $this->actingAs($user);

        $structure = Structure::factory()->create();
        $board = ElectricalBoard::factory()->create();
        $board->structures()->attach($structure->id);

        Livewire::test(StructureElectricalBoardsRelationManager::class, [
            'ownerRecord' => $structure,
            'pageClass' => ViewStructure::class,
        ])->assertSee("via_structure={$structure->id}", false);
    }
}
