<?php

declare(strict_types=1);

namespace Modules\Website\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Modules\Core\Models\Concerns\BelongsToSite;
use Modules\Core\Services\OrganizationContext;
use Modules\Website\Database\Factories\AnnouncementFactory;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\Translatable\HasTranslations;

/**
 * F3/CLA-469 of the multi-organization program — docs/ai/adr-multi-organization.md.
 *
 * A short-lived public notice per site — see the create-table migration's
 * docblock for how this differs from SiteSetting. `status` is the editorial
 * gate; `starts_at`/`ends_at` is the separate date window. scopeActive()
 * requires both: a published announcement outside its window, or a
 * currently-dated one still in draft/archived, is never public.
 */
class Announcement extends Model
{
    use BelongsToSite;
    use HasFactory;
    use HasTranslations;
    use LogsActivity;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_PUBLISHED = 'published';

    public const STATUS_ARCHIVED = 'archived';

    protected $table = 'website_announcements';

    protected $fillable = [
        'site_id',
        'message',
        'starts_at',
        'ends_at',
        'status',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
    ];

    public $translatable = ['message'];

    protected static function newFactory(): AnnouncementFactory
    {
        return AnnouncementFactory::new();
    }

    protected static function booted(): void
    {
        static::saved(fn (self $announcement) => self::forgetCacheFor($announcement->site_id));
        static::deleted(fn (self $announcement) => self::forgetCacheFor($announcement->site_id));
    }

    private static function cacheKeyFor(int $siteId): string
    {
        return "website.announcements.active.{$siteId}";
    }

    private static function forgetCacheFor(int $siteId): void
    {
        Cache::forget(self::cacheKeyFor($siteId));
    }

    /**
     * Cached-per-site read for the public API. A short TTL (rather than
     * settings' longer one) bounds how stale a starts_at/ends_at window
     * edge can appear without needing a scheduled cache-clear job — any
     * save/delete already forgets it immediately regardless.
     *
     * CLA-522 pattern — see SiteSetting::cachedForCurrentSite()'s docblock
     * for the full explanation: this app's real cache store can never
     * unserialize an Eloquent Collection back correctly, so the plain
     * array form is what actually gets cached, rehydrated on read.
     */
    public static function cachedActiveForCurrentSite(): Collection
    {
        $siteId = app(OrganizationContext::class)->siteId();

        if ($siteId === null) {
            return static::query()->active()->get();
        }

        $rows = Cache::remember(
            self::cacheKeyFor($siteId),
            300,
            fn () => static::query()->active()->get()->map->getAttributes()->all()
        );

        return static::hydrate($rows);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    /**
     * Published AND within its optional date window, evaluated against
     * $at (defaults to now()) so the query is deterministic in tests.
     */
    public function scopeActive(Builder $query, ?Carbon $at = null): Builder
    {
        $at ??= now();

        return $query->where('status', self::STATUS_PUBLISHED)
            ->where(function (Builder $query) use ($at): void {
                $query->whereNull('starts_at')->orWhere('starts_at', '<=', $at);
            })
            ->where(function (Builder $query) use ($at): void {
                $query->whereNull('ends_at')->orWhere('ends_at', '>=', $at);
            });
    }
}
