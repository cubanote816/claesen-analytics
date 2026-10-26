<?php

declare(strict_types=1);

namespace Modules\Website\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Modules\Intelligence\Services\GeminiService;
use Modules\Website\Models\Project;
use Modules\Website\Services\PortfolioService;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * F3/CLA-467 of the multi-organization program — docs/ai/adr-multi-organization.md.
 *
 * The gap this closes: Project's media originals lived on the public disk
 * and every public-facing accessor (Project::getApi*Attribute(),
 * Modules\Website\App\Http\Resources\ProjectResource,
 * PortfolioService::getProjectGallery()) served $media->getUrl() — the
 * original, full-resolution, unconverted file — directly to anyone.
 * Originals now live on the private 'local' disk
 * (Project::registerMediaCollections()); every accessor here only ever
 * returns a conversion URL (public disk). QUEUE_CONNECTION=sync in
 * phpunit.xml means conversions generate inline within these tests, no
 * queue worker needed.
 */
final class PrivateMediaOriginalsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Storage::fake('public');
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);

        // GenerateGalleryMediaMetadataJob (dispatched by MediaObserver on
        // every 'gallery' save) runs synchronously here (QUEUE_CONNECTION
        // =sync in phpunit.xml) — Queue::fake() is not an option because
        // this file's whole point is asserting real conversion FILES exist
        // on disk, and that would also swallow Spatie's own conversion
        // job. Mocking GeminiService is the same defence GalleryMetadataJobTest
        // uses, scoped to just the one real network dependency instead.
        $this->mock(GeminiService::class, function ($mock): void {
            $mock->shouldReceive('generateMediaMetadata')->andReturn([
                'caption' => ['nl' => 'c', 'en' => 'c', 'fr' => 'c', 'de' => 'c'],
                'alt' => ['nl' => 'a', 'en' => 'a', 'fr' => 'a', 'de' => 'a'],
            ]);
        });
    }

    public function test_the_original_file_is_stored_on_the_private_disk_never_on_public(): void
    {
        $project = Project::factory()->create();
        $media = $project->addMedia($this->fakeImage())->preservingOriginal()->toMediaCollection('gallery');

        $this->assertSame('local', $media->disk);
        $this->assertTrue(Storage::disk('local')->exists($media->getPathRelativeToRoot()));
        $this->assertFalse(Storage::disk('public')->exists($media->getPathRelativeToRoot()));
    }

    public function test_conversions_are_generated_in_both_webp_and_avif_on_the_public_disk(): void
    {
        $project = Project::factory()->create();
        $media = $project->addMedia($this->fakeImage())->preservingOriginal()->toMediaCollection('gallery');

        foreach (['thumb', 'optimized', 'gallery', 'thumb_avif', 'optimized_avif', 'gallery_avif'] as $conversion) {
            $this->assertTrue(
                Storage::disk('public')->exists($media->getPathRelativeToRoot($conversion)),
                "Expected conversion '{$conversion}' to exist on the public disk."
            );
        }
    }

    public function test_a_new_media_item_defaults_to_a_centered_focal_point(): void
    {
        $project = Project::factory()->create();
        $media = $project->addMedia($this->fakeImage())->preservingOriginal()->toMediaCollection('gallery');

        $this->assertSame(['x' => 0.5, 'y' => 0.5], $media->fresh()->getCustomProperty('focal_point'));
    }

    public function test_an_explicitly_set_focal_point_is_never_overwritten_by_a_later_save(): void
    {
        $project = Project::factory()->create();
        $media = $project->addMedia($this->fakeImage())->preservingOriginal()->toMediaCollection('gallery');

        $media->setCustomProperty('focal_point', ['x' => 0.2, 'y' => 0.8]);
        $media->save();

        // A second, unrelated save (e.g. touching another custom property)
        // must not reset the focal point back to center.
        $media->setCustomProperty('caption', ['en' => 'test']);
        $media->save();

        $this->assertSame(['x' => 0.2, 'y' => 0.8], $media->fresh()->getCustomProperty('focal_point'));
    }

    public function test_project_api_accessors_never_expose_the_original_url(): void
    {
        $project = Project::factory()->create();
        $project->addMedia($this->fakeImage())->preservingOriginal()->toMediaCollection('featured_image');
        $project->addMedia($this->fakeImage())->preservingOriginal()->toMediaCollection('gallery');

        $project->refresh();

        $this->assertStringContainsString('/conversions/', (string) $project->api_featured_image_url);

        $galleryItem = $project->api_gallery->first();
        $this->assertArrayNotHasKey('url', $galleryItem);
        $this->assertArrayNotHasKey('original', $galleryItem);
        $this->assertStringContainsString('/conversions/', $galleryItem['optimized']);
        $this->assertStringContainsString('/conversions/', $galleryItem['thumb_avif']);
        $this->assertSame(['x' => 0.5, 'y' => 0.5], $galleryItem['focal_point']);
    }

    public function test_the_public_api_project_detail_endpoint_never_exposes_an_original_url(): void
    {
        $project = Project::factory()->create(['published' => true]);
        $project->addMedia($this->fakeImage())->preservingOriginal()->toMediaCollection('featured_image');
        $project->addMedia($this->fakeImage())->preservingOriginal()->toMediaCollection('gallery');
        $project->addMedia($this->fakeImage())->preservingOriginal()->toMediaCollection('detail_gallery');

        $response = $this->getJson("/v1/website/projects/{$project->slug}")->assertOk();

        $body = $response->json('data');
        $this->assertArrayNotHasKey('original', $body['featured_image']);
        $this->assertArrayNotHasKey('original', $body['gallery'][0]);
        $this->assertArrayNotHasKey('original', $body['detail_gallery'][0]);
        $this->assertStringContainsString('/conversions/', $body['featured_image']['optimized_avif']);
        $this->assertSame(['x' => 0.5, 'y' => 0.5], $body['gallery'][0]['focal_point']);

        // No response field points at the un-converted original path shape
        // (Spatie stores originals directly under the media id, conversions
        // one level deeper under /conversions/) — a defence-in-depth check
        // on top of the explicit key assertions above.
        $this->assertStringNotContainsString('"url":', $response->getContent());
    }

    public function test_portfolio_service_gallery_helper_also_never_exposes_the_original(): void
    {
        $project = Project::factory()->create();
        $project->addMedia($this->fakeImage())->preservingOriginal()->toMediaCollection('gallery');

        $gallery = app(PortfolioService::class)->getProjectGallery($project->fresh());

        $this->assertStringContainsString('/conversions/', $gallery->first()['url']);
    }

    private function fakeImage(): UploadedFile
    {
        // Must return the UploadedFile object itself, not its ->getPathname()
        // string — Illuminate\Http\Testing\File deletes its own underlying
        // temp file once nothing references the object, which happens
        // immediately if only the path string survives this method call.
        return UploadedFile::fake()->image('test.jpg', 1000, 800);
    }
}
