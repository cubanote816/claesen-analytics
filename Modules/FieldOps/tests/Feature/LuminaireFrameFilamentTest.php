<?php

declare(strict_types=1);

namespace Modules\FieldOps\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\Core\Models\User;
use Modules\FieldOps\Filament\Resources\FoMaintenanceRecordResource;
use Modules\FieldOps\Filament\Resources\FoMaintenanceWorkOrderResource;
use Modules\FieldOps\Filament\Resources\LuminaireFrameResource;
use Modules\FieldOps\Filament\Resources\LuminaireFrames\Pages\CreateLuminaireFrame;
use Modules\FieldOps\Filament\Resources\LuminaireFrames\RelationManagers\LuminairesRelationManager;
use Modules\FieldOps\Filament\Resources\LuminaireResource;
use Modules\FieldOps\Filament\Resources\Structures\Pages\ViewStructure;
use Modules\FieldOps\Filament\Resources\Structures\RelationManagers\LuminaireFramesRelationManager;
use Modules\FieldOps\Models\Complex;
use Modules\FieldOps\Models\FoMaintenanceRecord;
use Modules\FieldOps\Models\Luminaire;
use Modules\FieldOps\Models\LuminaireFrame;
use Modules\FieldOps\Models\LuminaireFrameType;
use Modules\FieldOps\Models\LuminaireType;
use Modules\FieldOps\Models\Structure;
use Modules\FieldOps\Models\Terrain;
use Modules\Intelligence\Services\GeminiService;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class LuminaireFrameFilamentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mock(GeminiService::class, fn ($m) => $m->shouldReceive('translateAndDetect')->andReturn(['translations' => [], 'detected_locale' => 'nl']));
        Role::create(['name' => 'super_admin', 'guard_name' => 'web']);
    }

    public function test_frame_pages_render_with_luminaires_and_flagged_marker(): void
    {
        $user = User::factory()->create();
        $user->assignRole('super_admin');
        $this->actingAs($user);

        $frame = LuminaireFrame::factory()->create();
        $l1 = Luminaire::factory()->create([
            'luminaire_frame_id' => $frame->id,
            'frame_position' => 1,
            'frame_x' => 10, 'frame_y' => 10, 'scale_x' => 1.0, 'scale_y' => 1.0,
            'position_verified_at' => now()->subDay(),
        ]);
        $l2 = Luminaire::factory()->create([
            'luminaire_frame_id' => $frame->id,
            'frame_position' => 2,
            'frame_x' => 90, 'frame_y' => 90, 'scale_x' => 1.5, 'scale_y' => 1.5,
        ]);
        $l1->luminaireType()->update([
            'name' => 'BVP525 OptiVision',
            'product_family' => 'OptiVision LED gen3.5',
            'model_reference' => 'BVP518',
            'typical_application' => 'Recreational football and tennis',
            'image' => 'https://example.test/bvp525.jpg',
        ]);
        $l2->luminaireType()->update(['name' => 'BVP527 OptiVision']);

        FoMaintenanceRecord::factory()->forMaintainable($l2)->create([
            'problem_reported_at' => now()->subHours(2),
            'problem_solved_at' => null,
        ]);

        $this->withHeader('Accept-Language', 'en-US')->get('/luminaire-frames')->assertOk();
        $this->withHeader('Accept-Language', 'en-US')->get("/luminaire-frames/{$frame->id}")
            ->assertOk()
            ->assertSee(__('fieldops::resource.luminaire_frames.view.eyebrow'))
            ->assertSee('Frame overview')
            ->assertSee('Technical layout')
            ->assertSee('Open maintenance issues')
            ->assertSee('Last verified')
            ->assertSee('No open issues')
            ->assertSee('Hide details')
            ->assertSee('Show details')
            ->assertSee('Add luminaire')
            ->assertSee('Choose the luminaire type')
            ->assertSee('fieldops-new-luminaire-type', false)
            ->assertSee('data-fieldops-luminaire-type-preview', false)
            ->assertSee('data-fieldops-technical-details-toggle', false)
            ->assertSee('data-fieldops-marker-scale-control', false)
            ->assertSee('draggable="false"', false)
            ->assertSee('@dragstart.prevent', false)
            // These three live inside the @script block (CLA-278 wire:navigate fix):
            // Livewire transports it as a JSON effect embedded in a wire:snapshot
            // HTML attribute, so it reaches the response HTML-entity-escaped rather
            // than as literal inline <script> text — assert with escaping enabled
            // (default) instead of raw.
            ->assertSee("this.viewMode === 'overview'")
            ->assertSee('window.Livewire.navigate(marker.url)')
            ->assertSee('wire:navigate', false)
            ->assertSee('x-show="viewMode === \'technical\'"', false)
            ->assertSee("setViewMode('technical')", false)
            ->assertSee("destination.searchParams.set('layout', 'technical')")
            ->assertSee('https://example.test/bvp525.jpg', false)
            ->assertSee('OptiVision LED gen3.5')
            ->assertSee('BVP518')
            ->assertSee('Recreational football and tennis')
            ->assertSee(__('fieldops::resource.luminaire_frames.view.marker_size'))
            ->assertSee(__('fieldops::resource.luminaire_frames.view.marker_size_hint'))
            ->assertSee(__('fieldops::resource.luminaire_frames.view.resize_marker'))
            ->assertSee(__('fieldops::resource.luminaire_frames.view.layout_hint'))
            ->assertSee(__('fieldops::resource.luminaire_frames.view.sidebar_title'))
            ->assertSee(__('fieldops::resource.luminaire_frames.view.selected_position_label'))
            ->assertSee(__('fieldops::resource.luminaire_frames.view.open_position_details'))
            ->assertSee(__('fieldops::resource.luminaires.actions.schedule_maintenance'))
            ->assertSee(__('fieldops::resource.luminaires.actions.view_history'))
            ->assertSee(FoMaintenanceWorkOrderResource::getUrl('create', [
                'maintainable_type' => Luminaire::class,
                'maintainable_id' => $l1->id,
            ]))
            ->assertSee(FoMaintenanceRecordResource::getUrl('index', [
                'luminaire' => $l1->id,
                'position' => $l1->luminaire_position_id,
            ]))
            ->assertDontSee('fieldops::resource.luminaire_frames.view.canvas_label')
            ->assertDontSee('Resolved')
            ->assertSee(LuminaireResource::getUrl('view', ['record' => $l1]), false)
            ->assertSee('BVP525 OptiVision')
            ->assertSee('BVP527 OptiVision');

        $this->withHeader('Accept-Language', 'nl-BE')->get("/luminaire-frames/{$frame->id}")
            ->assertOk()
            ->assertSee('Frameoverzicht')
            ->assertSee('Technische indeling')
            ->assertSee('Open onderhoudsmeldingen')
            ->assertSee('Laatst geverifieerd')
            ->assertSee('Geen open meldingen')
            ->assertSee('Details verbergen')
            ->assertSee('Details tonen')
            ->assertSee('Armatuur toevoegen')
            ->assertSee('Onderhoud plannen')
            ->assertSee('Kies het armatuurtype')
            ->assertDontSee('fieldops::resource.luminaire_frames.view.canvas_label')
            ->assertDontSee('Opgelost');

        $this->get("/luminaire-frames/{$frame->id}/edit")->assertOk();
    }

    public function test_selected_marker_panel_links_use_spa_navigation(): void
    {
        // Regression guard: the Alpine-driven ":href" links in the selected-marker
        // panel (Overview AND Technical layout, both templates share identical
        // markup) never carried wire:navigate — only the rarely-visible
        // server-rendered fallback (shown briefly before Alpine hydrates) did.
        // Since Alpine hydrates almost immediately, this is what a real click
        // actually hits, and it was causing a genuine full page reload
        // (confirmed with Selenium: a window marker set before the click didn't
        // survive it). wire:navigate only needs to be present as a static
        // attribute — it doesn't need to be part of the reactive :href binding.
        $user = User::factory()->create();
        $user->assignRole('super_admin');
        $this->actingAs($user);

        $frame = LuminaireFrame::factory()->create();
        Luminaire::factory()->create(['luminaire_frame_id' => $frame->id, 'frame_position' => 1]);

        $html = $this->get("/luminaire-frames/{$frame->id}")->assertOk()->getContent();

        $this->assertSame(
            2,
            substr_count($html, ':href="selectedMarker()?.url" wire:navigate'),
            'Expected the selected-marker "open position details" link to carry wire:navigate in both the Overview and Technical panels.'
        );
        $this->assertSame(2, substr_count($html, ':href="selectedMarker()?.maintenanceCreateUrl" wire:navigate'));
        $this->assertSame(2, substr_count($html, ':href="selectedMarker()?.maintenanceIndexUrl" wire:navigate'));
    }

    public function test_frame_without_luminaires_renders_empty_state(): void
    {
        $user = User::factory()->create();
        $user->assignRole('super_admin');
        $this->actingAs($user);

        $frame = LuminaireFrame::factory()->create();

        $this->get("/luminaire-frames/{$frame->id}")->assertOk();
    }

    public function test_frame_with_single_luminaire_does_not_divide_by_zero(): void
    {
        $user = User::factory()->create();
        $user->assignRole('super_admin');
        $this->actingAs($user);

        $frame = LuminaireFrame::factory()->create();
        Luminaire::factory()->create([
            'luminaire_frame_id' => $frame->id,
            'frame_position' => 1,
            'frame_x' => 50, 'frame_y' => 50,
        ]);

        $this->get("/luminaire-frames/{$frame->id}")->assertOk();
    }

    public function test_frame_form_renders_gallery_selection_with_preview_and_selected_state(): void
    {
        $user = User::factory()->create();
        $user->assignRole('super_admin');
        $this->actingAs($user);

        $withImage = LuminaireFrameType::factory()->create([
            'name' => 'Balcony',
            'image' => 'https://example.test/balcony.jpg',
        ]);

        $withoutImage = LuminaireFrameType::factory()->create([
            'name' => 'Traverse 1',
            'image' => null,
        ]);

        $frame = LuminaireFrame::factory()->create([
            'luminaire_frame_type_id' => $withImage->id,
        ]);

        $this->get('/luminaire-frames/create')
            ->assertOk()
            ->assertSee('data-fieldops-frame-gallery-selector', false)
            ->assertSee('Balcony')
            ->assertSee('Traverse 1')
            ->assertSee(__('fieldops::resource.luminaire_frames.gallery.create_type'))
            ->assertSee(__('fieldops::resource.luminaire_frames.gallery.open_preview'))
            ->assertSee(__('fieldops::resource.luminaire_frames.gallery.no_preview'));

        $this->get("/luminaire-frames/{$frame->id}/edit")
            ->assertOk()
            ->assertSee(__('fieldops::resource.luminaire_frames.gallery.selected'));
    }

    // ── CLA-278: no orphan frames / max 2 frames per structure ─────────────

    private function buildComplexTerrainStructure(): Structure
    {
        $complex = Complex::factory()->create();
        $terrain = Terrain::factory()->create(['complex_id' => $complex->id]);
        $structure = Structure::factory()->create();
        $structure->terrains()->attach($terrain->id);

        return $structure;
    }

    public function test_create_luminaire_frame_requires_at_least_one_structure(): void
    {
        $user = User::factory()->create();
        $user->assignRole('super_admin');
        $this->actingAs($user);

        $frameType = LuminaireFrameType::factory()->create();

        Livewire::test(CreateLuminaireFrame::class)
            ->fillForm(['luminaire_frame_type_id' => $frameType->id])
            ->call('create')
            ->assertHasFormErrors(['structures']);

        $this->assertDatabaseCount('fo_luminaire_frames', 0);
    }

    public function test_create_luminaire_frame_rejects_structure_already_at_capacity(): void
    {
        $user = User::factory()->create();
        $user->assignRole('super_admin');
        $this->actingAs($user);

        $structure = $this->buildComplexTerrainStructure();
        $terrainId = $structure->terrains()->first()->id;
        $complexId = $structure->terrains()->first()->complex_id;
        LuminaireFrame::factory()->count(2)->create()->each(
            fn (LuminaireFrame $other) => $other->structures()->attach($structure->id)
        );

        $frameType = LuminaireFrameType::factory()->create();

        Livewire::test(CreateLuminaireFrame::class)
            ->fillForm([
                'complex_id' => $complexId,
                'terrain_id' => $terrainId,
                'structures' => [$structure->id],
                'luminaire_frame_type_id' => $frameType->id,
            ])
            ->call('create')
            ->assertHasFormErrors(['structures']);
    }

    public function test_create_luminaire_frame_from_structure_context_prefills_and_saves_structure(): void
    {
        $user = User::factory()->create();
        $user->assignRole('super_admin');
        $this->actingAs($user);

        $structure = $this->buildComplexTerrainStructure();
        $frameType = LuminaireFrameType::factory()->create();

        // Simulates the real "Create" shortcut link from LuminaireFramesRelationManager
        // (?structure_ids[]=...) — complex_id/terrain_id must auto-derive from it so the
        // `structures` field isn't left disabled (a disabled field doesn't dehydrate).
        $this->get('/luminaire-frames/create?structure_ids[0]='.$structure->id)
            ->assertOk()
            ->assertSee(__('fieldops::resource.luminaire_frames.sections.location'));

        Livewire::test(CreateLuminaireFrame::class)
            ->fillForm([
                'complex_id' => $structure->terrains()->first()->complex_id,
                'terrain_id' => $structure->terrains()->first()->id,
                'structures' => [$structure->id],
                'luminaire_frame_type_id' => $frameType->id,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('fo_luminaire_frame_structure', [
            'structure_id' => $structure->id,
        ]);
    }

    public function test_create_luminaire_frame_redirect_forwards_via_structure(): void
    {
        // Regression guard: Filament's default post-create redirect goes to
        // the new record's View page with no extra query params — without
        // via_structure, the frame's breadcrumb falls back to
        // LuminaireFrame::resolveStructure()'s deterministic "lowest id",
        // which can silently diverge from the structure just created under.
        $user = User::factory()->create();
        $user->assignRole('super_admin');
        $this->actingAs($user);

        $structure = $this->buildComplexTerrainStructure();
        $frameType = LuminaireFrameType::factory()->create();

        $component = Livewire::withQueryParams(['structure_ids' => [$structure->id]])
            ->test(CreateLuminaireFrame::class)
            ->fillForm([
                'complex_id' => $structure->terrains()->first()->complex_id,
                'terrain_id' => $structure->terrains()->first()->id,
                'structures' => [$structure->id],
                'luminaire_frame_type_id' => $frameType->id,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $frame = LuminaireFrame::firstOrFail();

        $component->assertRedirect(LuminaireFrameResource::getUrl('view', [
            'record' => $frame,
            'via_structure' => $structure->id,
        ]));
    }

    public function test_luminaire_frames_relation_manager_hides_create_and_attach_at_capacity(): void
    {
        $user = User::factory()->create();
        $user->assignRole('super_admin');
        $this->actingAs($user);

        $structure = Structure::factory()->create();
        LuminaireFrame::factory()->count(2)->create()->each(
            fn (LuminaireFrame $other) => $other->structures()->attach($structure->id)
        );

        Livewire::test(LuminaireFramesRelationManager::class, [
            'ownerRecord' => $structure,
            'pageClass' => ViewStructure::class,
        ])
            ->assertTableActionHidden('createLuminaireFrame')
            ->assertTableActionHidden('attach');
    }

    public function test_luminaire_frames_relation_manager_shows_create_and_attach_under_capacity(): void
    {
        $user = User::factory()->create();
        $user->assignRole('super_admin');
        $this->actingAs($user);

        $structure = Structure::factory()->create();
        LuminaireFrame::factory()->create()->structures()->attach($structure->id);

        // EditStructure, not ViewStructure: Filament hides CreateAction/AttachAction by
        // default on ViewRecord pages (RelationManager::isReadOnly()) — confirmed empirically
        // that 'attach' only shows on the Edit page context, matching the real reachable path.
        Livewire::test(LuminaireFramesRelationManager::class, [
            'ownerRecord' => $structure,
            'pageClass' => \Modules\FieldOps\Filament\Resources\Structures\Pages\EditStructure::class,
        ])
            ->assertTableActionVisible('createLuminaireFrame')
            ->assertTableActionVisible('attach');
    }

    // ── CLA-278: second Luminaire creation surface (frame -> Luminaires tab) safety ──

    public function test_luminaires_relation_manager_create_requires_type_and_rejects_occupied_position(): void
    {
        $user = User::factory()->create();
        $user->assignRole('super_admin');
        $this->actingAs($user);

        $frame = LuminaireFrame::factory()->create();
        Luminaire::factory()->create([
            'luminaire_frame_id' => $frame->id,
            'frame_position' => 1,
        ]);

        Livewire::test(LuminairesRelationManager::class, [
            'ownerRecord' => $frame,
            'pageClass' => \Modules\FieldOps\Filament\Resources\LuminaireFrames\Pages\EditLuminaireFrame::class,
        ])
            ->callTableAction('create', data: ['luminaire_type_id' => null])
            ->assertHasTableActionErrors(['luminaire_type_id']);

        $type = LuminaireType::factory()->create();
        Livewire::test(LuminairesRelationManager::class, [
            'ownerRecord' => $frame,
            'pageClass' => \Modules\FieldOps\Filament\Resources\LuminaireFrames\Pages\EditLuminaireFrame::class,
        ])
            ->callTableAction('create', data: [
                'luminaire_type_id' => $type->id,
                'frame_position' => 1,
            ])
            ->assertHasTableActionErrors(['frame_position']);
    }

    public function test_luminaires_relation_manager_create_auto_generates_serial_number(): void
    {
        $user = User::factory()->create();
        $user->assignRole('super_admin');
        $this->actingAs($user);

        $frame = LuminaireFrame::factory()->create();
        $type = LuminaireType::factory()->create();

        Livewire::test(LuminairesRelationManager::class, [
            'ownerRecord' => $frame,
            'pageClass' => \Modules\FieldOps\Filament\Resources\LuminaireFrames\Pages\EditLuminaireFrame::class,
        ])
            ->callTableAction('create', data: [
                'luminaire_type_id' => $type->id,
                'frame_position' => 2,
            ])
            ->assertHasNoTableActionErrors();

        $luminaire = Luminaire::where('luminaire_frame_id', $frame->id)->firstOrFail();
        $this->assertNotEmpty($luminaire->serial_number);
        $this->assertStringStartsWith('AUTO-', $luminaire->serial_number);
    }
}
