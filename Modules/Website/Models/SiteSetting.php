<?php

declare(strict_types=1);

namespace Modules\Website\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Modules\Core\Models\Concerns\BelongsToSite;
use Modules\Core\Services\OrganizationContext;
use Modules\Website\Database\Factories\SiteSettingFactory;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * F3/CLA-469 of the multi-organization program — docs/ai/adr-multi-organization.md.
 *
 * A whitelisted key/value settings row per site — see the create-table
 * migration's docblock. `key` is validated at the application layer against
 * config('website.site_settings.allowed_keys'), never a DB enum.
 *
 * `value` is always stored as JSON; `resolvedValue()`/`setResolvedValue()`
 * interpret it according to `type` ('translatable' => locale-keyed array,
 * handled through this model's own accessor rather than Spatie's
 * HasTranslations trait, because a single model here represents settings of
 * different types — HasTranslations expects every translatable attribute to
 * always be translatable, which isn't true of this table's `value` column).
 */
class SiteSetting extends Model
{
    use BelongsToSite;
    use HasFactory;
    use LogsActivity;

    public const TYPE_TRANSLATABLE = 'translatable';

    public const TYPE_TEXT = 'text';

    public const TYPE_JSON = 'json';

    protected $table = 'website_site_settings';

    protected $fillable = [
        'site_id',
        'key',
        'value',
        'type',
    ];

    protected $casts = [
        'value' => 'array',
    ];

    protected static function newFactory(): SiteSettingFactory
    {
        return SiteSettingFactory::new();
    }

    protected static function booted(): void
    {
        static::saved(fn (self $setting) => self::forgetCacheFor($setting->site_id));
        static::deleted(fn (self $setting) => self::forgetCacheFor($setting->site_id));
    }

    private static function cacheKeyFor(int $siteId): string
    {
        return "website.site-settings.{$siteId}";
    }

    private static function forgetCacheFor(int $siteId): void
    {
        Cache::forget(self::cacheKeyFor($siteId));
    }

    /**
     * Cached-per-site read for the public API. Site scoping still happens
     * through BelongsToSite's global scope on the underlying query — the
     * site id is only read here to build a cache key that never mixes two
     * sites' settings under the same key.
     *
     * CLA-522 pattern, applied here for the same reason: this app's real
     * cache store is 'database' (config/cache.php's serializable_classes
     * => false, a Laravel 13 default) and unserialize()s with
     * allowed_classes => false, which can never reconstruct an Eloquent
     * Collection — Cache::remember()-ing one directly comes back as
     * __PHP_Incomplete_Class on the very next request. Caching the plain
     * array form and rehydrating to a real Collection on read survives
     * that lockdown; PHPUnit's 'array' cache store never round-trips
     * through real serialization, which is why this was invisible to
     * every test in CLA-469's original closure.
     */
    public static function cachedForCurrentSite(): Collection
    {
        $siteId = app(OrganizationContext::class)->siteId();

        if ($siteId === null) {
            return static::query()->get();
        }

        $rows = Cache::remember(
            self::cacheKeyFor($siteId),
            3600,
            fn () => static::query()->get()->map->getAttributes()->all()
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
     * The type => value-shape map this model's `key`/`type` must obey.
     * Adding a new allowed key is a config change, never a migration.
     */
    public static function allowedKeys(): array
    {
        return config('website.site_settings.allowed_keys', []);
    }

    public static function isAllowedKey(string $key): bool
    {
        return array_key_exists($key, self::allowedKeys());
    }

    public static function typeForKey(string $key): ?string
    {
        return self::allowedKeys()[$key] ?? null;
    }

    /**
     * Resolves `value` for the current app locale when `type` is
     * 'translatable' (falls back to the first available translation rather
     * than null, same tolerance Spatie\Translatable applies), or returns it
     * verbatim for 'text'/'json'.
     */
    public function resolvedValue(): mixed
    {
        if ($this->type !== self::TYPE_TRANSLATABLE) {
            return $this->value;
        }

        $translations = is_array($this->value) ? $this->value : [];

        return $translations[app()->getLocale()]
            ?? $translations[config('app.fallback_locale')]
            ?? Arr::first($translations);
    }
}
