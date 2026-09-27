<?php

declare(strict_types=1);

namespace Modules\Website\Services;

use Modules\Core\Models\Site;
use Modules\Core\Services\OrganizationContext;

/**
 * CLA-611 gap G9, site-scoped per the approver's amendment (2026-09-27):
 * locale coalescing on the public API is NOT removed globally.
 *
 * PortfolioApiTest::test_show_falls_back_to_nl_when_requested_locale_is_missing
 * explicitly freezes the nl/en fallback for Claesen, whose live Astro
 * frontend may depend on it — a global rewrite would violate the program's
 * zero-regression rule. Strictness therefore OPTS IN per site:
 *
 *  - A site whose `key` is listed in
 *    config('website.public_api.strict_locale_site_keys') gets STRICT
 *    resolution on every public surface (projects, legal documents, media
 *    slots): a field without a translation in the REQUESTED locale resolves
 *    to `null` — never Dutch, never English. An untranslated locale means
 *    "this page is not published in that language" (noindex/draft banner
 *    on the frontend).
 *  - Every other site (Claesen today) keeps the historical tolerant chain
 *    locale → nl → en → first available — byte-identical behaviour, frozen
 *    by the existing baseline tests.
 *
 * The list is config, not a `sites` column: adding a column to the shared
 * `sites` table is a shared-domain migration the program explicitly gates
 * behind a human decision, and per-site API behaviour is policy, not
 * identity — config keeps it reversible without a migration (same pattern
 * as config('organizations.enforce'), ADR D4).
 */
final class PublicLocalePolicy
{
    public const DEFAULT_STRICT_SITE_KEYS = ['electrobertels'];

    /**
     * Whether the request's resolved site serves the public API strictly
     * (no locale coalescing). Falls back to Claesen-tolerant when no site
     * is resolved at all (never the case on the v1/website group, but this
     * service must not assume its caller).
     */
    public static function isStrict(): bool
    {
        $site = app(OrganizationContext::class)->site();

        if ($site === null) {
            return false;
        }

        return in_array(
            $site->key,
            (array) config('website.public_api.strict_locale_site_keys', self::DEFAULT_STRICT_SITE_KEYS),
            true
        );
    }

    /**
     * Resolve a locale-keyed translation map for the public API under the
     * resolved site's policy.
     *
     * Strict mode: ONLY the exact requested locale — missing translation
     * => null (G9: a French visitor must never receive Dutch text).
     * Tolerant mode: the historical Claesen chain — requested locale,
     * then nl, then en, then the first non-empty value available.
     *
     * @param  array<string, string|null>  $translations
     */
    public static function resolve(array $translations, ?string $locale = null): ?string
    {
        $locale ??= app()->getLocale();

        if (self::isStrict()) {
            return self::firstNonEmpty([$translations[$locale] ?? null]);
        }

        return self::firstNonEmpty([
            $translations[$locale] ?? null,
            $translations['nl'] ?? null,
            $translations['en'] ?? null,
        ]) ?? self::firstNonEmpty(array_values($translations));
    }

    /**
     * @param  array<array-key, string|null>  $values
     */
    private static function firstNonEmpty(array $values): ?string
    {
        foreach ($values as $value) {
            if (is_string($value) && $value !== '') {
                return $value;
            }
        }

        return null;
    }
}
