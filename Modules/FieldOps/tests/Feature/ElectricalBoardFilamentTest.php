<?php

declare(strict_types=1);

namespace Modules\FieldOps\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Models\User;
use Modules\FieldOps\Models\Complex;
use Modules\FieldOps\Models\ElectricalBoard;
use Modules\FieldOps\Models\Structure;
use Modules\FieldOps\Models\Terrain;
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
}
