<?php

declare(strict_types=1);

namespace Modules\Website\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Models\Concerns\BelongsToSite;
use Modules\Core\Models\Site;
use Modules\Core\Models\User;

/**
 * Whether a **static page** of this site is approved for publication in a
 * locale (CLA-611, and the build gate in the Astro repository).
 *
 * The copy of the eight pages lives in Git (decision D-F), so those pages have
 * no model and no row of their own — and that is why they had no place to record
 * an approval, which is how the site ended up deciding with a constant in
 * TypeScript.
 *
 * **Not** `PublicationState` (`website_publication_states`): that one is the
 * *deploy* state (idle/dispatched/pending/accepted plus the webhook dispatch).
 * Similar names, different questions — hence `PagePublication`.
 *
 * Granularity is per page, not per content key: the site's plan settled it
 * (D-C: review per page, so a section can move forward), and asking the client to
 * approve 274 keys is not a workflow. No row at all means `missing`.
 *
 * The three statuses implemented are the ones the client's flow needs; `stale`
 * (a source change invalidating an approved translation, gap G1) is deliberately
 * not here yet: nothing can detect it for copy that has no row to compare
 * against, and inventing a state nothing can reach would be a lie.
 */
class PagePublication extends Model
{

    use BelongsToSite;

    /** A draft: it renders, carrying the notice, and is never indexed. */
    public const STATUS_MACHINE = 'machine';

    /** A human read it, and has not published it yet. Still not indexed. */
    public const STATUS_REVIEWED = 'reviewed';

    /** The only status that makes a page indexable. */
    public const STATUS_PUBLISHED = 'published';

    /** @var list<string> */
    public const STATUSES = [self::STATUS_MACHINE, self::STATUS_REVIEWED, self::STATUS_PUBLISHED];

    protected $table = 'website_page_publications';

    protected $fillable = [
        'site_id',
        'page',
        'locale',
        'status',
        'reviewed_by_user_id',
        'reviewed_at',
    ];

    protected function casts(): array
    {
        return ['reviewed_at' => 'datetime'];
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_user_id');
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PUBLISHED);
    }

    /**
     * The manifest the static site's build consumes: `page → published locales`.
     *
     * Only `published` rows ever appear here — `machine` and `reviewed` are real
     * states but they publish nothing, because the build only asks one question:
     * may this page be indexed in this locale?
     *
     * The site is filtered **explicitly** even though the trait already scopes
     * the query: that scope only applies while `organizations.enforce` is on, and
     * a manifest that silently spans two sites in a demo is exactly the leak this
     * must not have.
     *
     * @return array<string, list<string>>
     */
    public static function manifestFor(Site $site): array
    {
        return static::query()
            ->withoutGlobalScope('site')
            ->where('site_id', $site->getKey())
            ->published()
            ->orderBy('page')
            ->orderBy('locale')
            ->get()
            ->groupBy('page')
            ->map(static fn ($rows): array => $rows->pluck('locale')->values()->all())
            ->all();
    }
}
