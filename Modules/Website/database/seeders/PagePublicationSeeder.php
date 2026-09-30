<?php

declare(strict_types=1);

namespace Modules\Website\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Core\Models\Site;
use Modules\Website\Models\PagePublication;

/**
 * Records the publication state of the content pages.
 *
 * Two things happen here, and only the first is about "today's truth":
 *
 *   1. Every `(page, locale)` pair the site declares is **materialised**. The
 *      client approves pairs, so the pairs have to exist — including the ones
 *      nobody has translated yet, which is the majority. A row that is absent
 *      and a row that is unpublished mean the same thing to the build (no index),
 *      but only the second one can be reviewed and approved.
 *   2. The site's own default locale starts as `published` — what is live today —
 *      and every other locale starts as `machine`: it is a draft, it is NOT
 *      indexable (the manifest only ever publishes `published`), and it is
 *      therefore exactly the right starting point while those locales have
 *      neither approved copy nor, in most cases, a slug.
 *
 * The three legal pages are deliberately **absent**: their gate is their own
 * `status: draft` in the content, not this table.
 *
 * `firstOrCreate`, never an upsert: a decision a human made in the backoffice
 * must survive every later run of this seeder.
 */
class PagePublicationSeeder extends Seeder
{
    private const PAGES = [
        'home',
        'particulieren',
        'bedrijven',
        'winkel',
        'projecten',
        'over-ons',
        'contact',
    ];

    /** Fallback when a site somehow declares no locale at all. */
    private const FALLBACK_LOCALE = 'nl';

    public function run(): void
    {
        $site = Site::query()->where('key', 'electro-bertels')->first();

        if ($site === null) {
            $this->command?->warn('PagePublicationSeeder: no "electro-bertels" site; nothing seeded.');

            return;
        }

        $locales = array_values(array_filter((array) $site->locales, 'is_string'));

        if ($locales === []) {
            $this->command?->warn(sprintf(
                'PagePublicationSeeder: site "%s" declares no locale; nothing seeded.',
                $site->key,
            ));

            return;
        }

        $publishedLocale = $site->default_locale ?: self::FALLBACK_LOCALE;

        foreach ($locales as $locale) {
            foreach (self::PAGES as $page) {
                PagePublication::query()->firstOrCreate(
                    ['site_id' => $site->getKey(), 'page' => $page, 'locale' => $locale],
                    [
                        'status' => $locale === $publishedLocale
                            ? PagePublication::STATUS_PUBLISHED
                            : PagePublication::STATUS_MACHINE,
                        // No author: nobody clicked approve for this initial state, and
                        // crediting a user who never did it would be a false record.
                        'reviewed_by_user_id' => null,
                        'reviewed_at' => null,
                    ],
                );
            }
        }

        $this->command?->info(sprintf(
            'PagePublicationSeeder: %d pairs (%d pages x %d locales) for site "%s"; published in "%s".',
            count(self::PAGES) * count($locales),
            count(self::PAGES),
            count($locales),
            $site->key,
            $publishedLocale,
        ));
    }
}
