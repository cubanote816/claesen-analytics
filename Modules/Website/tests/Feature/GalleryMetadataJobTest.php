<?php

declare(strict_types=1);

namespace Modules\Website\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Modules\Intelligence\Services\GeminiService;
use Modules\Website\Jobs\GenerateGalleryMediaMetadataJob;
use Modules\Website\Jobs\TriggerStaticSiteRebuildJob;
use Modules\Website\Models\Project;
use Tests\TestCase;

/**
 * Tests for GenerateGalleryMediaMetadataJob.
 * Covers WEB-009 — Gemini caption/alt generation for gallery images.
 * Gemini is always mocked; no real API calls are made.
 */
class GalleryMetadataJobTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Capture all job dispatches — prevents NotifyAstroFrontendJob from
        // attempting real HTTP calls to GitHub and MediaObserver cascades.
        Queue::fake();
        Storage::fake('public');
    }

    // =========================================================================
    // Happy path — Gemini returns metadata; media is persisted
    // =========================================================================

    public function test_job_persists_caption_and_alt_from_gemini(): void
    {
        $project = Project::factory()->create();

        $file  = UploadedFile::fake()->image('gallery.jpg', 800, 600);
        $media = $project->addMedia($file)->toMediaCollection('gallery');

        $gemini = $this->mock(GeminiService::class, function ($mock): void {
            $mock->shouldReceive('generateMediaMetadata')
                ->once()
                ->andReturn([
                    'caption' => ['nl' => 'NL caption', 'en' => 'EN caption', 'fr' => 'FR caption', 'de' => 'DE caption'],
                    'alt'     => ['nl' => 'NL alt',     'en' => 'EN alt',     'fr' => 'FR alt',     'de' => 'DE alt'],
                ]);
        });

        (new GenerateGalleryMediaMetadataJob($media->id))->handle($gemini);

        $media->refresh();

        $this->assertEquals('NL caption', $media->getCustomProperty('caption')['nl']);
        $this->assertEquals('EN caption', $media->getCustomProperty('caption')['en']);
        $this->assertEquals('NL alt',     $media->getCustomProperty('alt')['nl']);
        $this->assertEquals('DE alt',     $media->getCustomProperty('alt')['de']);
    }

    // =========================================================================
    // NotifyAstroFrontendJob is dispatched in the finally block
    // =========================================================================

    public function test_job_dispatches_notify_frontend_job(): void
    {
        $project = Project::factory()->create();
        $file    = UploadedFile::fake()->image('gallery2.jpg');
        $media   = $project->addMedia($file)->toMediaCollection('gallery');

        $gemini = $this->mock(GeminiService::class, function ($mock): void {
            $mock->shouldReceive('generateMediaMetadata')
                ->once()
                ->andReturn([
                    'caption' => ['nl' => 'x', 'en' => 'x', 'fr' => 'x', 'de' => 'x'],
                    'alt'     => ['nl' => 'x', 'en' => 'x', 'fr' => 'x', 'de' => 'x'],
                ]);
        });

        config(['static_site.enabled' => true]);
        Queue::fake(); // Reset captures from addMedia above
        (new GenerateGalleryMediaMetadataJob($media->id))->handle($gemini);

        Queue::assertPushed(TriggerStaticSiteRebuildJob::class);
    }

    // =========================================================================
    // Gemini is skipped when all locales are already complete
    // =========================================================================

    public function test_job_skips_gemini_when_all_locales_already_filled(): void
    {
        $project = Project::factory()->create();
        $file    = UploadedFile::fake()->image('gallery3.jpg');
        $media   = $project->addMedia($file)->toMediaCollection('gallery');

        // Pre-fill all four locales so isComplete() returns true
        $media->setCustomProperty('caption', ['nl' => 'A', 'en' => 'B', 'fr' => 'C', 'de' => 'D']);
        $media->setCustomProperty('alt',     ['nl' => 'A', 'en' => 'B', 'fr' => 'C', 'de' => 'D']);
        $media->saveQuietly();

        $gemini = $this->mock(GeminiService::class, function ($mock): void {
            $mock->shouldNotReceive('generateMediaMetadata');
        });

        (new GenerateGalleryMediaMetadataJob($media->id))->handle($gemini);
    }

    // =========================================================================
    // Non-existent media ID is handled gracefully
    // =========================================================================

    public function test_job_handles_missing_media_gracefully(): void
    {
        $gemini = $this->mock(GeminiService::class, function ($mock): void {
            $mock->shouldNotReceive('generateMediaMetadata');
        });

        // Should not throw — job just returns early
        (new GenerateGalleryMediaMetadataJob(99999))->handle($gemini);
    }
}
