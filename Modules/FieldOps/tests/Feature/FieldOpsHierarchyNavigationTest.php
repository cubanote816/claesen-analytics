<?php

declare(strict_types=1);

namespace Modules\FieldOps\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Models\User;
use Modules\FieldOps\Filament\Resources\ComplexResource;
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

        $this->assertSame(
            [ComplexResource::getUrl(), ComplexResource::getUrl('view', ['record' => $complex]), TerrainResource::getUrl()],
            array_keys($breadcrumbs),
        );
        $this->assertSame('Stadion Bleukens', array_values($breadcrumbs)[1]);
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
        $this->assertArrayHasKey(LuminaireFrameResource::getUrl(), $breadcrumbs);
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
        $this->assertArrayHasKey(LuminaireResource::getUrl(), $breadcrumbs);
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
}
