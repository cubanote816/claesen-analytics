<?php

declare(strict_types=1);

namespace App\Support\Documentation;

use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Str;
use League\CommonMark\Extension\Attributes\AttributesExtension;
use RuntimeException;

/**
 * In-app user guide of one module (CLA-693).
 *
 * A guide is one Markdown file per locale in `Modules/<Module>/docs/user-guide/<locale>.md`.
 * Locales without a file fall back to English. Every guide starts with front matter
 * (`title`, `navigation_label`, `module`) that a future per-module RAG reads with the sections.
 * Front matter is flat `key: value` lines, parsed here on purpose: symfony/yaml is a dev-only
 * package and production installs with `composer install --no-dev` (CLA-693 hotfix).
 *
 * UI names are never typed by hand. A placeholder points at the translation key the UI uses,
 * so a renamed button renames itself in the guide too:
 *
 *   [[action:mailing::resource.actions.send_now]]     a button, shown as a button chip
 *   [[label:mailing::resource.fields.subject]]        a field, column, section or tab name
 *   [[nav:navigation.groups.mailing > mailing::resource.campaign.plural_model_label]]
 *                                                     a menu path, segments joined with →
 *   [[enum:Modules\Mailing\Enums\CampaignStatus::draft]]
 *                                                     an enum option, via its label($locale)
 *
 * Parameters go after `?`, like a query string; each value is translated when it is a key:
 *
 *   [[action:filament-actions::create.single.label?label=mailing::resource.campaign.model_label]]
 *
 * Labels always follow the reader's UI locale, even when the guide text falls back to English,
 * so the names match the screen the reader has open.
 *
 * Sections carry stable anchors (`## Send a campaign {#action-campaign-send_now}`) that
 * internal links (`[Send now](#action-campaign-send_now)`), the table of contents and the
 * coverage test (tests/Feature/Documentation) use.
 */
final class ModuleGuide
{
    public const FALLBACK_LOCALE = 'en';

    private const PLACEHOLDER = '/\[\[(action|label|nav|enum):([^\]]+)\]\]/';

    public static function directoryFor(string $module): string
    {
        return base_path("Modules/{$module}/docs/user-guide");
    }

    /** Locale whose file is used: the requested one when it exists, English otherwise. */
    public static function resolveLocale(string $directory, ?string $locale = null): string
    {
        $locale = self::normalizeLocale($locale ?? app()->getLocale());

        return is_file("{$directory}/{$locale}.md") ? $locale : self::FALLBACK_LOCALE;
    }

    /** @return list<string> locales that have a guide file, sorted */
    public static function locales(string $directory): array
    {
        $locales = array_map(
            fn (string $path): string => pathinfo($path, PATHINFO_FILENAME),
            glob("{$directory}/*.md") ?: [],
        );
        sort($locales);

        return $locales;
    }

    /** @return array<string, mixed> front matter only, without rendering the body */
    public static function meta(string $directory, ?string $locale = null): array
    {
        return self::split(self::read($directory, self::resolveLocale($directory, $locale)))[0];
    }

    /**
     * Filament roots outside the module whose actions the guide documents (CLA-718), from the
     * `filament` front matter of the English guide: repository-relative folders, comma separated,
     * e.g. `filament: app/Filament/Clusters/Website`. A folder that does not exist inside the
     * repository fails loudly, so a moved cluster cannot silently drop out of the coverage test.
     *
     * @return list<string> absolute paths
     */
    public static function filamentRoots(string $directory): array
    {
        $declared = (string) (self::meta($directory, self::FALLBACK_LOCALE)['filament'] ?? '');
        $base = rtrim(base_path(), '/');

        $roots = [];
        foreach (array_filter(array_map('trim', explode(',', $declared))) as $relative) {
            $absolute = realpath($base . '/' . ltrim($relative, '/'));

            if ($absolute === false || ! is_dir($absolute) || ! str_starts_with($absolute . '/', $base . '/')) {
                throw new RuntimeException("Guide {$directory}: filament root {$relative} is not a folder in this repository.");
            }

            $roots[] = $absolute;
        }

        return $roots;
    }

