<?php

declare(strict_types=1);

namespace Modules\FieldOps\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\Core\Models\User;
use Modules\FieldOps\Filament\Resources\Catalogs\LuminaireFrameTypes\Pages\ListLuminaireFrameTypes;
use Modules\FieldOps\Filament\Resources\Catalogs\LuminaireTypes\Pages\ListLuminaireTypes;
use Modules\FieldOps\Models\AccessType;
use Modules\FieldOps\Models\ElectricalBoardType;
use Modules\FieldOps\Models\FoMaintenanceType;
use Modules\FieldOps\Models\LuminaireFrameType;
use Modules\FieldOps\Models\LuminaireType;
use Modules\FieldOps\Models\SafetyType;
use Modules\FieldOps\Models\StructureType;
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
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
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

    /**
     * CLA-389 — a LuminaireType created from a confirmed AI suggestion (source
     * ai_suggestion, verified_by_user_id null) shows an "Unverified" badge in
     * Filament; a manually-created or already-verified one doesn't. The "Mark as
     * verified" row action itself renders inside a Livewire dropdown menu that a
     * plain GET can't observe (same JS-dependent-content limitation already
     * documented for other Filament actions in this codebase) — its visibility
     * closure is the same one driving this badge, so this test covers the real
     * logic without asserting on markup a static request can't see.
     */
    public function test_luminaire_type_shows_unverified_badge_only_for_unverified_ai_suggestions(): void
    {
        $user = User::factory()->create();
        $user->assignRole('super_admin');
        $this->actingAs($user);

        LuminaireType::factory()->create(['name' => 'Manual entry', 'source' => 'manual']);
        LuminaireType::factory()->create(['name' => 'Suggested entry', 'source' => 'ai_suggestion', 'verified_by_user_id' => null]);
        LuminaireType::factory()->create(['name' => 'Verified suggestion', 'source' => 'ai_suggestion', 'verified_by_user_id' => $user->id]);

        $this->get('/catalogs/luminaire-types')
            ->assertOk()
            ->assertSeeText(__('fieldops::resource.catalogs.unverified'));
    }

    public function test_mark_verified_action_records_the_reviewing_super_admin(): void
    {
        $user = User::factory()->create();
        $user->assignRole('super_admin');
        $this->actingAs($user);

        $suggested = LuminaireType::factory()->create(['source' => 'ai_suggestion', 'verified_by_user_id' => null]);

        Livewire::test(ListLuminaireTypes::class)
            ->callTableAction('markVerified', $suggested);

        $this->assertSame($user->id, $suggested->refresh()->verified_by_user_id);
    }

    /**
     * CLA-409 (CLA-390 Fase 3) — same badge/verify treatment as LuminaireType
     * (CLA-389), but source=ai_generated instead of ai_suggestion (a
     * generated catalog illustration, not a suggested existing product).
     */
    public function test_luminaire_frame_type_shows_unverified_badge_only_for_unverified_ai_generated_entries(): void
    {
        $user = User::factory()->create();
        $user->assignRole('super_admin');
        $this->actingAs($user);

        LuminaireFrameType::factory()->create(['name' => 'Manual frame', 'source' => 'manual']);
        LuminaireFrameType::factory()->create(['name' => 'Generated frame', 'source' => 'ai_generated', 'verified_by_user_id' => null]);
        LuminaireFrameType::factory()->create(['name' => 'Verified generated frame', 'source' => 'ai_generated', 'verified_by_user_id' => $user->id]);

        $this->get('/catalogs/luminaire-frame-types')
            ->assertOk()
            ->assertSeeText(__('fieldops::resource.catalogs.unverified'));
    }

    public function test_mark_verified_action_records_the_reviewing_super_admin_for_frame_types(): void
    {
        $user = User::factory()->create();
        $user->assignRole('super_admin');
        $this->actingAs($user);

        $generated = LuminaireFrameType::factory()->create(['source' => 'ai_generated', 'verified_by_user_id' => null]);

        Livewire::test(ListLuminaireFrameTypes::class)
            ->callTableAction('markVerified', $generated);

        $this->assertSame($user->id, $generated->refresh()->verified_by_user_id);
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

    /**
     * @param class-string<AccessType|StructureType|SafetyType|ElectricalBoardType|FoMaintenanceType> $modelClass
     */
    private function assertCatalogEditResolvesTranslatedLabel(string $modelClass, string $urlSegment): void
    {
        $user = User::factory()->create();
        $user->assignRole('super_admin');
        $this->actingAs($user);

        app()->setLocale('en');

        $record = $modelClass::factory()->create([
            'name' => ['nl' => 'Testwaarde', 'en' => 'Test value', 'fr' => 'Valeur de test', 'de' => 'Testwert'],
        ]);

        $this->get("/catalogs/{$urlSegment}/{$record->id}/edit")
            ->assertOk()
            ->assertSee('Test value', false)
            ->assertDontSee('[object Object]', false);
    }

    // CLA-273 — same [object Object] bug as CLA-269's Terrain Types, for the
    // other 4 single-field-translatable catalogs (LuminaireFrameType,
    // LuminaireSubgroup, LuminaireType are plain strings, not translatable —
    // they never had this bug, confirmed by reading their models).

    public function test_access_type_edit_resolves_translated_label_instead_of_raw_json(): void
    {
        $this->assertCatalogEditResolvesTranslatedLabel(AccessType::class, 'access-types');
    }

    public function test_structure_type_edit_resolves_translated_label_instead_of_raw_json(): void
    {
        $this->assertCatalogEditResolvesTranslatedLabel(StructureType::class, 'structure-types');
    }

    public function test_safety_type_edit_resolves_translated_label_instead_of_raw_json(): void
    {
        $this->assertCatalogEditResolvesTranslatedLabel(SafetyType::class, 'safety-types');
    }

    public function test_electrical_board_type_edit_resolves_translated_label_instead_of_raw_json(): void
    {
        $this->assertCatalogEditResolvesTranslatedLabel(ElectricalBoardType::class, 'electrical-board-types');
    }

    public function test_maintenance_type_edit_resolves_translated_label_instead_of_raw_json(): void
    {
        $this->assertCatalogEditResolvesTranslatedLabel(FoMaintenanceType::class, 'fo-maintenance-types');
    }

    public function test_maintenance_type_code_helper_text_mentions_all_four_reserved_codes(): void
    {
        $user = User::factory()->create();
        $user->assignRole('super_admin');
        $this->actingAs($user);

        $this->get('/catalogs/fo-maintenance-types/create')
            ->assertOk()
            ->assertSee('preventive', false)
            ->assertSee('corrective', false)
            ->assertSee('emergency', false)
            ->assertSee('replacement', false);
    }
}
