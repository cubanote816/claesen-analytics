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
 * The seeder that records today's editorial truth: the content pages published
 * in Dutch.
 *
 * It exists as a test because the seeder is the one piece of this feature that
 * no other test touched — and it is the piece that decides what the *live* site
 * publishes. Two properties matter and neither is obvious from reading it:
 *
 *   * it never overwrites a decision a human made later in the backoffice, and
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

    /** The site the seeder looks for, created the way the fixture creates sites. */
    private function bertelsSite(): Site
    {
        return $this->bertelsFixtureSite(attributes: [
            'key' => 'electro-bertels',
            'status' => Site::STATUS_ACTIVE,
        ]);
    }

    public function test_it_publishes_the_content_pages_in_dutch(): void
    {
        $site = $this->bertelsSite();

        $this->seed(PagePublicationSeeder::class);

        $rows = PagePublication::query()
            ->where('site_id', $site->getKey())
            ->where('locale', 'nl')
            ->orderBy('page')
            ->get();

        $this->assertSame(self::PAGES, $rows->pluck('page')->all());
        $this->assertSame(
            [PagePublication::STATUS_PUBLISHED],
            $rows->pluck('status')->unique()->all(),
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

        // A human demotes one page after the first run — the seeder must not
        // resurrect it on the next seed, which is the whole reason it uses
        // firstOrCreate rather than upsert.
        PagePublication::query()
            ->where('site_id', $site->getKey())
            ->where('page', 'winkel')
            ->update(['status' => PagePublication::STATUS_MACHINE]);

        $this->seed(PagePublicationSeeder::class);

        $this->assertSame(
            PagePublication::STATUS_MACHINE,
            PagePublication::query()
                ->where('site_id', $site->getKey())
                ->where('page', 'winkel')
                ->value('status'),
        );
        $this->assertSame(count(self::PAGES), PagePublication::query()->count());
    }

    public function test_it_skips_a_site_that_is_not_registered_yet(): void
    {
        // Registering the site is a manual step; before it happens the seeder
        // must do nothing rather than guess which site Bertels is.
        $this->bertelsFixtureSite();

        $this->seed(PagePublicationSeeder::class);

        $this->assertSame(0, PagePublication::query()->count());
    }
}
