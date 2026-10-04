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
 * The three legal pages are materialised too, in **every** locale as `machine`.
 * They used to be deliberately absent from this table, on the grounds that their
 * gate was their own `status: draft` in the content — that was wrong: the site
 * declares `legal-privacy`, `legal-cookies` and `legal-terms` as first-class page
 * ids alongside the other seven, so the pairs have to exist here for the client to
 * be able to review and approve them at all. An absent row and an unpublished row
 * both mean "not indexable", but only the second can be approved by a human.
 *
 * They are `machine` in **all** locales, including the default one: `published`
 * means the client approved that page, and nobody has approved legal copy — the
 * body is still the "in preparation" notice. Seeding them `published` would put an
 * approval in the manifest that never happened.
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

    /**
     * The site's three legal page ids. Same table, different starting state: see
     * the note on this class about why they are `machine` everywhere.
     *
     * @var list<string>
     */
    private const LEGAL_PAGES = [
        'legal-privacy',
        'legal-cookies',
        'legal-terms',
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
        $pages = [...self::PAGES, ...self::LEGAL_PAGES];

        foreach ($locales as $locale) {
            foreach ($pages as $page) {
                PagePublication::query()->firstOrCreate(
                    ['site_id' => $site->getKey(), 'page' => $page, 'locale' => $locale],
                    [
                        // Legal pages never start published, in any locale: nobody has
                        // approved their copy, so claiming the default locale is live
                        // would be an approval this seeder cannot back.
                        'status' => $locale === $publishedLocale && ! in_array($page, self::LEGAL_PAGES, true)
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
            'PagePublicationSeeder: %d pairs (%d pages x %d locales) for site "%s"; published in "%s" (%d legal pages start as machine).',
            count($pages) * count($locales),
            count($pages),
            count($locales),
            $site->key,
            $publishedLocale,
            count(self::LEGAL_PAGES),
        ));
    }
}
