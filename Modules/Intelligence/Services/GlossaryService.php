<?php

declare(strict_types=1);

namespace Modules\Intelligence\Services;

use Illuminate\Support\Facades\Cache;
use Modules\Intelligence\Models\Glossary;

/**
 * CLA-611 (gap G4): glossary lookup for the translation prompts.
 *
 * The version is derived from the site's rows (count + latest update), so
 * the translation cache (hash(source + locale + glossary_version))
 * invalidates automatically when an editor changes the glossary.
 */
class GlossaryService
{
    /**
     * @return array{version: int, terms: array<string, array{translations: array<string, string>, do_not_translate: bool}>}
     */
    public function forSite(?int $siteId): array
    {
        if ($siteId === null) {
            return ['version' => 0, 'terms' => []];
        }

        return Cache::remember(
            "intelligence.glossary.{$siteId}",
            3600,
            function () use ($siteId): array {
                $rows = Glossary::query()->where('site_id', $siteId)->get();

                $terms = $rows->mapWithKeys(fn (Glossary $row) => [
                    $row->term => [
                        'translations' => is_array($row->translations) ? $row->translations : [],
                        'do_not_translate' => (bool) $row->do_not_translate,
                    ],
                ])->all();

                // Content-derived version: any term/translation change (or
                // added/removed row) changes the version and therefore
                // invalidates the translation cache. MySQL datetime columns
                // carry no fractional seconds, so a timestamp alone cannot
                // distinguish two edits within the same second.
                $fingerprint = $rows
                    ->map(fn (Glossary $row) => [$row->term, $row->translations, $row->do_not_translate])
                    ->toJson();

                return ['version' => crc32($fingerprint), 'terms' => $terms];
            }
        );
    }

    public static function forgetCacheFor(int $siteId): void
    {
        Cache::forget("intelligence.glossary.{$siteId}");
    }
}
