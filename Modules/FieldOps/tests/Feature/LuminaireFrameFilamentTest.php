<?php

declare(strict_types=1);

namespace Modules\FieldOps\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Models\User;
use Modules\FieldOps\Filament\Resources\FoMaintenanceRecordResource;
use Modules\FieldOps\Filament\Resources\LuminaireResource;
use Modules\FieldOps\Models\FoMaintenanceRecord;
use Modules\FieldOps\Models\Luminaire;
use Modules\FieldOps\Models\LuminaireFrame;
use Modules\FieldOps\Models\LuminaireFrameType;
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
            ->assertSee("this.viewMode === 'overview'", false)
            ->assertSee('window.Livewire.navigate(marker.url)', false)
            ->assertSee('wire:navigate', false)
            ->assertSee('x-show="viewMode === \'technical\'"', false)
            ->assertSee("setViewMode('technical')", false)
            ->assertSee("destination.searchParams.set('layout', 'technical')", false)
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
            ->assertSee(__('fieldops::resource.luminaires.actions.add_maintenance'))
            ->assertSee(FoMaintenanceRecordResource::getUrl('create', [
                'maintainable_type' => Luminaire::class,
                'maintainable_id' => $l1->id,
                'return_luminaire' => $l1->id,
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
            ->assertSee('Onderhoud toevoegen')
            ->assertSee('Kies het armatuurtype')
            ->assertDontSee('fieldops::resource.luminaire_frames.view.canvas_label')
            ->assertDontSee('Opgelost');

        $this->get("/luminaire-frames/{$frame->id}/edit")->assertOk();
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
}
