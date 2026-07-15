<?php

declare(strict_types=1);

namespace Modules\FieldOps\Tests\Feature;

use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\Core\Models\User;
use Modules\FieldOps\Filament\Resources\Structures\Pages\CreateStructure;
use Modules\FieldOps\Filament\Resources\StructureResource;
use Modules\FieldOps\Models\Complex;
use Modules\FieldOps\Models\Structure;
use Modules\FieldOps\Models\StructureType;
use Modules\FieldOps\Models\Terrain;
use Modules\Intelligence\Services\GeminiService;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class StructureProximityAlertTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mock(GeminiService::class, fn ($m) => $m->shouldReceive('translateAndDetect')->andReturn(['translations' => [], 'detected_locale' => 'nl']));
        Role::create(['name' => 'super_admin', 'guard_name' => 'web']);
        config(['fieldops.structure_proximity_warning_meters' => 10]);
    }

    public function test_create_page_warns_when_a_structure_is_within_the_configured_radius(): void
    {
        $user = User::factory()->create();
        $user->assignRole('super_admin');
        $this->actingAs($user);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $complex = Complex::factory()->create();
        $terrain = Terrain::factory()->create([
            'complex_id' => $complex->id,
            'lat' => 51.163500,
            'lng' => 5.163500,
        ]);
        $nearby = Structure::factory()->create([
            'lat' => 51.163541,
            'lng' => 5.163541,
            'structure_type_id' => StructureType::factory()->create()->id,
        ]);
        $nearby->terrains()->attach($terrain->id);

        Livewire::test(CreateStructure::class)
            ->set('terrainIds', [$terrain->id])
            ->set('data.structure_type_id', StructureType::factory()->create()->id)
            ->set('data.height', 900)
            ->set('data.lat', 51.163500)
            ->set('data.lng', 5.163500)
            ->call('create')
            ->assertHasNoFormErrors()
            ->assertSet('proximityMatch.id', $nearby->id)
            ->assertSet('pendingFormData.lat', 51.163500)
            ->assertSet('pendingFormData.lng', 5.163500)
            ->assertSet('data.lat', 51.163500)
            ->assertSet('data.lng', 5.163500)
            ->assertSet('data.height', 900)
            ->assertSee(__('fieldops::resource.structures.validation.proximity_warning_title'));

        $this->assertDatabaseCount('fo_structures', 1);
    }

    public function test_create_page_requires_a_structure_type_before_saving(): void
    {
        $user = User::factory()->create();
        $user->assignRole('super_admin');
        $this->actingAs($user);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $complex = Complex::factory()->create();
        $terrain = Terrain::factory()->create([
            'complex_id' => $complex->id,
            'lat' => 51.163500,
            'lng' => 5.163500,
        ]);

        Livewire::test(CreateStructure::class)
            ->set('terrainIds', [$terrain->id])
            ->set('data.height', 900)
            ->set('data.lat', 51.163500)
            ->set('data.lng', 5.163500)
            ->call('create')
            ->assertHasFormErrors(['structure_type_id' => ['required']]);

        $this->assertDatabaseCount('fo_structures', 0);
    }

    public function test_create_page_keeps_last_selected_location_when_terrain_is_missing(): void
    {
        $user = User::factory()->create();
        $user->assignRole('super_admin');
        $this->actingAs($user);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(CreateStructure::class)
            ->set('data.structure_type_id', StructureType::factory()->create()->id)
            ->set('data.height', 900)
            ->set('data.lat', 51.163500)
            ->set('data.lng', 5.163500)
            ->call('create')
            ->assertHasFormErrors(['terrain_ids' => ['required']])
            ->assertSet('lastSelectedLocation.lat', 51.163500)
            ->assertSet('lastSelectedLocation.lng', 5.163500);

        $this->assertDatabaseCount('fo_structures', 0);
    }

    public function test_create_page_can_attach_selected_terrains_to_the_detected_structure(): void
    {
        $user = User::factory()->create();
        $user->assignRole('super_admin');
        $this->actingAs($user);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $complex = Complex::factory()->create();
        $terrain = Terrain::factory()->create([
            'complex_id' => $complex->id,
            'lat' => 51.163500,
            'lng' => 5.163500,
        ]);
        $nearby = Structure::factory()->create([
            'lat' => 51.163541,
            'lng' => 5.163541,
        ]);
        $nearby->terrains()->attach($terrain->id);

        Livewire::test(CreateStructure::class)
            ->set('terrainIds', [$terrain->id])
            ->set('data.structure_type_id', StructureType::factory()->create()->id)
            ->set('data.height', 900)
            ->set('data.lat', 51.163500)
            ->set('data.lng', 51.163500)
            ->call('create')
            ->call('attachToDetectedStructure', $nearby->id)
            ->assertRedirect(StructureResource::getUrl('view', ['record' => $nearby]));

        $this->assertDatabaseHas('fo_structure_terrain', [
            'structure_id' => $nearby->id,
            'terrain_id' => $terrain->id,
        ]);
    }

    public function test_create_page_can_continue_anyway_after_reviewing_the_alert(): void
    {
        $user = User::factory()->create();
        $user->assignRole('super_admin');
        $this->actingAs($user);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $complex = Complex::factory()->create();
        $terrain = Terrain::factory()->create([
            'complex_id' => $complex->id,
            'lat' => 51.163500,
            'lng' => 5.163500,
        ]);
        $nearby = Structure::factory()->create([
            'lat' => 51.163541,
            'lng' => 5.163541,
        ]);
        $nearby->terrains()->attach($terrain->id);
        $type = StructureType::factory()->create();

        Livewire::test(CreateStructure::class)
            ->set('terrainIds', [$terrain->id])
            ->set('data.structure_type_id', $type->id)
            ->set('data.height', 900)
            ->set('data.lat', 51.163500)
            ->set('data.lng', 5.163500)
            ->call('create')
            ->assertSet('proximityMatch.id', $nearby->id)
            ->call('createAnyway', $nearby->id);

        $this->assertDatabaseCount('fo_structures', 2);
        $this->assertDatabaseHas('fo_structures', [
            'created_by_user_id' => $user->id,
            'structure_type_id' => $type->id,
            'height' => 900,
            'lat' => 51.163500,
            'lng' => 5.163500,
        ]);
    }
}
