<?php

declare(strict_types=1);

namespace Modules\FieldOps\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Models\User;
use Modules\FieldOps\Models\Complex;
use Modules\FieldOps\Models\ElectricalBoard;
use Modules\FieldOps\Models\FoClient;
use Modules\FieldOps\Models\Terrain;
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
        Terrain::factory()->create(['complex_id' => $complex->id]);
        $board = ElectricalBoard::factory()->create();
        $complex->electricalBoards()->attach($board->id);

        $this->get('/complexes')->assertOk();
        $this->get("/complexes/{$complex->id}")
            ->assertOk()
            ->assertSee('data-fieldops-map-panel', false)
            ->assertSee('Desktop map overview');
        $this->get("/complexes/{$complex->id}/edit")->assertOk();
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
            ->assertSee(__('fieldops::resource.complexes.no_coordinates'));
    }
}
