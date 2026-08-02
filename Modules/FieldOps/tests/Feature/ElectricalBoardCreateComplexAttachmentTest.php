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
        Role::create(['name' => 'super_admin', 'guard_name' => 'web']);
    }

    public function test_create_page_attaches_board_to_complex_when_complex_id_is_present(): void
    {
        $user = User::factory()->create();
        $user->assignRole('super_admin');
        $this->actingAs($user);

        $complex = Complex::factory()->create();
        $type = ElectricalBoardType::factory()->create();

        Livewire::test(CreateElectricalBoard::class)
            ->set('complexId', $complex->id)
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
}
