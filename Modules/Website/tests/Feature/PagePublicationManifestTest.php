<?php

declare(strict_types=1);

namespace Modules\Website\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Models\Site;
use Modules\Core\Tests\Support\InteractsWithOrganizationFixtures;
use Modules\Website\Models\PagePublication;
use Tests\TestCase;

/**
 * The publication manifest (CLA-611 + the build gate in `electro-bertels-official`).
 *
 * What matters here is not the JSON shape — it is short — but the two ways this
 * endpoint could quietly do damage: publishing something nobody approved, or
 * answering about the wrong site.
 */
final class PagePublicationManifestTest extends TestCase
{
    use InteractsWithOrganizationFixtures;
    use RefreshDatabase;

    private const ENDPOINT = '/api/v1/website/publication-manifest?site=electro-bertels';

    public function test_the_manifest_has_the_shape_the_build_writes_to_its_snapshot(): void
    {
        $site = $this->bertelsFixtureSite();
        $this->approve($site, 'home', 'nl');
        $this->approve($site, 'contact', 'nl');

        $response = $this->getJson(self::ENDPOINT)->assertOk();

        $this->assertSame(1, $response->json('version'));
        $this->assertSame(
            ['contact' => ['nl'], 'home' => ['nl']],
            $response->json('published'),
            'the snapshot is deterministic: pages sorted, locales sorted',
        );
    }

    public function test_only_published_pages_appear(): void
    {
        $site = $this->bertelsFixtureSite();
        $this->approve($site, 'home', 'nl', PagePublication::STATUS_PUBLISHED);
        $this->approve($site, 'contact', 'nl', PagePublication::STATUS_REVIEWED);
        $this->approve($site, 'winkel', 'nl', PagePublication::STATUS_MACHINE);

        $published = $this->getJson(self::ENDPOINT)->assertOk()->json('published');

        // `reviewed` and `machine` are real states that publish nothing: the
        // build asks one question, and only `published` answers yes to it.
        $this->assertSame(['home' => ['nl']], $published);
    }

    public function test_a_page_can_be_published_in_one_locale_and_not_in_another(): void
    {
        $site = $this->bertelsFixtureSite();
        $this->approve($site, 'home', 'nl', PagePublication::STATUS_PUBLISHED);
        $this->approve($site, 'home', 'fr', PagePublication::STATUS_MACHINE);

        // The whole reason the state is per (page, locale) and not per locale:
        // FR can be approved on one page while another page is still a draft.
        $this->assertSame(['home' => ['nl']], $this->getJson(self::ENDPOINT)->json('published'));
    }

    public function test_pages_of_another_site_never_leak_into_the_manifest(): void
    {
        $bertels = $this->bertelsFixtureSite();
        $other = Site::query()->create(['key' => 'otro-sitio', 'name' => 'Otro sitio']);

        $this->approve($bertels, 'home', 'nl');
        $this->approve($other, 'contact', 'nl');

        // The model's global scope only applies while `organizations.enforce` is
        // on, which is off in local and demo environments — so this read is
        // filtered explicitly, and this test runs with enforcement off on purpose.
        $this->assertSame(['home' => ['nl']], $this->getJson(self::ENDPOINT)->json('published'));
    }

    public function test_nothing_approved_is_an_empty_map_not_an_empty_list(): void
    {
        $this->bertelsFixtureSite();

        $response = $this->getJson(self::ENDPOINT)->assertOk();

        // `{}` is a map with no entries, `[]` is a list: the build reads this as a
        // page → locales map, and the two must not be confused.
        $this->assertSame('{}', $response->getContent() === '' ? '' : json_encode($response->json('published')));
    }

    public function test_a_page_cannot_be_published_twice_in_the_same_locale(): void
    {
        $site = $this->bertelsFixtureSite();
        $this->approve($site, 'home', 'nl');

        $this->expectException(\Illuminate\Database\UniqueConstraintViolationException::class);

        $this->approve($site, 'home', 'nl', PagePublication::STATUS_MACHINE);
    }

    private function approve(
        Site $site,
        string $page,
        string $locale,
        string $status = PagePublication::STATUS_PUBLISHED,
    ): PagePublication {
        return PagePublication::query()->create([
            'site_id' => $site->getKey(),
            'page' => $page,
            'locale' => $locale,
            'status' => $status,
        ]);
    }
}
