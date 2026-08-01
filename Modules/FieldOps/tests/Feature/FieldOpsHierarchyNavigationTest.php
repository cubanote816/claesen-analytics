<?php

declare(strict_types=1);

namespace Modules\FieldOps\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Models\User;
use Modules\FieldOps\Filament\Resources\ComplexResource;
use Modules\FieldOps\Filament\Resources\ElectricalBoardResource;
use Modules\FieldOps\Filament\Resources\LuminaireFrameResource;
use Modules\FieldOps\Filament\Resources\LuminaireResource;
use Modules\FieldOps\Filament\Resources\StructureResource;
use Modules\FieldOps\Filament\Resources\TerrainResource;
use Modules\FieldOps\Filament\Support\FieldOpsBreadcrumbs;
use Modules\FieldOps\Models\Complex;
use Modules\FieldOps\Models\Luminaire;
use Modules\FieldOps\Models\LuminaireFrame;
use Modules\FieldOps\Models\LuminaireFrameType;
use Modules\FieldOps\Models\Structure;
use Modules\FieldOps\Models\Terrain;
use Modules\Intelligence\Services\GeminiService;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class FieldOpsHierarchyNavigationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mock(GeminiService::class, fn ($m) => $m->shouldReceive('translateAndDetect')->andReturn(['translations' => [], 'detected_locale' => 'nl']));
        Role::create(['name' => 'super_admin', 'guard_name' => 'web']);
    }

    // ── CLA-278: Terrains/Structures/Luminaire frames/Luminaires out of the flat sidebar ──

    public function test_hierarchy_leaf_resources_do_not_register_navigation(): void
    {
        $this->assertFalse(TerrainResource::shouldRegisterNavigation());
        $this->assertFalse(StructureResource::shouldRegisterNavigation());
        $this->assertFalse(LuminaireFrameResource::shouldRegisterNavigation());
        $this->assertFalse(LuminaireResource::shouldRegisterNavigation());
    }

    public function test_hierarchy_leaf_resource_routes_still_work_despite_being_hidden(): void
    {
        $user = User::factory()->create();
        $user->assignRole('super_admin');
        $this->actingAs($user);

        $terrain = Terrain::factory()->create();
        $structure = Structure::factory()->create();
        $frame = LuminaireFrame::factory()->create();
        $luminaire = Luminaire::factory()->create(['luminaire_frame_id' => $frame->id, 'frame_position' => 1]);

        $this->get("/terrains/{$terrain->id}")->assertOk();
        $this->get("/structures/{$structure->id}")->assertOk();
        $this->get("/luminaire-frames/{$frame->id}")->assertOk();
        $this->get("/luminaires/{$luminaire->id}")->assertOk();
    }

    // ── Resolve helpers: deterministic fallback + "via" context preference ──

    public function test_structure_resolve_terrain_falls_back_to_lowest_id_without_via_context(): void
    {
        $structure = Structure::factory()->create();
        $terrainB = Terrain::factory()->create();
        $terrainA = Terrain::factory()->create();
        $structure->terrains()->attach([$terrainB->id, $terrainA->id]);

        $resolved = $structure->resolveTerrain();

        $this->assertSame(min($terrainA->id, $terrainB->id), $resolved->id);
    }

    public function test_structure_resolve_terrain_prefers_via_context_when_valid(): void
    {
        $structure = Structure::factory()->create();
        $terrainA = Terrain::factory()->create();
        $terrainB = Terrain::factory()->create();
        $structure->terrains()->attach([$terrainA->id, $terrainB->id]);

        $resolved = $structure->resolveTerrain($terrainB->id);

        $this->assertSame($terrainB->id, $resolved->id);
    }

    public function test_structure_resolve_terrain_ignores_via_context_when_not_actually_related(): void
    {
        $structure = Structure::factory()->create();
        $realTerrain = Terrain::factory()->create();
        $unrelatedTerrain = Terrain::factory()->create();
        $structure->terrains()->attach($realTerrain->id);

        $resolved = $structure->resolveTerrain($unrelatedTerrain->id);

        $this->assertSame($realTerrain->id, $resolved->id);
    }

    public function test_luminaire_frame_resolve_structure_falls_back_to_lowest_id_without_via_context(): void
    {
        $frame = LuminaireFrame::factory()->create();
        $structureB = Structure::factory()->create();
        $structureA = Structure::factory()->create();
        $frame->structures()->attach([$structureB->id, $structureA->id]);

        $resolved = $frame->resolveStructure();

        $this->assertSame(min($structureA->id, $structureB->id), $resolved->id);
    }

    public function test_luminaire_frame_resolve_structure_prefers_via_context_when_valid(): void
    {
        $frame = LuminaireFrame::factory()->create();
        $structureA = Structure::factory()->create();
        $structureB = Structure::factory()->create();
        $frame->structures()->attach([$structureA->id, $structureB->id]);

        $resolved = $frame->resolveStructure($structureB->id);

        $this->assertSame($structureB->id, $resolved->id);
    }

    // ── FieldOpsBreadcrumbs: full chain content, including the real multi-parent case ──

    public function test_terrain_ancestors_include_complex(): void
    {
        $complex = Complex::factory()->create(['name' => 'Stadion Bleukens']);
        $terrain = Terrain::factory()->create(['complex_id' => $complex->id]);

        $breadcrumbs = FieldOpsBreadcrumbs::terrainAncestors($terrain);

        $keys = array_keys($breadcrumbs);
        // Complexes stays a real, clickable index (still in the sidebar). "Terrains"
        // is a type-label segment, never a link — a flat, unscoped list of every
        // Terrain in the system isn't a page this app wants reachable at all.
        $this->assertSame([ComplexResource::getUrl(), ComplexResource::getUrl('view', ['record' => $complex])], array_slice($keys, 0, 2));
        $this->assertNotSame(TerrainResource::getUrl(), $keys[2]);
        $this->assertSame(TerrainResource::getBreadcrumb(), array_values($breadcrumbs)[2]);
        $this->assertSame('Stadion Bleukens', array_values($breadcrumbs)[1]);
    }

    public function test_type_label_segments_are_never_urls_across_the_whole_chain(): void
    {
        $complex = Complex::factory()->create(['name' => 'Stadion Bleukens']);
        $terrain = Terrain::factory()->create(['complex_id' => $complex->id, 'name' => ['nl' => 'Terrain Main']]);
        $structure = Structure::factory()->create();
        $structure->terrains()->attach($terrain->id);
        $frame = LuminaireFrame::factory()->create();
        $frame->structures()->attach($structure->id);
        $luminaire = Luminaire::factory()->create(['luminaire_frame_id' => $frame->id, 'frame_position' => 1]);

        $breadcrumbs = FieldOpsBreadcrumbs::luminaireAncestors($luminaire, $structure->id, $terrain->id);

        foreach ([TerrainResource::getUrl(), StructureResource::getUrl(), LuminaireFrameResource::getUrl(), LuminaireResource::getUrl()] as $flatIndexUrl) {
            $this->assertArrayNotHasKey($flatIndexUrl, $breadcrumbs);
        }

        // Every key really is either the Complexes index, a specific record's own
        // URL, or the non-clickable sentinel — nothing silently fell through blank.
        foreach (array_keys($breadcrumbs) as $key) {
            $this->assertTrue(
                $key === ComplexResource::getUrl()
                    || str_starts_with($key, 'http')
                    || str_starts_with($key, 'fieldops-breadcrumb-unlinked:'),
            );
        }
    }

    public function test_structure_ancestors_use_via_terrain_over_deterministic_fallback(): void
    {
        $complexA = Complex::factory()->create(['name' => 'Complex A']);
        $complexB = Complex::factory()->create(['name' => 'Complex B']);
        $terrainA = Terrain::factory()->create(['complex_id' => $complexA->id, 'name' => ['nl' => 'Terrain A']]);
        $terrainB = Terrain::factory()->create(['complex_id' => $complexB->id, 'name' => ['nl' => 'Terrain B']]);
        $structure = Structure::factory()->create();
        $structure->terrains()->attach([$terrainA->id, $terrainB->id]);

        $withoutContext = FieldOpsBreadcrumbs::structureAncestors($structure);
        $withContext = FieldOpsBreadcrumbs::structureAncestors($structure, $terrainB->id);

        $this->assertContains('Complex A', array_values($withoutContext));
        $this->assertContains('Complex B', array_values($withContext));
    }

    public function test_luminaire_frame_ancestors_full_chain_with_via_context(): void
    {
        $complex = Complex::factory()->create(['name' => 'Stadion Bleukens']);
        $terrain = Terrain::factory()->create(['complex_id' => $complex->id, 'name' => ['nl' => 'Terrain Main']]);
        $structureOther = Structure::factory()->create();
        $structure = Structure::factory()->create();
        $structure->terrains()->attach($terrain->id);
        $frame = LuminaireFrame::factory()->create();
        $frame->structures()->attach([$structureOther->id, $structure->id]);

        $breadcrumbs = FieldOpsBreadcrumbs::luminaireFrameAncestors($frame, $structure->id, $terrain->id);

        $this->assertContains('Stadion Bleukens', array_values($breadcrumbs));
        $this->assertContains('Terrain Main', array_values($breadcrumbs));
        $this->assertContains(StructureResource::getRecordTitle($structure), array_values($breadcrumbs));
        // "Luminaire Frames" is a type-label segment, not a link: no key in this
        // array should ever equal LuminaireFrameResource's flat index URL.
        $this->assertContains(LuminaireFrameResource::getBreadcrumb(), array_values($breadcrumbs));
        $this->assertArrayNotHasKey(LuminaireFrameResource::getUrl(), $breadcrumbs);
    }

    public function test_luminaire_ancestors_full_chain(): void
    {
        $complex = Complex::factory()->create(['name' => 'Stadion Bleukens']);
        $terrain = Terrain::factory()->create(['complex_id' => $complex->id, 'name' => ['nl' => 'Terrain Main']]);
        $structure = Structure::factory()->create();
        $structure->terrains()->attach($terrain->id);
        $frameType = LuminaireFrameType::factory()->create();
        $frame = LuminaireFrame::factory()->create(['luminaire_frame_type_id' => $frameType->id]);
        $frame->structures()->attach($structure->id);
        $luminaire = Luminaire::factory()->create(['luminaire_frame_id' => $frame->id, 'frame_position' => 1]);

        $breadcrumbs = FieldOpsBreadcrumbs::luminaireAncestors($luminaire, $structure->id, $terrain->id);

        $this->assertContains('Stadion Bleukens', array_values($breadcrumbs));
        $this->assertContains('Terrain Main', array_values($breadcrumbs));
        // "Luminaires" is a type-label segment, not a link — same reasoning as
        // luminaireFrameAncestors above.
        $this->assertContains(LuminaireResource::getBreadcrumb(), array_values($breadcrumbs));
        $this->assertArrayNotHasKey(LuminaireResource::getUrl(), $breadcrumbs);
    }

    // ── End-to-end: breadcrumb actually renders on the page ──

    public function test_structure_page_renders_breadcrumb_reflecting_via_terrain(): void
    {
        $user = User::factory()->create();
        $user->assignRole('super_admin');
        $this->actingAs($user);

        $complexA = Complex::factory()->create(['name' => 'Complex A']);
        $complexB = Complex::factory()->create(['name' => 'Complex B']);
        $terrainA = Terrain::factory()->create(['complex_id' => $complexA->id]);
        $terrainB = Terrain::factory()->create(['complex_id' => $complexB->id]);
        $structure = Structure::factory()->create();
        $structure->terrains()->attach([$terrainA->id, $terrainB->id]);

        $this->get("/structures/{$structure->id}?via_terrain={$terrainB->id}")
            ->assertOk()
            ->assertSee('Complex B')
            ->assertDontSee('Complex A');
    }

    public function test_breadcrumb_type_segments_render_as_plain_text_not_links(): void
    {
        $user = User::factory()->create();
        $user->assignRole('super_admin');
        $this->actingAs($user);

        $complex = Complex::factory()->create(['name' => 'Stadion Bleukens']);
        $terrain = Terrain::factory()->create(['complex_id' => $complex->id, 'name' => ['nl' => 'Terrain Main']]);
        $structure = Structure::factory()->create();
        $structure->terrains()->attach($terrain->id);
        $frame = LuminaireFrame::factory()->create();
        $frame->structures()->attach($structure->id);
        $luminaire = Luminaire::factory()->create(['luminaire_frame_id' => $frame->id, 'frame_position' => 1]);

        $html = $this->get("/luminaires/{$luminaire->id}?via_structure={$structure->id}&via_terrain={$terrain->id}")
            ->assertOk()
            ->getContent();

        // The flat index routes must never appear as an href anywhere in the
        // breadcrumb — "Terrains"/"Structures"/"Luminaire frames"/"Luminaires"
        // are plain text now, Complexes stays a real link.
        foreach ([
            TerrainResource::getUrl(),
            StructureResource::getUrl(),
            LuminaireFrameResource::getUrl(),
            LuminaireResource::getUrl(),
        ] as $flatIndexUrl) {
            $this->assertStringNotContainsString('href="'.$flatIndexUrl.'"', $html);
        }

        $this->assertStringContainsString('href="'.ComplexResource::getUrl().'"', $html);
    }

    public function test_breadcrumb_record_links_use_spa_navigation_not_full_page_reload(): void
    {
        $user = User::factory()->create();
        $user->assignRole('super_admin');
        $this->actingAs($user);

        $complex = Complex::factory()->create(['name' => 'Stadion Bleukens']);
        $terrain = Terrain::factory()->create(['complex_id' => $complex->id]);

        $html = $this->get("/terrains/{$terrain->id}")->assertOk()->getContent();

        // The panel has ->spa() enabled (AdminPanelProvider) — Filament's own
        // generate_href_html() (reused as-is in the breadcrumbs override) adds
        // wire:navigate automatically for in-panel links. Regression guard: if a
        // future edit swaps back to a raw href="{{ $url }}", this fails instead
        // of silently causing full page reloads on every breadcrumb click.
        $complexLinkPos = strpos($html, 'href="'.ComplexResource::getUrl().'"');
        $this->assertNotFalse($complexLinkPos);
        $this->assertStringContainsString('wire:navigate', substr($html, $complexLinkPos, 400));
    }

    public function test_luminaire_frames_relation_manager_record_url_carries_via_structure(): void
    {
        $structure = Structure::factory()->create();
        $frame = LuminaireFrame::factory()->create();
        $frame->structures()->attach($structure->id);

        $user = User::factory()->create();
        $user->assignRole('super_admin');
        $this->actingAs($user);

        \Livewire\Livewire::test(
            \Modules\FieldOps\Filament\Resources\Structures\RelationManagers\LuminaireFramesRelationManager::class,
            [
                'ownerRecord' => $structure,
                'pageClass' => \Modules\FieldOps\Filament\Resources\Structures\Pages\EditStructure::class,
            ]
        )->assertSee("via_structure={$structure->id}", false);
    }

    public function test_terrain_ancestors_for_complex_matches_the_record_based_variant(): void
    {
        $complex = Complex::factory()->create();
        $terrain = Terrain::factory()->create(['complex_id' => $complex->id]);

        $this->assertSame(
            FieldOpsBreadcrumbs::terrainAncestors($terrain),
            FieldOpsBreadcrumbs::terrainAncestorsForComplex($complex),
        );
    }

    public function test_create_terrain_page_renders_breadcrumb_reflecting_complex_context(): void
    {
        $user = User::factory()->create();
        $user->assignRole('super_admin');
        $this->actingAs($user);

        $complex = Complex::factory()->create();

        $this->get(TerrainResource::getUrl('create', ['complex_id' => $complex->id]))
            ->assertOk()
            ->assertSee('href="'.ComplexResource::getUrl('view', ['record' => $complex]).'"', false);
    }

    public function test_structure_ancestors_for_terrain_matches_the_record_based_variant(): void
    {
        $terrain = Terrain::factory()->create();
        $structure = Structure::factory()->create();
        $structure->terrains()->attach($terrain->id);

        $this->assertSame(
            FieldOpsBreadcrumbs::structureAncestors($structure, $terrain->id),
            FieldOpsBreadcrumbs::structureAncestorsForTerrain($terrain),
        );
    }

    public function test_create_structure_page_renders_breadcrumb_reflecting_terrain_context(): void
    {
        $user = User::factory()->create();
        $user->assignRole('super_admin');
        $this->actingAs($user);

        $complex = Complex::factory()->create();
        $terrain = Terrain::factory()->create(['complex_id' => $complex->id]);

        $this->get(StructureResource::getUrl('create', ['terrain_ids' => [$terrain->id]]))
            ->assertOk()
            ->assertSee('href="'.ComplexResource::getUrl('view', ['record' => $complex]).'"', false)
            ->assertSee('href="'.TerrainResource::getUrl('view', ['record' => $terrain]).'"', false);
    }

    public function test_electrical_board_create_ancestors_prefers_deepest_available_context(): void
    {
        $complex = Complex::factory()->create();
        $terrain = Terrain::factory()->create(['complex_id' => $complex->id]);
        $structure = Structure::factory()->create();
        $structure->terrains()->attach($terrain->id);

        // Structure present -> full chain through the structure, terrain/complex args ignored.
        $viaStructure = FieldOpsBreadcrumbs::electricalBoardCreateAncestors($structure, null, null, $terrain->id);
        $this->assertArrayHasKey(StructureResource::getUrl('view', ['record' => $structure, 'via_terrain' => $terrain->id]), $viaStructure);

        // No structure, terrain present -> chain through the terrain only.
        $viaTerrain = FieldOpsBreadcrumbs::electricalBoardCreateAncestors(null, $terrain, null);
        $this->assertArrayHasKey(TerrainResource::getUrl('view', ['record' => $terrain]), $viaTerrain);
        $this->assertArrayNotHasKey(StructureResource::getUrl('view', ['record' => $structure, 'via_terrain' => $terrain->id]), $viaTerrain);

        // Only the complex present -> chain stops at the complex.
        $viaComplex = FieldOpsBreadcrumbs::electricalBoardCreateAncestors(null, null, $complex);
        $this->assertArrayHasKey(ComplexResource::getUrl('view', ['record' => $complex]), $viaComplex);
        $this->assertArrayNotHasKey(TerrainResource::getUrl('view', ['record' => $terrain]), $viaComplex);

        // Electrical Boards' own index always stays a real link (unlike the hidden leaves).
        $this->assertSame(ElectricalBoardResource::getBreadcrumb(), $viaComplex[ElectricalBoardResource::getUrl()]);
    }

    public function test_create_electrical_board_page_renders_breadcrumb_reflecting_structure_context(): void
    {
        $user = User::factory()->create();
        $user->assignRole('super_admin');
        $this->actingAs($user);

        $complex = Complex::factory()->create();
        $terrain = Terrain::factory()->create(['complex_id' => $complex->id]);
        $structure = Structure::factory()->create();
        $structure->terrains()->attach($terrain->id);

        $this->get(ElectricalBoardResource::getUrl('create', [
            'structure_ids' => [$structure->id],
            'terrain_ids' => [$terrain->id],
        ]))
            ->assertOk()
            ->assertSee('href="'.ComplexResource::getUrl('view', ['record' => $complex]).'"', false)
            ->assertSee('href="'.ElectricalBoardResource::getUrl().'"', false);
    }

    public function test_create_luminaire_breadcrumb_uses_via_structure_when_present(): void
    {
        $user = User::factory()->create();
        $user->assignRole('super_admin');
        $this->actingAs($user);

        $complex = Complex::factory()->create();
        $terrain = Terrain::factory()->create(['complex_id' => $complex->id]);
        $structure = Structure::factory()->create();
        $structure->terrains()->attach($terrain->id);

        $this->get(LuminaireResource::getUrl('create', [
            'via_structure' => $structure->id,
            'via_terrain' => $terrain->id,
        ]))
            ->assertOk()
            ->assertSee('href="'.ComplexResource::getUrl('view', ['record' => $complex]).'"', false);

        // Without via_structure, falls back to no ancestor context at all —
        // no established caller sends it today, this must never error.
        $this->get(LuminaireResource::getUrl('create'))->assertOk();
    }
}
