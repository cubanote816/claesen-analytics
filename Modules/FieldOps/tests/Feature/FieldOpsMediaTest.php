<?php

declare(strict_types=1);

namespace Modules\FieldOps\Tests\Feature;

use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Modules\FieldOps\Models\Complex;
use Modules\FieldOps\Models\ElectricalBoard;
use Modules\FieldOps\Models\Structure;
use Modules\FieldOps\Models\Terrain;
use Modules\Intelligence\Services\GeminiService;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class FieldOpsMediaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->mock(GeminiService::class, fn ($m) => $m->shouldReceive('translateAndDetect')->andReturn(['translations' => [], 'detected_locale' => 'nl']));
    }

    // CLA-369 tightened FieldOps tenant scoping to single-record access (not just
    // lists) — this helper stands in for "generic authenticated internal staff"
    // across the whole file, none of these tests exercise tenant scope itself, so
    // it needs fieldops.view-all-clients like CLA-369 already gave
    // MaintenanceWorkOrderTest/MaintenanceRequestTest/MaintenanceWorkOrderAuditNotificationTest.
    private function user(): array
    {
        $user = UserFactory::new()->create();
        $user->givePermissionTo(Permission::findOrCreate('fieldops.view-all-clients', 'web'));
        $token = $user->createToken('test')->plainTextToken;

        return [$user, $token];
    }

    /**
     * UploadedFile::fake()->create() generates dummy bytes, not real PDF content —
     * Media Library's acceptsMimeTypes() sniffs actual content, so a fake "PDF"
     * gets detected as application/x-empty and rejected. Build a real minimal PDF.
     */
    private function fakePdf(string $name = 'plan.pdf'): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'pdf');
        file_put_contents($path, "%PDF-1.4\n%%EOF");

        return new UploadedFile($path, $name, 'application/pdf', null, true);
    }

    // ── store: one full round-trip per model type ───────────────────────────────

    public function test_store_uploads_photo_to_complex(): void
    {
        [, $token] = $this->user();
        $complex = Complex::factory()->create();

        $response = $this->withToken($token)->postJson("/api/v1/fieldops/complexes/{$complex->id}/media", [
            'collection' => 'photos',
            'file'       => UploadedFile::fake()->image('photo.jpg'),
        ]);

        $response->assertStatus(201)->assertJsonPath('success', true);
        $this->assertCount(1, $complex->fresh()->getMedia('photos'));
    }

    public function test_store_uploads_photo_to_terrain(): void
    {
        [, $token] = $this->user();
        $terrain = Terrain::factory()->create();

        $response = $this->withToken($token)->postJson("/api/v1/fieldops/terrains/{$terrain->id}/media", [
            'collection' => 'photos',
            'file'       => UploadedFile::fake()->image('photo.jpg'),
        ]);

        $response->assertStatus(201);
        $this->assertCount(1, $terrain->fresh()->getMedia('photos'));
    }

    public function test_store_uploads_document_to_structure(): void
    {
        [, $token] = $this->user();
        $structure = Structure::factory()->create();

        $response = $this->withToken($token)->postJson("/api/v1/fieldops/structures/{$structure->id}/media", [
            'collection' => 'documents',
            'file'       => $this->fakePdf(),
        ]);

        $response->assertStatus(201);
        $this->assertCount(1, $structure->fresh()->getMedia('documents'));
    }

    public function test_store_uploads_photo_to_electrical_board(): void
    {
        [, $token] = $this->user();
        $board = ElectricalBoard::factory()->create();

        $response = $this->withToken($token)->postJson("/api/v1/fieldops/electrical-boards/{$board->id}/media", [
            'collection' => 'photos',
            'file'       => UploadedFile::fake()->image('photo.jpg'),
        ]);

        $response->assertStatus(201);
        $this->assertCount(1, $board->fresh()->getMedia('photos'));
    }

    // ── validation ────────────────────────────────────────────────────────────

    public function test_store_requires_collection(): void
    {
        [, $token] = $this->user();
        $complex = Complex::factory()->create();

        $this->withToken($token)->postJson("/api/v1/fieldops/complexes/{$complex->id}/media", [
            'file' => UploadedFile::fake()->image('photo.jpg'),
        ])->assertStatus(422)->assertJsonValidationErrors('collection');
    }

    public function test_store_rejects_invalid_collection(): void
    {
        [, $token] = $this->user();
        $complex = Complex::factory()->create();

        $this->withToken($token)->postJson("/api/v1/fieldops/complexes/{$complex->id}/media", [
            'collection' => 'invalid',
            'file'       => UploadedFile::fake()->image('photo.jpg'),
        ])->assertStatus(422)->assertJsonValidationErrors('collection');
    }

    public function test_store_rejects_pdf_in_photos_collection(): void
    {
        [, $token] = $this->user();
        $complex = Complex::factory()->create();

        $this->withToken($token)->postJson("/api/v1/fieldops/complexes/{$complex->id}/media", [
            'collection' => 'photos',
            'file'       => UploadedFile::fake()->create('plan.pdf', 100, 'application/pdf'),
        ])->assertStatus(422)->assertJsonValidationErrors('file');
    }

    public function test_store_rejects_image_in_documents_collection(): void
    {
        [, $token] = $this->user();
        $complex = Complex::factory()->create();

        $this->withToken($token)->postJson("/api/v1/fieldops/complexes/{$complex->id}/media", [
            'collection' => 'documents',
            'file'       => UploadedFile::fake()->image('photo.jpg'),
        ])->assertStatus(422)->assertJsonValidationErrors('file');
    }

    public function test_store_rejects_oversized_photo(): void
    {
        [, $token] = $this->user();
        $complex = Complex::factory()->create();

        $this->withToken($token)->postJson("/api/v1/fieldops/complexes/{$complex->id}/media", [
            'collection' => 'photos',
            'file'       => UploadedFile::fake()->image('big.jpg')->size(10241),
        ])->assertStatus(422)->assertJsonValidationErrors('file');
    }

    public function test_store_returns_404_for_nonexistent_model(): void
    {
        [, $token] = $this->user();

        $this->withToken($token)->postJson('/api/v1/fieldops/complexes/99999/media', [
            'collection' => 'photos',
            'file'       => UploadedFile::fake()->image('photo.jpg'),
        ])->assertStatus(404);
    }

    public function test_store_requires_authentication(): void
    {
        $complex = Complex::factory()->create();

        $this->postJson("/api/v1/fieldops/complexes/{$complex->id}/media", [
            'collection' => 'photos',
            'file'       => UploadedFile::fake()->image('photo.jpg'),
        ])->assertStatus(401);
    }

    // ── show (stream) ─────────────────────────────────────────────────────────

    public function test_show_streams_the_file(): void
    {
        [, $token] = $this->user();
        $complex = Complex::factory()->create();
        $complex->addMedia(UploadedFile::fake()->image('photo.jpg'))->toMediaCollection('photos');
        $media = $complex->fresh()->getMedia('photos')->first();

        $this->withToken($token)->getJson("/api/v1/fieldops/media/{$media->id}")
            ->assertStatus(200)
            ->assertHeader('content-type', 'image/jpeg');
    }

    public function test_show_requires_authentication(): void
    {
        $complex = Complex::factory()->create();
        $complex->addMedia(UploadedFile::fake()->image('photo.jpg'))->toMediaCollection('photos');
        $media = $complex->fresh()->getMedia('photos')->first();

        $this->getJson("/api/v1/fieldops/media/{$media->id}")->assertStatus(401);
    }

    public function test_show_returns_404_for_nonexistent_media(): void
    {
        [, $token] = $this->user();

        $this->withToken($token)->getJson('/api/v1/fieldops/media/99999')->assertStatus(404);
    }

    // ── admin web route (Filament backoffice galleries) ─────────────────────────
    // Reproduces a real bug found via manual QA: the infolist gallery blades used
    // to call $media->getUrl() directly, which 404s for the private 'local' disk
    // (no public URL mapping). These assert the session-authenticated route the
    // blades were fixed to use instead.

    public function test_admin_media_route_streams_the_file_for_panel_users(): void
    {
        $user = UserFactory::new()->create();
        Role::findOrCreate('super_admin', 'web');
        $user->assignRole('super_admin');

        $complex = Complex::factory()->create();
        $complex->addMedia(UploadedFile::fake()->image('photo.jpg'))->toMediaCollection('photos');
        $media = $complex->fresh()->getMedia('photos')->first();

        $this->actingAs($user)->get("/fieldops/media/{$media->id}")
            ->assertStatus(200)
            ->assertHeader('content-type', 'image/jpeg');
    }

    public function test_admin_media_route_requires_authentication(): void
    {
        $complex = Complex::factory()->create();
        $complex->addMedia(UploadedFile::fake()->image('photo.jpg'))->toMediaCollection('photos');
        $media = $complex->fresh()->getMedia('photos')->first();

        $this->get("/fieldops/media/{$media->id}")->assertRedirect();
    }

    // Guards against reintroducing the project_manager/client panel bypass from
    // CLA-205: `auth` alone isn't enough here, since it doesn't know about
    // hasPanelAccess() — only EnsurePanelAccess does.
    public function test_admin_media_route_blocks_users_without_panel_access(): void
    {
        $user = UserFactory::new()->create();
        Role::findOrCreate('client', 'web');
        $user->assignRole('client');

        $complex = Complex::factory()->create();
        $complex->addMedia(UploadedFile::fake()->image('photo.jpg'))->toMediaCollection('photos');
        $media = $complex->fresh()->getMedia('photos')->first();

        $this->actingAs($user)->get("/fieldops/media/{$media->id}")
            ->assertRedirect(route('auth.no-access'));
    }

    // ── destroy ───────────────────────────────────────────────────────────────

    public function test_destroy_removes_media(): void
    {
        [, $token] = $this->user();
        $complex = Complex::factory()->create();
        $complex->addMedia(UploadedFile::fake()->image('photo.jpg'))->toMediaCollection('photos');
        $media = $complex->fresh()->getMedia('photos')->first();

        $this->withToken($token)->deleteJson("/api/v1/fieldops/media/{$media->id}")
            ->assertStatus(204);

        $this->assertCount(0, $complex->fresh()->getMedia('photos'));
    }

    public function test_destroy_requires_authentication(): void
    {
        $complex = Complex::factory()->create();
        $complex->addMedia(UploadedFile::fake()->image('photo.jpg'))->toMediaCollection('photos');
        $media = $complex->fresh()->getMedia('photos')->first();

        $this->deleteJson("/api/v1/fieldops/media/{$media->id}")->assertStatus(401);
    }

    // ── JSON resource shape ──────────────────────────────────────────────────

    public function test_show_complex_includes_photos_and_documents_keys(): void
    {
        [, $token] = $this->user();
        $complex = Complex::factory()->create();
        $complex->addMedia(UploadedFile::fake()->image('photo.jpg'))->toMediaCollection('photos');

        $response = $this->withToken($token)->getJson("/api/v1/fieldops/complexes/{$complex->id}")
            ->assertOk();

        $response->assertJsonCount(1, 'data.photos');
        $response->assertJsonPath('data.documents', []);
        $this->assertNotNull($response->json('data.photos.0.thumb_url'));
    }
}
