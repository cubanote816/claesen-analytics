<?php

declare(strict_types=1);

namespace Modules\FieldOps\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Models\User;
use Modules\FieldOps\Models\LuminaireFrameType;
use Modules\FieldOps\Models\LuminaireType;
use Modules\FieldOps\Models\TerrainType;
use Modules\FieldOps\Support\TerrainPinCatalog;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CatalogFilamentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::create(['name' => 'super_admin', 'guard_name' => 'web']);
    }

    public function test_luminaire_frame_type_content_grid_renders_with_and_without_image(): void
    {
        $user = User::factory()->create();
        $user->assignRole('super_admin');
        $this->actingAs($user);

        LuminaireFrameType::factory()->create(['name' => 'Traverse 1', 'image' => null]);
        LuminaireFrameType::factory()->create(['name' => 'Custom frame', 'image' => 'https://example.test/frame.jpg']);

        $this->get('/catalogs/luminaire-frame-types')->assertOk();
    }

    public function test_luminaire_frame_type_create_and_edit_render_image_editor(): void
    {
        $user = User::factory()->create();
        $user->assignRole('super_admin');
        $this->actingAs($user);

        $record = LuminaireFrameType::factory()->create(['name' => 'Traverse 6', 'image' => null]);

        $this->get('/catalogs/luminaire-frame-types/create')
            ->assertOk()
            ->assertSee(__('fieldops::resource.catalogs.frame_type_editor.upload_title'), false)
            ->assertSee(__('fieldops::resource.catalogs.frame_type_editor.draw_title'), false);

        $this->get("/catalogs/luminaire-frame-types/{$record->id}/edit")
            ->assertOk()
            ->assertSee(__('fieldops::resource.catalogs.frame_type_editor.upload_title'), false)
            ->assertSee(__('fieldops::resource.catalogs.frame_type_editor.draw_title'), false);
    }

    public function test_luminaire_type_content_grid_renders_with_and_without_image(): void
    {
        $user = User::factory()->create();
        $user->assignRole('super_admin');
        $this->actingAs($user);

        LuminaireType::factory()->create(['name' => 'BVP525', 'image' => null]);
        LuminaireType::factory()->create(['name' => 'Custom type', 'image' => 'https://example.test/type.jpg']);
        LuminaireType::factory()->create([
            'name' => 'BVP518',
            'image' => '/assets/luminaire-types/bvp518-optivision-led-gen3-5.png',
        ]);

        $this->get('/catalogs/luminaire-types')
            ->assertOk()
            ->assertSee('https://example.test/type.jpg', false)
            ->assertSee(asset('assets/luminaire-types/bvp518-optivision-led-gen3-5.png'), false);
    }

    public function test_luminaire_type_editor_renders_product_catalog_fields(): void
    {
        $user = User::factory()->create();
        $user->assignRole('super_admin');
        $this->actingAs($user);

        $record = LuminaireType::factory()->create([
            'product_family' => 'OptiVision LED gen3.5',
            'model_reference' => 'BVP518',
            'typical_application' => 'Recreational football',
        ]);

        $this->get("/catalogs/luminaire-types/{$record->id}/edit")
            ->assertOk()
            ->assertSee(__('fieldops::resource.catalogs.fields.product_family'), false)
            ->assertSee(__('fieldops::resource.catalogs.fields.model_reference'), false)
            ->assertSee(__('fieldops::resource.catalogs.fields.typical_application'), false)
            ->assertSee('OptiVision LED gen3.5', false)
            ->assertSee('BVP518', false);
    }

    public function test_terrain_type_edit_resolves_translated_label_instead_of_raw_json(): void
    {
        $user = User::factory()->create();
        $user->assignRole('super_admin');
        $this->actingAs($user);

        app()->setLocale('en');

        $record = TerrainType::factory()->create([
            'type' => ['nl' => 'Voetbal', 'en' => 'Soccer', 'fr' => 'Football', 'de' => 'Fußball'],
            'code' => 'soccer',
            'pin_color' => '#4c8c4a',
        ]);

        $this->get("/catalogs/terrain-types/{$record->id}/edit")
            ->assertOk()
            ->assertSee('Soccer', false)
            ->assertDontSee('[object Object]', false);
    }

    public function test_terrain_type_create_and_edit_render_pin_selector_with_search_and_generic_option(): void
    {
        $user = User::factory()->create();
        $user->assignRole('super_admin');
        $this->actingAs($user);

        $record = TerrainType::factory()->create(['code' => 'korfball']);

        $this->get('/catalogs/terrain-types/create')
            ->assertOk()
            ->assertSee(__('fieldops::resource.catalogs.pin_selector.search_placeholder'), false)
            ->assertSee(__('fieldops::resource.catalogs.pin_selector.generic_label'), false)
            ->assertSee(__('fieldops::resource.catalogs.pin_catalog.korfball'), false);

        $this->get("/catalogs/terrain-types/{$record->id}/edit")
            ->assertOk()
            ->assertSee(__('fieldops::resource.catalogs.pin_selector.generic_label'), false)
            ->assertSee(__('fieldops::resource.catalogs.pin_catalog.korfball'), false);
    }

    public function test_terrain_pin_catalog_has_nineteen_unique_codes(): void
    {
        $codes = TerrainPinCatalog::codes();

        $this->assertCount(19, $codes);
        $this->assertCount(19, array_unique($codes));
    }
}
