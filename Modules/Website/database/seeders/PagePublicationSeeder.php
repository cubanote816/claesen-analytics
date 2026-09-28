<?php

declare(strict_types=1);

namespace Modules\Website\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Core\Models\Site;
use Modules\Website\Models\PagePublication;

/**
 * The publication state as it actually stands when this slice lands.
 *
 * It exists because the state is a **decision**, not a schema default: the Dutch
 * copy of the seven content pages is live and approved, and the three legal pages
 * are deliberately not (their gate is their own draft status, not this table).
 *
 * Seeding rather than a data migration on purpose: a migration must not decide
 * editorial content, and this is the one row set that has to exist *before* the
 * first sync runs — otherwise the manifest would come back empty and the site
 * would flip itself to draft.
 *
 * Only sets rows that are missing, so it is safe to re-run after a sync: an
 * approval made later by a person is never overwritten.
 */
class PagePublicationSeeder extends Seeder
{
    /**
     * The content pages whose NL copy is approved, and the locales the site
     * actually has. fr/en/de stay out until somebody approves them.
     *
     * @var list<string>
     */
    private const PAGES = [
        'home',
        'particulieren',
        'bedrijven',
        'winkel',
        'projecten',
        'over-ons',
        'contact',
    ];

    public function run(): void
    {
        $site = Site::query()->where('key', 'electro-bertels')->first();

        if ($site === null) {
            $this->command?->warn('PagePublicationSeeder: no "electro-bertels" site; nothing seeded.');

            return;
        }

        foreach (self::PAGES as $page) {
            PagePublication::query()->firstOrCreate(
                ['site_id' => $site->getKey(), 'page' => $page, 'locale' => 'nl'],
                [
                    'status' => PagePublication::STATUS_PUBLISHED,
                    // No author: nobody clicked approve for this initial state, and
                    // crediting a user who never did it would be a false record.
                    'reviewed_by_user_id' => null,
                    'reviewed_at' => null,
                ],
            );
        }

        $this->command?->info(sprintf(
            'PagePublicationSeeder: %d pages published in nl for site "%s".',
            count(self::PAGES),
            $site->key,
        ));
    }
}
