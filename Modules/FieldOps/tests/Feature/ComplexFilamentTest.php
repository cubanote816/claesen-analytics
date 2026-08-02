<?php

declare(strict_types=1);

namespace Modules\FieldOps\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Modules\Core\Models\User;
use Modules\FieldOps\Filament\Resources\ElectricalBoardResource;
use Modules\FieldOps\Filament\Resources\Complexes\Pages\ViewComplex;
use Modules\FieldOps\Filament\Resources\Complexes\RelationManagers\TerrainsRelationManager;
use Modules\FieldOps\Models\Complex;
use Modules\FieldOps\Models\ElectricalBoard;
use Modules\FieldOps\Models\FoClient;
use Modules\FieldOps\Models\Terrain;
use Modules\FieldOps\Models\TerrainType;
use Modules\Intelligence\Services\GeminiService;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ComplexFilamentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mock(GeminiService::class, fn ($m) => $m->shouldReceive('translateAndDetect')->andReturn(['translations' => [], 'detected_locale' => 'nl']));
        Role::create(['name' => 'super_admin', 'guard_name' => 'web']);
    }

    public function test_complex_pages_render_with_relations(): void
    {
        $user = User::factory()->create();
        $user->assignRole('super_admin');
        $this->actingAs($user);

        $complex = Complex::factory()->create(['client_id' => FoClient::factory()->create()->id]);
        Terrain::factory()->create([
            'complex_id' => $complex->id,
            'lat' => 51.163912,
            'lng' => 5.163982,
        ]);
        $board = ElectricalBoard::factory()->create([
            'lat' => null,
            'lng' => null,
        ]);
        $complex->electricalBoards()->attach($board->id);

        $this->get('/complexes')->assertOk();
        $this->get("/complexes/{$complex->id}")
            ->assertOk()
            ->assertSee('data-fieldops-map-panel', false)
            ->assertSee('Desktop map overview')
            ->assertSee('Unmapped')
            ->assertSee('No coordinates yet')
            ->assertSee(ElectricalBoardResource::getUrl('view', [
                'record' => $board,
                'via_complex' => $complex->id,
            ]), false);
        $this->get("/complexes/{$complex->id}/edit")->assertOk();
    }

    public function test_complex_map_panel_and_profile_chip_links_use_spa_navigation(): void
    {
        // Regression guard: map-panel.blade.php and profile-header.blade.php built
        // raw <a href="{{ $url }}"> for internal FieldOps navigation (no
        // generate_href_html()) — confirmed via a real browser click (Selenium)
        // that this caused a genuine full page reload, unrelated to the
        // breadcrumb's own SPA links (already covered by
        // FieldOpsHierarchyNavigationTest::test_breadcrumb_record_links_use_spa_navigation_not_full_page_reload).
        $user = User::factory()->create();
        $user->assignRole('super_admin');
        $this->actingAs($user);

        $client = FoClient::factory()->create();
        $complex = Complex::factory()->create(['client_id' => $client->id]);
        $terrain = Terrain::factory()->create([
            'complex_id' => $complex->id,
            'lat' => 51.163912,
            'lng' => 5.163982,
        ]);

        $html = $this->get("/complexes/{$complex->id}")->assertOk()->getContent();

        $terrainLinkPos = strpos($html, 'href="'.\Modules\FieldOps\Filament\Resources\TerrainResource::getUrl('view', ['record' => $terrain]).'"');
        $this->assertNotFalse($terrainLinkPos, 'Map panel rail item for the terrain should be present.');
        $this->assertStringContainsString('wire:navigate', substr($html, $terrainLinkPos, 200));

        $clientChipLinkPos = strpos($html, 'href="'.\Modules\FieldOps\Filament\Resources\FoClientResource::getUrl('view', ['record' => $client]).'"');
        $this->assertNotFalse($clientChipLinkPos, 'Profile header client chip should be present.');
        $this->assertStringContainsString('wire:navigate', substr($html, $clientChipLinkPos, 200));
    }

    public function test_complex_map_passes_terrain_type_code_and_color_for_sport_pin(): void
    {
        $user = User::factory()->create();
        $user->assignRole('super_admin');
        $this->actingAs($user);

        $complex = Complex::factory()->create(['client_id' => FoClient::factory()->create()->id]);
        $terrainType = TerrainType::factory()->create(['code' => 'soccer', 'pin_color' => '#4c8c4a']);
        Terrain::factory()->create([
            'complex_id' => $complex->id,
            'terrain_type_id' => $terrainType->id,
            'lat' => 51.163912,
            'lng' => 5.163982,
        ]);

        // @js() (Illuminate\Support\Js) escapes every literal double quote in the
        // JSON payload to a 6-character backslash-u-0022 sequence so it survives
        // sitting inside the double-quoted x-data HTML attribute.
        $q = chr(92).'u0022';
        $this->get("/complexes/{$complex->id}")
            ->assertOk()
            ->assertSee("terrainTypeCode{$q}:{$q}soccer", false)
            ->assertSee("terrainTypeColor{$q}:{$q}#4c8c4a", false);
    }

    // Reproduces a real gap from manual QA: the documents section only ever showed a
    // count, never an actual link — fixed to render a download list (shared partial,
    // Modules/FieldOps/resources/views/filament/infolists/document-list.blade.php)
    // routed through the same session-authenticated fieldops.admin.media.show route
    // used by the photo/video galleries.
    public function test_complex_view_renders_a_document_download_link(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();
        $user->assignRole('super_admin');
        $this->actingAs($user);

        $complex = Complex::factory()->create();
        // UploadedFile::fake()->create() produces dummy bytes, not real PDF content —
        // Media Library's acceptsMimeTypes() sniffs actual content and rejects it.
        $path = tempnam(sys_get_temp_dir(), 'pdf');
        file_put_contents($path, "%PDF-1.4\n%%EOF");
        $complex->addMedia(new UploadedFile($path, 'plan.pdf', 'application/pdf', null, true))
            ->toMediaCollection('documents');
        $media = $complex->fresh()->getMedia('documents')->first();

        $this->get("/complexes/{$complex->id}")
            ->assertOk()
            ->assertSee('plan.pdf')
            ->assertSee(route('fieldops.admin.media.show', $media), false);
    }

    public function test_complex_without_client_or_coordinates_renders(): void
    {
        $user = User::factory()->create();
        $user->assignRole('super_admin');
        $this->actingAs($user);

        $complex = Complex::factory()->create([
            'client_id' => null,
            'lat' => null,
            'lng' => null,
        ]);

        $this->get("/complexes/{$complex->id}")
            ->assertOk()
            ->assertSee('data-fieldops-map-panel', false)
            ->assertSee(__('fieldops::resource.complexes.no_coordinates'));

        // TerrainsRelationManager is a genuinely lazy-loaded Livewire component
        // (x-intersect) on the ViewComplex page — its table/header actions never
        // appear in the initial GET response at all, only after a follow-up AJAX
        // call the browser's IntersectionObserver triggers. This assertion never
        // could have passed via $this->get(...); testing the component directly
        // (Filament's own supported pattern for relation managers) is the fix.
        Livewire::test(TerrainsRelationManager::class, [
            'ownerRecord' => $complex,
            'pageClass' => ViewComplex::class,
        ])->assertSee(__('fieldops::resource.terrains.actions.create'));
    }
}
