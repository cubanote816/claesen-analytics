<?php

declare(strict_types=1);

namespace Modules\FieldOps\Tests\Feature;

use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\Core\Models\User;
use Modules\FieldOps\Filament\Resources\Catalogs\StructureTypes\Pages\CreateStructureType;
use Modules\FieldOps\Filament\Resources\Catalogs\StructureTypes\Pages\EditStructureType;
use Modules\FieldOps\Filament\Resources\Catalogs\StructureTypes\Pages\ListStructureTypes;
use Modules\FieldOps\Models\StructureType;
use Modules\Intelligence\Services\GeminiService;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class StructureTypeFilamentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mock(GeminiService::class, fn ($m) => $m->shouldReceive('translateAndDetect')->andReturn(['translations' => [], 'detected_locale' => 'nl']));
        Role::create(['name' => 'super_admin', 'guard_name' => 'web']);
        // The `name` field saves under app()->getLocale() (HasAiTranslations
        // auto-translates the rest) — pin to 'en' so assertions on `name->en`
        // are deterministic regardless of APP_LOCALE.
        app()->setLocale('en');
    }

    private function actingAsSuperAdmin(): User
    {
        $user = User::factory()->create();
        $user->assignRole('super_admin');
        $this->actingAs($user);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        return $user;
    }

    public function test_create_page_renders_the_pin_selector_with_the_three_mast_icons(): void
    {
        $this->actingAsSuperAdmin();

        $this->get('/catalogs/structure-types/create')
            ->assertOk()
            ->assertSee('fieldops-structure-pin-selector', false)
            ->assertSee(__('fieldops::resource.catalogs.structure_pin_catalog.conical'))
            ->assertSee(__('fieldops::resource.catalogs.structure_pin_catalog.hinged'))
            ->assertSee(__('fieldops::resource.catalogs.structure_pin_catalog.roof'))
            ->assertSee(__('fieldops::resource.catalogs.structure_pin_selector.generic_label'));
    }

    public function test_creating_a_structure_type_with_a_pin_code_persists_it(): void
    {
        $this->actingAsSuperAdmin();

        Livewire::test(CreateStructureType::class)
            ->set('data.name', 'Conical mast')
            ->set('data.code', 'conical')
            ->set('data.pin_color', '#f5a524')
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('fo_structure_types', [
            'code' => 'conical',
            'pin_color' => '#f5a524',
        ]);
    }

    public function test_leaving_the_pin_selector_on_generic_normalizes_the_code_to_null(): void
    {
        $this->actingAsSuperAdmin();

        Livewire::test(CreateStructureType::class)
            ->set('data.name', 'Custom mast')
            ->set('data.code', '')
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('fo_structure_types', [
            'name->en' => 'Custom mast',
            'code' => null,
        ]);
    }

    public function test_two_structure_types_can_both_stay_on_generic(): void
    {
        $this->actingAsSuperAdmin();

        StructureType::factory()->create(['code' => null]);

        Livewire::test(CreateStructureType::class)
            ->set('data.name', 'Second generic type')
            ->set('data.code', '')
            ->call('create')
            ->assertHasNoFormErrors();
    }

    public function test_edit_page_prefills_the_saved_code_and_color(): void
    {
        $this->actingAsSuperAdmin();

        $structureType = StructureType::factory()->create([
            'code' => 'roof',
            'pin_color' => '#f5a524',
        ]);

        Livewire::test(EditStructureType::class, ['record' => $structureType->id])
            ->assertFormSet([
                'code' => 'roof',
                'pin_color' => '#f5a524',
            ]);
    }

    public function test_index_table_shows_the_pin_label_badge(): void
    {
        $this->actingAsSuperAdmin();

        StructureType::factory()->create(['name' => ['en' => 'Conical'], 'code' => 'conical']);
        StructureType::factory()->create(['name' => ['en' => 'Other'], 'code' => null]);

        Livewire::test(ListStructureTypes::class)
            ->assertSee(__('fieldops::resource.catalogs.structure_pin_catalog.conical'))
            ->assertSee(__('fieldops::resource.catalogs.structure_pin_selector.generic_label'));
    }
}
