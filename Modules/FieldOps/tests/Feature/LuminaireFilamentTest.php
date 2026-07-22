<?php

declare(strict_types=1);

namespace Modules\FieldOps\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\Core\Models\User;
use Modules\FieldOps\Filament\Resources\FoMaintenanceRecordResource;
use Modules\FieldOps\Filament\Resources\LuminaireFrameResource;
use Modules\FieldOps\Filament\Resources\Luminaires\Pages\EditLuminaire;
use Modules\FieldOps\Filament\Resources\Luminaires\Pages\ViewLuminaire;
use Modules\FieldOps\Models\FoMaintenanceRecord;
use Modules\FieldOps\Models\Luminaire;
use Modules\FieldOps\Models\LuminaireType;
use Modules\Intelligence\Services\GeminiService;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class LuminaireFilamentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mock(GeminiService::class, fn ($m) => $m->shouldReceive('translateAndDetect')->andReturn(['translations' => [], 'detected_locale' => 'nl']));
        Role::create(['name' => 'super_admin', 'guard_name' => 'web']);
    }

    public function test_luminaire_pages_render_with_maintenance_history(): void
    {
        $user = User::factory()->create();
        $user->assignRole('super_admin');
        $this->actingAs($user);

        $luminaire = Luminaire::factory()->create(['frame_x' => 40, 'frame_y' => 60, 'scale_x' => 1.2]);
        $luminaire->luminaireType()->update([
            'product_family' => 'ArenaVision LED gen3.5',
            'model_reference' => 'BVP418',
            'typical_application' => 'Indoor arenas and competition venues',
            'image' => '/assets/luminaire-types/bvp418.png',
        ]);
        $resolved = FoMaintenanceRecord::factory()->forMaintainable($luminaire)->create([
            'problem_reported_at' => now()->subDays(3),
            'problem_solved_at' => now()->subDays(2),
        ]);
        $open = FoMaintenanceRecord::factory()->forMaintainable($luminaire)->create([
            'problem_reported_at' => now()->subHours(5),
            'problem_solved_at' => null,
        ]);

        $this->get('/luminaires')->assertOk();
        $this->withHeader('Accept-Language', 'en-US')->get("/luminaires/{$luminaire->id}")
            ->assertOk()
            ->assertSee('data-fieldops-luminaire-overview', false)
            ->assertSee('ArenaVision LED gen3.5')
            ->assertSee('BVP418')
            ->assertSee('Indoor arenas and competition venues')
            ->assertSee('1 open issue')
            ->assertSee('Maintenance history')
            ->assertSee('Replace luminaire')
            ->assertSee(FoMaintenanceRecordResource::getUrl('view', ['record' => $resolved]), false)
            ->assertSee(FoMaintenanceRecordResource::getUrl('view', ['record' => $open]), false)
            ->assertSee(LuminaireFrameResource::getUrl('view', [
                'record' => $luminaire->luminaire_frame_id,
                'layout' => 'technical',
                'luminaire' => $luminaire->id,
            ]));

        $this->withHeader('Accept-Language', 'en-US')->get("/luminaires/{$luminaire->id}/edit")
            ->assertOk()
            ->assertSee('Product and identification')
            ->assertSee('Technical placement')
            ->assertSee('data-fieldops-luminaire-type-picker', false)
            ->assertSee('/assets/luminaire-types/bvp418.png', false);

        $this->withHeader('Accept-Language', 'nl-BE')->get("/luminaires/{$luminaire->id}")
            ->assertOk()
            ->assertSee('Armatuuroverzicht')
            ->assertSee('Onderhoudsgeschiedenis')
            ->assertSee('Technische indeling openen');
    }

    public function test_luminaire_without_maintenance_renders(): void
    {
        $user = User::factory()->create();
        $user->assignRole('super_admin');
        $this->actingAs($user);

        $luminaire = Luminaire::factory()->create();

        $this->withHeader('Accept-Language', 'en-US')->get("/luminaires/{$luminaire->id}")
            ->assertOk()
            ->assertSee('No maintenance recorded yet.')
            ->assertSee('Record first maintenance');
    }

    public function test_editing_type_keeps_subgroup_consistent(): void
    {
        $user = User::factory()->create();
        $user->assignRole('super_admin');
        $this->actingAs($user);

        $luminaire = Luminaire::factory()->create();
        $newType = LuminaireType::factory()->create();

        Livewire::test(EditLuminaire::class, ['record' => $luminaire->id])
            ->fillForm(['luminaire_type_id' => $newType->id])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('fo_luminaires', [
            'id' => $luminaire->id,
            'luminaire_type_id' => $newType->id,
            'luminaire_subgroup_id' => $newType->luminaire_subgroup_id,
        ]);
    }

    public function test_replacement_modal_rejects_an_existing_serial_without_server_error(): void
    {
        $user = User::factory()->create();
        $user->assignRole('super_admin');
        $this->actingAs($user);

        $luminaire = Luminaire::factory()->create(['serial_number' => '000']);
        $newType = LuminaireType::factory()->create();

        Livewire::test(ViewLuminaire::class, ['record' => $luminaire->id])
            ->callAction('replaceLuminaire', [
                'luminaire_type_id' => $newType->id,
                'luminaire_subgroup_id' => $newType->luminaire_subgroup_id,
                'serial_number' => '000',
                'maintenance_at' => now(),
                'replacement_reason' => 'Test replacement',
                'position_version' => $luminaire->position->position_version,
            ])
            ->assertHasActionErrors([
                'serial_number' => __('fieldops::resource.luminaires.replacement.serial_taken'),
            ]);

        $luminaire->refresh();
        $this->assertNull($luminaire->removed_at);
        $this->assertNull($luminaire->replaced_by_luminaire_id);
        $this->assertSame($luminaire->luminaire_position_id, $luminaire->active_position_id);
    }
}
