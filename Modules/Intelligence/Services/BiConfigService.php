<?php

namespace Modules\Intelligence\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Modules\Intelligence\Models\BiConfig;

class BiConfigService
{
    private const TTL     = 3600;         // 1 hour — config changes are rare
    private const PREFIX  = 'bi_config';

    /**
     * Read a config value by key, with optional dot-notation for nested access.
     *
     * Examples:
     *   get('variant_margin_targets')                    → ['economy' => 20, ...]
     *   get('variant_margin_targets.economy')            → 20
     *   get('billing_guardian_rules.days_without_invoice') → 30
     */
    public function get(string $key, mixed $default = null): mixed
    {
        // Split on first dot to separate config_key from optional sub-path
        $parts     = explode('.', $key, 2);
        $configKey = $parts[0];
        $subPath   = $parts[1] ?? null;

        $value = Cache::remember(
            self::PREFIX . ':' . $configKey,
            self::TTL,
            fn () => BiConfig::where('config_key', $configKey)->value('config_value')
        );

        if ($value === null) {
            return $default;
        }

        if ($subPath === null) {
            return $value;
        }

        return data_get($value, $subPath, $default);
    }

    /**
     * Update a config value and immediately invalidate its cache entry.
     */
    public function set(string $key, mixed $value, ?int $updatedBy = null): void
    {
        BiConfig::where('config_key', $key)->update([
            'config_value' => $value,
            'updated_by'   => $updatedBy,
        ]);

        Cache::forget(self::PREFIX . ':' . $key);
    }

    /**
     * Return all config entries as a keyed collection (no cache — used by admin pages).
     *
     * @return Collection<string, array>  keyed by config_key
     */
    public function all(): Collection
    {
        return BiConfig::all()->keyBy('config_key');
    }

    /**
     * Invalidate all bi_config cache entries.
     * Call after bulk imports or direct DB edits.
     */
    public function flush(): void
    {
        $keys = BiConfig::pluck('config_key');
        foreach ($keys as $key) {
            Cache::forget(self::PREFIX . ':' . $key);
        }
    }
}
