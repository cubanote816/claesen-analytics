<?php

declare(strict_types=1);

namespace Modules\FieldOps\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Models\User;
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
}