    public static function render(string $directory, ?string $locale = null): RenderedGuide
    {
        $uiLocale = self::normalizeLocale($locale ?? app()->getLocale());
        $locale = self::resolveLocale($directory, $uiLocale);
        [$meta, $body] = self::split(self::read($directory, $locale));

        $html = Str::markdown($body, [
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ], [new AttributesExtension]);

        $missing = [];

        // Placeholders are replaced after Markdown, so labels can never inject markup and
        // a table cell keeps its pipes. The captured text is HTML-escaped by CommonMark.
        $html = (string) preg_replace_callback(
            self::PLACEHOLDER,
            function (array $match) use ($uiLocale, &$missing): string {
                [$text, $isMissing] = self::resolvePlaceholder(
                    $match[1],
                    html_entity_decode($match[2], ENT_QUOTES | ENT_HTML5),
                    $uiLocale,
                );
                if ($isMissing !== null) {
                    $missing[] = $isMissing;

                    return '<span class="doc-ui doc-ui--missing">' . e($isMissing) . '</span>';
                }

                return '<span class="doc-ui doc-ui--' . $match[1] . '">' . e($text) . '</span>';
            },
            $html,
        );

        return new RenderedGuide(
            locale: $locale,
            title: (string) ($meta['title'] ?? ''),
            meta: $meta,
            html: $html,
            toc: self::tableOfContents($html),
            missingKeys: array_values(array_unique($missing)),
        );
    }

    private static function normalizeLocale(string $locale): string
    {
        return Str::before(Str::before($locale, '_'), '-');
    }

    /** @return array{0: string, 1: string|null} the text, and the unresolved reference when one is missing */
    private static function resolvePlaceholder(string $type, string $reference, string $locale): array
    {
        if ($type === 'enum') {
            return self::resolveEnum(trim($reference), $locale);
        }

        $segments = $type === 'nav'
            ? array_map('trim', explode('>', $reference))
            : [trim($reference)];

        $texts = [];
        foreach ($segments as $segment) {
            $key = Str::before($segment, '?');
            parse_str(str_contains($segment, '?') ? Str::after($segment, '?') : '', $params);

            $replace = [];
            foreach ($params as $name => $value) {
                $value = (string) $value;
                $replace[$name] = Lang::has($value, $locale) ? __($value, [], $locale) : $value;
            }

            if (! Lang::has($key, $locale)) {
                return ['', $key];
            }

            $texts[] = __($key, $replace, $locale);
        }

        return [implode(' → ', $texts), null];
    }

    /** @return array{0: string, 1: string|null} */
    private static function resolveEnum(string $reference, string $locale): array
    {
        [$class, $value] = array_pad(explode('::', $reference, 2), 2, '');

        if (! enum_exists($class) || ! is_subclass_of($class, \BackedEnum::class)) {
            return ['', $reference];
        }

        try {
            $case = $class::tryFrom($value);
        } catch (\TypeError) {
            $case = null;
        }
        if ($case === null || ! method_exists($case, 'label')) {
            return ['', $reference];
        }

        return [(string) $case->label($locale), null];
    }

    /** @return list<array{id: string, text: string, level: int}> */
    private static function tableOfContents(string $html): array
    {
        preg_match_all('/<h([23])\b[^>]*\bid="([^"]+)"[^>]*>(.*?)<\/h\1>/s', $html, $matches, PREG_SET_ORDER);

        return array_map(fn (array $m): array => [
            'id' => html_entity_decode($m[2], ENT_QUOTES | ENT_HTML5),
            'text' => trim(html_entity_decode(strip_tags($m[3]), ENT_QUOTES | ENT_HTML5)),
            'level' => (int) $m[1],
        ], $matches);
    }

    private static function read(string $directory, string $locale): string
    {
        $path = "{$directory}/{$locale}.md";
        if (! is_file($path)) {
            throw new RuntimeException("No user guide at {$path}.");
        }

        return (string) file_get_contents($path);
    }

    /** @return array{0: array<string, mixed>, 1: string} */
    private static function split(string $source): array
    {
        if (! preg_match('/\A---\R(.*?)\R---\R?(.*)\z/s', $source, $m)) {
            return [[], $source];
        }

        $meta = [];
        foreach (preg_split('/\R/', $m[1]) ?: [] as $line) {
            if (preg_match('/^([A-Za-z0-9_]+)\s*:\s*(.*?)\s*$/', $line, $pair)) {
                $meta[$pair[1]] = self::unquote($pair[2]);
            }
        }

        return [$meta, $m[2]];
    }

    private static function unquote(string $value): string
    {
        if (strlen($value) >= 2 && ($value[0] === '"' || $value[0] === "'") && str_ends_with($value, $value[0])) {
            return substr($value, 1, -1);
        }

        return $value;
    }
}
