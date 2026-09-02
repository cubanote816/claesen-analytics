<?php

declare(strict_types=1);

namespace Modules\FieldOps\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Modules\Core\Models\User;
use Modules\FieldOps\Filament\Resources\FoMaintenanceRecordResource;
use Modules\FieldOps\Filament\Resources\FoMaintenanceWorkOrderResource;
use Modules\FieldOps\Filament\Resources\LuminaireFrameResource;
use Modules\FieldOps\Filament\Resources\LuminaireResource;
use Modules\FieldOps\Filament\Resources\Luminaires\Pages\CreateLuminaire;
use Modules\FieldOps\Filament\Resources\Luminaires\Pages\EditLuminaire;
use Modules\FieldOps\Filament\Resources\Luminaires\Pages\ViewLuminaire;
use Modules\FieldOps\Models\Complex;
use Modules\FieldOps\Models\FoMaintenanceRecord;
use Modules\FieldOps\Models\Luminaire;
use Modules\FieldOps\Models\LuminaireFrame;
use Modules\FieldOps\Models\LuminaireType;
use Modules\FieldOps\Models\Structure;
use Modules\FieldOps\Models\Terrain;
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
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
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
            ->assertSee('/assets/luminaire-types/bvp418.png', false)
            // The product picker is locked on Edit — changing the installed product must
            // go through "Replace luminaire" instead (see CLA-278 design notes).
            ->assertDontSee('data-fieldops-luminaire-type-picker', false)
            ->assertDontSee('Change product');

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
            ->assertSee('Schedule maintenance')
            ->assertSee(FoMaintenanceWorkOrderResource::getUrl('create', [
                'maintainable_type' => Luminaire::class,
                'maintainable_id' => $luminaire->id,
            ]));
    }

    // Reproduces a real bug from manual QA: the photo/video gallery used $media->getUrl(),
    // which 404s for the private 'local' disk — fixed to route through the
    // session-authenticated fieldops.admin.media.show route (Modules/FieldOps/routes/web.php)
    // instead of Spatie MediaLibrary's own (broken, for this disk) URL generation.
    public function test_luminaire_view_renders_photo_through_the_admin_media_route(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();
        $user->assignRole('super_admin');
        $this->actingAs($user);

        $luminaire = Luminaire::factory()->create();
        $luminaire->addMedia(UploadedFile::fake()->image('photo.jpg'))->toMediaCollection('photos');
        $media = $luminaire->fresh()->getMedia('photos')->first();

        $this->get("/luminaires/{$luminaire->id}")
            ->assertOk()
            ->assertSee(route('fieldops.admin.media.show', $media), false)
            ->assertDontSee('/storage/'.$media->id.'/', false);
    }

    // Same pre-existing gap fixed across all FieldOps resources: the documents section
    // only ever showed a count, never an actual download link.
    public function test_luminaire_view_renders_a_document_download_link(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();
        $user->assignRole('super_admin');
        $this->actingAs($user);

        $luminaire = Luminaire::factory()->create();
        $path = tempnam(sys_get_temp_dir(), 'pdf');
        file_put_contents($path, "%PDF-1.4\n%%EOF");
        $luminaire->addMedia(new UploadedFile($path, 'manual.pdf', 'application/pdf', null, true))
            ->toMediaCollection('documents');
        $media = $luminaire->fresh()->getMedia('documents')->first();

        $this->get("/luminaires/{$luminaire->id}")
            ->assertOk()
            ->assertSee('manual.pdf')
            ->assertSee(route('fieldops.admin.media.show', $media), false);
    }

    public function test_luminaire_edit_resolves_translated_info_instead_of_raw_json(): void
    {
        $user = User::factory()->create();
        $user->assignRole('super_admin');
        $this->actingAs($user);

        app()->setLocale('en');

        $luminaire = Luminaire::factory()->create([
            'info' => ['nl' => 'Testwaarde', 'en' => 'Test value', 'fr' => 'Valeur de test', 'de' => 'Testwert'],
        ]);

        $this->get("/luminaires/{$luminaire->id}/edit")
            ->assertOk()
            ->assertSee('Test value', false)
            ->assertDontSee('[object Object]', false);
    }

    // The product/type field is locked on the Edit page (CLA-278) — changing the
    // installed product must go through "Replace luminaire" instead. fillForm()
    // bypasses the Blade lock directly against Livewire state, which is exactly
    // why the server-side guard in EditLuminaire::mutateFormDataBeforeSave() must
    // hold even when the UI control isn't there to prevent it.
    public function test_editing_type_via_edit_page_is_ignored(): void
    {
        $user = User::factory()->create();
        $user->assignRole('super_admin');
        $this->actingAs($user);

        $luminaire = Luminaire::factory()->create();
        $originalTypeId = $luminaire->luminaire_type_id;
        $originalSubgroupId = $luminaire->luminaire_subgroup_id;
        $newType = LuminaireType::factory()->create();

        Livewire::test(EditLuminaire::class, ['record' => $luminaire->id])
            ->fillForm(['luminaire_type_id' => $newType->id])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('fo_luminaires', [
            'id' => $luminaire->id,
            'luminaire_type_id' => $originalTypeId,
            'luminaire_subgroup_id' => $originalSubgroupId,
        ]);
    }

    // Reproduces a real crash from manual QA: submitting a frame_position already held by
    // another current luminaire on the same frame used to hit the
    // fo_luminaires_one_active_per_position DB unique constraint as a raw
    // UniqueConstraintViolationException 500 instead of a clean form error.
    public function test_create_luminaire_rejects_a_frame_position_already_in_use(): void
    {
        $user = User::factory()->create();
        $user->assignRole('super_admin');
        $this->actingAs($user);

        $existing = Luminaire::factory()->create(['frame_position' => 3]);

        Livewire::test(CreateLuminaire::class)
            ->fillForm([
                'luminaire_type_id' => $existing->luminaire_type_id,
                'luminaire_frame_id' => $existing->luminaire_frame_id,
                'frame_position' => 3,
            ])
            ->call('create')
            ->assertHasFormErrors([
                'frame_position' => __('fieldops::resource.luminaires.fields.frame_position_conflict'),
            ]);

        $this->assertDatabaseCount('fo_luminaires', 1);
    }

    public function test_create_luminaire_redirect_forwards_via_context_when_present(): void
    {
        // Regression guard: getRedirectUrlParameters() must read via_structure/
        // via_terrain from a property captured once during mount(), not by
        // re-reading request() at redirect time — Livewire's ->call() action
        // invocations don't reliably see the original page load's query string
        // the way a fresh read during mount()/initial render does. Confirmed
        // empirically on CreateLuminaireFrame's equivalent redirect.
        $user = User::factory()->create();
        $user->assignRole('super_admin');
        $this->actingAs($user);

        $complex = Complex::factory()->create();
        $terrain = Terrain::factory()->create(['complex_id' => $complex->id]);
        $structure = Structure::factory()->create();
        $structure->terrains()->attach($terrain->id);
        $frame = LuminaireFrame::factory()->create();
        $frame->structures()->attach($structure->id);
        $existing = Luminaire::factory()->create(['luminaire_frame_id' => $frame->id, 'frame_position' => 1]);

        $component = Livewire::withQueryParams(['via_structure' => $structure->id, 'via_terrain' => $terrain->id])
            ->test(CreateLuminaire::class)
            ->fillForm([
                'complex_id' => $complex->id,
                'terrain_id' => $terrain->id,
                'structure_id' => $structure->id,
                'luminaire_type_id' => $existing->luminaire_type_id,
                'luminaire_frame_id' => $frame->id,
                'frame_position' => 2,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $luminaire = Luminaire::where('frame_position', 2)->firstOrFail();

        $component->assertRedirect(LuminaireResource::getUrl('view', [
            'record' => $luminaire,
            'via_structure' => $structure->id,
            'via_terrain' => $terrain->id,
        ]));
    }

    public function test_edit_luminaire_rejects_moving_into_a_frame_position_already_in_use(): void
    {
        $user = User::factory()->create();
        $user->assignRole('super_admin');
        $this->actingAs($user);

        $frame = LuminaireFrame::factory()->create();
        Luminaire::factory()->create(['luminaire_frame_id' => $frame->id, 'frame_position' => 3]);
        $movable = Luminaire::factory()->create(['luminaire_frame_id' => $frame->id, 'frame_position' => 5]);

        Livewire::test(EditLuminaire::class, ['record' => $movable->id])
            ->fillForm(['frame_position' => 3])
            ->call('save')
            ->assertHasFormErrors([
                'frame_position' => __('fieldops::resource.luminaires.fields.frame_position_conflict'),
            ]);

        $this->assertSame(5, $movable->refresh()->frame_position);
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
