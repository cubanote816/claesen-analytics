<?php

declare(strict_types=1);

namespace App\Support\Documentation;

use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Str;
use League\CommonMark\Extension\Attributes\AttributesExtension;
use RuntimeException;
use Symfony\Component\Yaml\Yaml;

/**
 * In-app user guide of one module (CLA-693).
 *
 * A guide is one Markdown file per locale in `Modules/<Module>/docs/user-guide/<locale>.md`.
 * Locales without a file fall back to English. Every guide starts with YAML front matter
 * (`title`, `navigation_label`, `module`) that a future per-module RAG reads with the sections.
 *
 * UI names are never typed by hand. A placeholder points at the translation key the UI uses,
 * so a renamed button renames itself in the guide too:
 *
 *   [[action:mailing::resource.actions.send_now]]     a button, shown as a button chip
 *   [[label:mailing::resource.fields.subject]]        a field, column, section or tab name
 *   [[nav:navigation.groups.mailing > mailing::resource.campaign.plural_model_label]]
 *                                                     a menu path, segments joined with →
 *
 * Parameters go after `?`, like a query string; each value is translated when it is a key:
 *
 *   [[action:filament-actions::create.single.label?label=mailing::resource.campaign.model_label]]
 *
 * Sections carry stable anchors (`## Send a campaign {#action-campaign-send_now}`) that
 * internal links (`[Send now](#action-campaign-send_now)`), the table of contents and the
 * coverage test (tests/Feature/Documentation) use.
 */
final class ModuleGuide
{
    public const FALLBACK_LOCALE = 'en';

    private const PLACEHOLDER = '/\[\[(action|label|nav):([^\]]+)\]\]/';

    public static function directoryFor(string $module): string
    {
        return base_path("Modules/{$module}/docs/user-guide");
    }

    /** Locale whose file is used: the requested one when it exists, English otherwise. */
    public static function resolveLocale(string $directory, ?string $locale = null): string
    {
        $locale = Str::before(Str::before($locale ?? app()->getLocale(), '_'), '-');

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

    public static function render(string $directory, ?string $locale = null): RenderedGuide
    {
        $locale = self::resolveLocale($directory, $locale);
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
            function (array $match) use ($locale, &$missing): string {
                [$text, $isMissing] = self::resolvePlaceholder(
                    $match[1],
                    html_entity_decode($match[2], ENT_QUOTES | ENT_HTML5),
                    $locale,
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

    /** @return array{0: string, 1: string|null} the text, and the unresolved key when one is missing */
    private static function resolvePlaceholder(string $type, string $reference, string $locale): array
    {
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

        $meta = Yaml::parse($m[1]);

        return [is_array($meta) ? $meta : [], $m[2]];
    }
}
