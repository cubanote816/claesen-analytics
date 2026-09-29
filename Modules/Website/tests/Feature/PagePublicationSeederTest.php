<?php

declare(strict_types=1);

namespace Modules\Website\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Models\Site;
use Modules\Core\Tests\Support\InteractsWithOrganizationFixtures;
use Modules\Website\Database\Seeders\PagePublicationSeeder;
use Modules\Website\Models\PagePublication;
use Tests\TestCase;

/**
 * The seeder that materialises the publication state: every `(page, locale)`
 * pair the site declares, with its default locale live and the rest as drafts.
 *
 * It exists as a test because the seeder is the one piece of this feature that
 * no other test touched — and it is the piece that decides what the *live* site
 * publishes. Three properties matter and none is obvious from reading it:
 *
 *   * pairs exist for locales nobody has translated yet (they are the ones the
 *     client still has to approve, so they must be rows, not absences);
 *   * it never overwrites a decision a human made later in the backoffice; and
 *   * a site that is not registered yet is skipped, not seeded into a void.
 *
 * This class is also the only place that proves the seeder is reachable at all:
 * `Modules\Website\Database\Seeders\` needs its own PSR-4 entry in the root
 * `composer.json`, and without it this file fails to load.
 */
final class PagePublicationSeederTest extends TestCase
{
    use InteractsWithOrganizationFixtures;
    use RefreshDatabase;

    private const PAGES = [
        'bedrijven',
        'contact',
        'home',
        'over-ons',
        'particulieren',
        'projecten',
        'winkel',
    ];

    private const LOCALES = ['nl', 'en', 'fr', 'de'];

    /** The site the seeder looks for, with the locales the real site declares. */
    private function bertelsSite(): Site
    {
        return $this->bertelsFixtureSite(attributes: [
            'key' => 'electro-bertels',
            'locales' => self::LOCALES,
            'default_locale' => 'nl',
            'status' => Site::STATUS_ACTIVE,
        ]);
    }

    public function test_it_materialises_every_page_and_locale_the_site_declares(): void
    {
        $this->bertelsSite();

        $this->seed(PagePublicationSeeder::class);

        // 7 content pages x 4 locales. The three legal pages stay out on purpose:
        // their gate is their own draft status in the content, not this table.
        $this->assertSame(count(self::PAGES) * count(self::LOCALES), PagePublication::query()->count());
    }

    public function test_it_publishes_only_the_site_default_locale(): void
    {
        $this->bertelsSite();

        $this->seed(PagePublicationSeeder::class);

        $published = PagePublication::query()
            ->where('status', PagePublication::STATUS_PUBLISHED)
            ->orderBy('page')
            ->get();

        $this->assertSame(self::PAGES, $published->pluck('page')->all());
        $this->assertSame(['nl'], $published->pluck('locale')->unique()->values()->all());

        // Everything else is a draft, which the manifest never publishes: an
        // untranslated locale cannot become indexable by being seeded.
        $this->assertSame(
            count(self::PAGES) * (count(self::LOCALES) - 1),
            PagePublication::query()->where('status', PagePublication::STATUS_MACHINE)->count(),
        );
    }

    public function test_it_records_no_author_because_nobody_approved_this_yet(): void
    {
        $this->bertelsSite();

        $this->seed(PagePublicationSeeder::class);

        // Crediting a user who never clicked approve would be a false record, so
        // the initial state is explicitly anonymous.
        $this->assertSame(0, PagePublication::query()->whereNotNull('reviewed_by_user_id')->count());
        $this->assertSame(0, PagePublication::query()->whereNotNull('reviewed_at')->count());
    }

    public function test_it_is_idempotent_and_never_overwrites_a_later_decision(): void
    {
        $site = $this->bertelsSite();

        $this->seed(PagePublicationSeeder::class);

        // A human publishes French on one page after the first run — the seeder
        // must not resurrect the draft on the next seed, which is the whole
        // reason it uses firstOrCreate rather than upsert.
        PagePublication::query()
            ->where('site_id', $site->getKey())
            ->where('page', 'winkel')
            ->where('locale', 'fr')
            ->update(['status' => PagePublication::STATUS_PUBLISHED]);

        $this->seed(PagePublicationSeeder::class);

        $this->assertSame(
            PagePublication::STATUS_PUBLISHED,
            PagePublication::query()
                ->where('site_id', $site->getKey())
                ->where('page', 'winkel')
                ->where('locale', 'fr')
                ->value('status'),
        );
        $this->assertSame(count(self::PAGES) * count(self::LOCALES), PagePublication::query()->count());
    }

    public function test_it_skips_a_site_that_is_not_registered_yet(): void
    {
        // Registering the site is a manual step; before it happens the seeder
        // must do nothing rather than guess which site Bertels is.
        $this->bertelsFixtureSite();

        $this->seed(PagePublicationSeeder::class);

        $this->assertSame(0, PagePublication::query()->count());
    }

    public function test_it_does_nothing_when_the_site_declares_no_locale(): void
    {
        $this->bertelsFixtureSite(attributes: [
            'key' => 'electro-bertels',
            'locales' => [],
        ]);

        $this->seed(PagePublicationSeeder::class);

        $this->assertSame(0, PagePublication::query()->count());
    }
}
