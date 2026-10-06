<?php

declare(strict_types=1);

namespace Modules\Website\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
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

        // An explicit host, not a relative path. A relative request inherits
        // `config('app.url')`, so asserting a literal origin here made this test depend on
        // APP_URL — passing with `http://localhost` and failing with
        // `http://localhost:8004`, which is the coupling its own comment below says it
        // dropped (CLA-444's lesson). The host is given by this test, so the assertion is
        // self-contained. `test_the_media_url_origin_follows_the_request_host` covers the
        // same rule with a different host, on purpose.
        $response = $this->getJson('http://media.test/v1/website/media/slots')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $data = $response->json('data.0');

        $this->assertSame('home.hero', $data['slot']);
        // The origin comes from the request, the path from the disk. Asserting the
        // full `$media->getUrl('optimized')` would re-introduce the coupling to the
        // disk's own URL config.
        $this->assertStringStartsWith('http://media.test/storage/', $data['url']);
        $this->assertStringEndsWith($media->getPathRelativeToRoot('optimized'), $data['url']);
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

    /**
     * Regression: `alt` and `caption` must be JSON **objects** even when they
     * have no entries. `localeMap()` returns a PHP array, and an empty PHP array
     * serialises as `[]` — a list — which the site's `sync-content.mjs` rejects
     * with "caption must be an object keyed by locale", refusing the whole
     * surface. The `(object)` cast in `MediaSlotController::serialize()` is what
     * keeps them objects; this test fails if it is removed.
     *
     * Decoding with `assoc: false` is the point: `{}` yields `stdClass`, `[]`
     * yields `array`, so the assertion sees the difference the contract cares
     * about — which `assertSame([], ...)` never could.
     */
    public function test_alt_and_caption_are_json_objects_even_when_empty(): void
    {
        $site = Site::find(Site::claesenId());
        $project = Project::factory()->create(['site_id' => $site->id]);
        $media = $project->addMedia(
            UploadedFile::fake()->image('slot.png', 1200, 800)
        )->toMediaCollection('gallery');

        // Rights confirmed, but no alt and no caption set at all.
        $media->setCustomProperty('usage_rights_confirmed_at', now()->toIso8601String());
        $media->save();

        MediaSlot::create([
            'site_id' => $site->id,
            'slot' => 'home.hero',
            'media_id' => $media->id,
        ]);

        $response = $this->getJson('/v1/website/media/slots')->assertOk();
        $body = $response->getContent();

        // Eyeball-level guarantee: the published bytes really say `{}`.
        $this->assertStringContainsString('"alt":{}', $body);
        $this->assertStringContainsString('"caption":{}', $body);

        $objects = json_decode($body);
        $this->assertInstanceOf(\stdClass::class, $objects->data[0]->alt);
        $this->assertInstanceOf(\stdClass::class, $objects->data[0]->caption);

        // And the same for a media that does have entries.
        $media->setCustomProperty('alt', ['nl' => 'NL alt']);
        $media->save();

        $withEntries = json_decode($this->getJson('/v1/website/media/slots')->assertOk()->getContent());
        $this->assertInstanceOf(\stdClass::class, $withEntries->data[0]->alt);
        $this->assertSame('NL alt', $withEntries->data[0]->alt->nl);
    }

    /**
     * `mime_type` must describe the file the consumer downloads — the conversion —
     * not the private original. The tempting `$media->mime_type` is the original's,
     * so a PNG original converted to WebP would advertise `image/png` for WebP
     * bytes; a consumer that trusts the field to pick a decoder is misled.
     *
     * The conversion here is a **real** WebP, generated rather than faked: a test
     * that left the PNG bytes in place would keep passing while the bug was present,
     * because then the two mime types genuinely coincide.
     */
    public function test_the_mime_type_describes_the_conversion_not_the_original(): void
    {
        [, $media, $bytes] = $this->createProjectMedia(Site::find(Site::claesenId()));

        MediaSlot::create([
            'site_id' => Site::claesenId(),
            'slot' => 'home.hero',
            'media_id' => $media->id,
        ]);

        // The upload is a PNG (see createProjectMedia), and the original keeps saying so.
        $this->assertSame('image/png', $media->mime_type);

        $image = imagecreatefromstring($bytes);
        $this->assertNotFalse($image, 'the fixture must be a readable image');

        ob_start();
        imagewebp($image);
        $webp = (string) ob_get_clean();
        imagedestroy($image);

        $this->assertNotSame('', $webp, 'the test environment must be able to encode WebP');

        Storage::disk('public')->put($media->getPathRelativeToRoot('optimized'), $webp);

        $this->assertSame('image/webp', $this->getJson('/v1/website/media/slots')->json('data.0.mime_type'));
    }

    /**
     * The URL must be usable from wherever the request came from: the deployment's
     * APP_URL is routinely not the address a consumer reaches the API by (internal
     * host vs public domain), and the site's build downloads these URLs from
     * whichever machine runs the build. Taken from the request, the URL is correct
     * for that caller by construction.
     */
    public function test_the_media_url_origin_follows_the_request_host(): void
    {
        $site = Site::find(Site::claesenId());
        $project = Project::factory()->create(['site_id' => $site->id]);
        $media = $project->addMedia(
            UploadedFile::fake()->image('slot.png', 1200, 800)
        )->toMediaCollection('gallery');

        $media->setCustomProperty('usage_rights_confirmed_at', now()->toIso8601String());
        $media->save();

        MediaSlot::create([
            'site_id' => $site->id,
            'slot' => 'home.hero',
            'media_id' => $media->id,
        ]);

        $response = $this->getJson('http://build.bertels.test/v1/website/media/slots')->assertOk();

        $url = $response->json('data.0.url');
        $this->assertStringStartsWith('http://build.bertels.test/storage/', $url);
        $this->assertStringEndsWith($media->getPathRelativeToRoot('optimized'), $url);

        // The body depends on the Host, and the endpoint is served as `public,
        // max-age=60`: without `Vary: Host` a shared cache could hand one host the
        // other's URLs. (`Vary` is a comma list and CORS contributes `Origin`, so
        // this asserts membership, not equality.)
        $this->assertStringContainsString('Host', (string) $response->headers->get('Vary'));
    }

    /**
     * A slot whose conversion is not on disk yet must not be remembered as empty.
     *
     * The endpoint cached the "no dimensions" answer for an hour, so a slot seeded or
     * uploaded moments before a build answered `width`/`height`/`checksum` as null and
     * kept answering that long after the queue had written the file. The site's sync
     * requires positive integers, so it rejected the whole surface for that.
     *
     * `phpunit.xml` sets `CACHE_STORE=array`, which is per-request and would hide the
     * bug entirely — hence the switch to a store that survives between requests.
     */
    public function test_a_conversion_that_is_not_ready_yet_is_not_cached_as_empty(): void
    {
        config(['cache.default' => 'file']);
        Cache::flush();

        [$project, $media, $bytes] = $this->createProjectMedia(Site::find(Site::claesenId()));

        MediaSlot::create([
            'site_id' => Site::claesenId(),
            'slot' => 'home.hero',
            'media_id' => $media->id,
        ]);

        // La cola todavía no la generó: el fichero no está.
        $conversion = $media->getPathRelativeToRoot('optimized');
        Storage::disk('public')->delete($conversion);

        $this->assertNull($this->getJson('/v1/website/media/slots')->json('data.0.width'));

        // La cola termina y la conversión aparece. La próxima respuesta tiene que verla.
        Storage::disk('public')->put($conversion, $bytes);

        $this->assertSame(1200, $this->getJson('/v1/website/media/slots')->json('data.0.width'));
        $this->assertSame('image/png', $this->getJson('/v1/website/media/slots')->json('data.0.mime_type'));

        Cache::flush();
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
