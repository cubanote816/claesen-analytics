<?php

declare(strict_types=1);

namespace Modules\FieldOps\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Models\User;
use Modules\FieldOps\Models\Complex;
use Modules\FieldOps\Models\ElectricalBoard;
use Modules\FieldOps\Models\FoClient;
use Modules\FieldOps\Models\Terrain;
use Modules\FieldOps\Models\TerrainType;
use Modules\Intelligence\Services\GeminiService;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ComplexFilamentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mock(GeminiService::class, fn ($m) => $m->shouldReceive('translateAndDetect')->andReturn(['translations' => [], 'detected_locale' => 'nl']));
        Role::create(['name' => 'super_admin', 'guard_name' => 'web']);
    }

    public function test_complex_pages_render_with_relations(): void
    {
        $user = User::factory()->create();
        $user->assignRole('super_admin');
        $this->actingAs($user);

        $complex = Complex::factory()->create(['client_id' => FoClient::factory()->create()->id]);
        Terrain::factory()->create([
            'complex_id' => $complex->id,
            'lat' => 51.163912,
            'lng' => 5.163982,
        ]);
        $board = ElectricalBoard::factory()->create([
            'lat' => null,
            'lng' => null,
        ]);
        $complex->electricalBoards()->attach($board->id);

        $this->get('/complexes')->assertOk();
        $this->get("/complexes/{$complex->id}")
            ->assertOk()
            ->assertSee('data-fieldops-map-panel', false)
            ->assertSee('Desktop map overview')
            ->assertSee('Unmapped')
            ->assertSee('No coordinates yet');
        $this->get("/complexes/{$complex->id}/edit")->assertOk();
    }

    public function test_complex_map_passes_terrain_type_code_and_color_for_sport_pin(): void
    {
        $user = User::factory()->create();
        $user->assignRole('super_admin');
        $this->actingAs($user);

        $complex = Complex::factory()->create(['client_id' => FoClient::factory()->create()->id]);
        $terrainType = TerrainType::factory()->create(['code' => 'soccer', 'pin_color' => '#4c8c4a']);
        Terrain::factory()->create([
            'complex_id' => $complex->id,
            'terrain_type_id' => $terrainType->id,
            'lat' => 51.163912,
            'lng' => 5.163982,
        ]);

        // @js() (Illuminate\Support\Js) escapes every literal double quote in the
        // JSON payload to a 6-character backslash-u-0022 sequence so it survives
        // sitting inside the double-quoted x-data HTML attribute.
        $q = chr(92).'u0022';
        $this->get("/complexes/{$complex->id}")
            ->assertOk()
            ->assertSee("terrainTypeCode{$q}:{$q}soccer", false)
            ->assertSee("terrainTypeColor{$q}:{$q}#4c8c4a", false);
    }

    public function test_complex_without_client_or_coordinates_renders(): void
    {
        $user = User::factory()->create();
        $user->assignRole('super_admin');
        $this->actingAs($user);

        $complex = Complex::factory()->create([
            'client_id' => null,
            'lat' => null,
            'lng' => null,
        ]);

        $this->get("/complexes/{$complex->id}")
            ->assertOk()
            ->assertSee('data-fieldops-map-panel', false)
            ->assertSee(__('fieldops::resource.complexes.no_coordinates'))
            ->assertSee(__('fieldops::resource.terrains.actions.create'));
    }
}
