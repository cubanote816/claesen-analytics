<?php

declare(strict_types=1);

namespace Modules\Website\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Modules\Core\Models\Site;
use Modules\Intelligence\Services\GeminiService;
use Modules\Website\Models\MediaSlot;
use Modules\Website\Models\Project;
use Tests\TestCase;

/**
 * CLA-481: GET /v1/website/media/slots — shape, isolation, and the CLA-467
 * boundaries (conversion URLs only, originals never exposed; usage-rights
 * gate) and the CLA-611 G9 rule (per-locale maps contain only real locales).
 */
final class MediaSlotsApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // addMedia + observers (NotifyAstroFrontendJob, metadata job) must
        // not reach the network or a real Gemini; conversions are not
        // generated in tests unless explicitly written to the fake disk.
        Queue::fake();
        Http::fake();
        Storage::fake('public');

        $this->mock(GeminiService::class, function ($mock): void {
            $mock->shouldReceive('translateAndDetect')->andReturn(['translations' => []]);
        });

        app()->setLocale('nl');
    }

    private function createProjectMedia(Site $site, string $collection = 'gallery')
    {
        $project = Project::factory()->create(['site_id' => $site->id]);
        $file = UploadedFile::fake()->image('slot.png', 1200, 800);
        $bytes = $file->getContent();

        $media = $project->addMedia($file)->toMediaCollection($collection);

        $media->setCustomProperty('usage_rights_confirmed_at', now()->toIso8601String());
        $media->setCustomProperty('usage_rights_confirmed_by', 'test');
        $media->setCustomProperty('alt', ['nl' => 'NL alt', 'fr' => 'FR alt', 'en' => '']);
        $media->setCustomProperty('caption', ['nl' => 'NL caption']);
        $media->save();

        // Place the "optimized" conversion manually on the fake public disk —
        // conversion jobs are queue-faked in tests. The endpoint reads its
        // dimensions/checksum from this file.
        $conversionPath = $media->getPathRelativeToRoot('optimized');
        Storage::disk('public')->put($conversionPath, $bytes);

        return [$project, $media, $bytes];
    }

    public function test_it_serves_slots_with_conversion_url_dimensions_checksum_and_locale_maps(): void
    {
        [$project, $media, $bytes] = $this->createProjectMedia(Site::find(Site::claesenId()));

        MediaSlot::create([
            'site_id' => Site::claesenId(),
            'slot' => 'home.hero',
            'media_id' => $media->id,
        ]);

        $response = $this->getJson('/v1/website/media/slots')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $data = $response->json('data.0');

        $this->assertSame('home.hero', $data['slot']);
        $this->assertSame($media->getUrl('optimized'), $data['url']);
        $this->assertSame(1200, $data['width']);
        $this->assertSame(800, $data['height']);
        $this->assertSame('sha256:'.hash('sha256', $bytes), $data['checksum']);
        $this->assertSame('image/png', $data['mime_type']);

        // G9 applied to maps: only locales with an actual value appear —
        // 'en' was empty and is absent, never coalesced to another locale.
        // (Key order from the JSON round-trip is not guaranteed.)
        ksort($data['alt']);
        $this->assertSame(['fr' => 'FR alt', 'nl' => 'NL alt'], $data['alt']);
        $this->assertSame(['nl' => 'NL caption'], $data['caption']);
    }

    public function test_slots_without_confirmed_usage_rights_are_omitted(): void
    {
        $site = Site::find(Site::claesenId());
        $project = Project::factory()->create(['site_id' => $site->id]);
        $media = $project->addMedia(
            UploadedFile::fake()->image('slot.png', 1200, 800)
        )->toMediaCollection('gallery');

        MediaSlot::create([
            'site_id' => $site->id,
            'slot' => 'home.hero',
            'media_id' => $media->id,
        ]);

        $this->getJson('/v1/website/media/slots')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_slots_are_scoped_to_the_resolved_site(): void
    {
        $claesenSite = Site::find(Site::claesenId());
        $bertelsSite = Site::factory()->create(['key' => 'bertels-fixture-slots']);

        [, $claesenMedia] = $this->createProjectMedia($claesenSite);
        [, $bertelsMedia] = $this->createProjectMedia($bertelsSite);

        MediaSlot::create(['site_id' => $claesenSite->id, 'slot' => 'home.hero', 'media_id' => $claesenMedia->id]);
        MediaSlot::create(['site_id' => $bertelsSite->id, 'slot' => 'home.hero', 'media_id' => $bertelsMedia->id]);

        $this->getJson('/v1/website/media/slots')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            // Distinguishing mark: the alt texts differ per project.
            ->assertJsonPath('data.0.alt.nl', 'NL alt');

        $this->getJson('/v1/website/media/slots?site=bertels-fixture-slots')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_a_slot_whose_media_was_deleted_disappears_from_the_listing(): void
    {
        [, $media] = $this->createProjectMedia(Site::find(Site::claesenId()));

        MediaSlot::create([
            'site_id' => Site::claesenId(),
            'slot' => 'home.hero',
            'media_id' => $media->id,
        ]);

        $media->delete();

        $this->getJson('/v1/website/media/slots')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }
}
