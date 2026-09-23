<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Filament\Clusters\Website\Resources\ProjectResource\Pages\CreateProject;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Modules\Core\Models\User;
use Modules\Website\Models\Project;
use Spatie\MediaLibrary\MediaCollections\Exceptions\FileUnacceptableForCollection;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * CLA-470 (F3) — docs/ai/adr-multi-organization.md project, "Corregir
 * contrato de medios y límites de subida".
 *
 * The gap this closes: App\Filament\Clusters\Website\Resources\
 * ProjectResource's form offered video/mp4|webm|quicktime for the gallery/
 * detail_gallery fields, but Modules\Website\Models\Project::
 * registerMediaCollections() only ever accepted images — a video upload
 * passed the browser's file picker, then threw
 * Spatie\MediaLibrary\MediaCollections\Exceptions\FileUnacceptableForCollection
 * on save (confirmed by reproducing it directly, uncaught, before this fix).
 * Both layers now read Project::MEDIA_MIME_TYPES, so they cannot diverge
 * again. Also fixes maxSize (100 MB / 500 MB, no real justification for
 * plain portfolio photographs — down to 10 MB, matching the precedent
 * already established for Modules\FieldOps photo collections) and adds a
 * pixel-dimension cap (defence against a decompression-bomb-style upload
 * reaching Spatie's WebP conversion pipeline).
 *
 * The 'slug' field is deliberately never asserted by value: ProjectResource
 * auto-derives it from 'title' via a live afterStateUpdated() the moment
 * both fields are filled (an existing, unrelated mechanism this file leaves
 * alone) — any explicit 'slug' passed to fillForm() gets silently
 * overwritten. Each test uses a unique 'title' instead and looks the record
 * up by that.
 */
final class ProjectMediaContractTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(config('media-library.disk_name', 'public'));

        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    }

    // ─── Filament form ↔ model contract: the actual bug ─────────────────────

    public function test_the_create_form_rejects_a_video_for_gallery_instead_of_accepting_it_then_crashing_on_save(): void
    {
        $this->actingAsSuperAdmin();

        Livewire::test(CreateProject::class)
            ->fillForm([
                'title' => 'Video rejection test',
                'category' => 'sport',
                'gallery' => [UploadedFile::fake()->create('clip.mp4', 100, 'video/mp4')],
            ])
            ->call('create')
            ->assertHasFormErrors(['gallery']);

        $this->assertSame(0, Project::query()->count());
    }

    public function test_the_model_itself_also_rejects_a_video_for_every_media_collection(): void
    {
        // Defence in depth: even if a future form change bypassed Filament's
        // own validation, Spatie MediaLibrary's own enforcement
        // (acceptsMimeTypes(), driven by the same Project::MEDIA_MIME_TYPES
        // constant the form reads) is the real, final gate.
        $project = Project::factory()->create();
        $video = UploadedFile::fake()->create('clip.mp4', 100, 'video/mp4');

        foreach (['featured_image', 'gallery', 'detail_gallery'] as $collection) {
            try {
                $project->addMedia($video)->preservingOriginal()->toMediaCollection($collection);
                $this->fail("Expected {$collection} to reject a video upload.");
            } catch (FileUnacceptableForCollection) {
                $this->assertTrue(true);
            }
        }
    }

    // ─── Size limit ──────────────────────────────────────────────────────────

    public function test_the_create_form_rejects_an_oversized_featured_image(): void
    {
        $this->actingAsSuperAdmin();

        Livewire::test(CreateProject::class)
            ->fillForm([
                'title' => 'Oversized image test',
                'category' => 'sport',
                'featured_image' => [UploadedFile::fake()->image('big.jpg')->size(10241)],
            ])
            ->call('create')
            ->assertHasFormErrors(['featured_image']);

        $this->assertSame(0, Project::query()->count());
    }

    // ─── Spoofed / corrupted content — magic-byte detection, not extension ──

    public function test_the_create_form_rejects_a_file_with_a_jpg_extension_but_non_image_content(): void
    {
        $this->actingAsSuperAdmin();

        // UploadedFile::fake()->create() writes dummy null-byte content —
        // the client-declared mime is image/jpeg but the real bytes are not
        // a valid image. Filament's acceptedFileTypes() maps to Laravel's
        // `mimetypes:` rule, which sniffs actual content via finfo, not the
        // client-declared type — this is exactly the spoofed-file case.
        $spoofed = UploadedFile::fake()->create('spoofed.jpg', 50, 'image/jpeg');

        Livewire::test(CreateProject::class)
            ->fillForm([
                'title' => 'Spoofed file test',
                'category' => 'sport',
                'featured_image' => [$spoofed],
            ])
            ->call('create')
            ->assertHasFormErrors(['featured_image']);

        $this->assertSame(0, Project::query()->count());
    }

    public function test_the_model_rejects_a_corrupted_file_with_an_image_extension(): void
    {
        $project = Project::factory()->create();
        $corrupted = UploadedFile::fake()->create('corrupted.png', 20, 'image/png');

        $this->expectException(FileUnacceptableForCollection::class);

        $project->addMedia($corrupted)->preservingOriginal()->toMediaCollection('gallery');
    }

    // ─── Dimension cap ───────────────────────────────────────────────────────

    public function test_the_create_form_rejects_an_image_exceeding_the_pixel_dimension_cap(): void
    {
        $this->actingAsSuperAdmin();

        Livewire::test(CreateProject::class)
            ->fillForm([
                'title' => 'Huge dimensions test',
                'category' => 'sport',
                'featured_image' => [UploadedFile::fake()->image('huge.jpg', 9000, 9000)],
            ])
            ->call('create')
            ->assertHasFormErrors(['featured_image']);

        $this->assertSame(0, Project::query()->count());
    }

    // ─── Valid upload still works end to end ────────────────────────────────

    public function test_a_valid_image_within_every_limit_is_accepted_and_stored_under_a_random_name(): void
    {
        $this->actingAsSuperAdmin();

        Livewire::test(CreateProject::class)
            ->fillForm([
                'title' => 'Valid upload test',
                'category' => 'sport',
                'featured_image' => [UploadedFile::fake()->image('legit.jpg', 800, 600)],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $project = Project::query()->where('slug', 'like', 'valid-upload-test%')->firstOrFail();
        $media = $project->getFirstMedia('featured_image');

        $this->assertNotNull($media);
        // CLA-470 acceptance: "nombres internos aleatorios" — already true by
        // Filament's own default (Str::ulid(), BaseFileUpload::setUp()) with
        // no ->preserveFilenames() call on this field; this pins that the
        // stored name is not the user-supplied "legit.jpg".
        $this->assertNotSame('legit', pathinfo($media->file_name, PATHINFO_FILENAME));
        $this->assertMatchesRegularExpression(
            '/^[0-9A-Z]{26}\.jpg$/',
            $media->file_name,
            'Expected a ULID-based stored filename, got: '.$media->file_name
        );
    }

    private function actingAsSuperAdmin(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole('super_admin');

        $this->actingAs($user);
    }
}
