<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * CLA-700: the admin panel offers nl, en, fr and de, so every static lang
 * file must exist in all four locales with the same keys. A translation must
 * also keep the `:placeholders` and the plural segments of the English
 * source, otherwise `trans()` / `trans_choice()` render broken text.
 *
 * Checked through the translator (`__()`), the same path the panel uses.
 */
class LangFileParityTest extends TestCase
{
    private const LOCALES = ['en', 'nl', 'fr', 'de'];

    /**
     * One case per lang file group: [namespace or null, group, relative directory].
     *
     * @return array<string, array{0: ?string, 1: string, 2: string}>
     */
    public static function langGroupProvider(): array
    {
        $root = dirname(__DIR__, 2);
        $directories = ['lang' => null];

        foreach (glob($root.'/Modules/*/lang', GLOB_ONLYDIR) ?: [] as $moduleLang) {
            $module = basename(dirname($moduleLang));
            $directories['Modules/'.$module.'/lang'] = strtolower($module);
        }

        $cases = [];

        foreach ($directories as $directory => $namespace) {
            $groups = [];

            foreach (self::LOCALES as $locale) {
                $localeDir = $root.'/'.$directory.'/'.$locale;

                if (! is_dir($localeDir)) {
                    continue;
                }

                $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($localeDir, \FilesystemIterator::SKIP_DOTS));

                foreach ($files as $file) {
                    if ($file->getExtension() === 'php') {
                        $relative = substr($file->getPathname(), strlen($localeDir) + 1, -4);
                        $groups[str_replace(DIRECTORY_SEPARATOR, '/', $relative)] = true;
                    }
                }
            }

            foreach (array_keys($groups) as $group) {
                $cases[$directory.'/*/'.$group.'.php'] = [$namespace, $group, $directory];
            }
        }

        ksort($cases);

        return $cases;
    }

    #[Test]
    #[DataProvider('langGroupProvider')]
    public function every_locale_has_the_same_keys(?string $namespace, string $group, string $directory): void
    {
        $reference = $this->referenceKeys($namespace, $group);
        $problems = [];

        foreach (self::LOCALES as $locale) {
            $keys = array_keys($this->flatten($this->load($namespace, $group, $locale)));
            $missing = array_values(array_diff($reference, $keys));
            $extra = array_values(array_diff($keys, $reference));

            if ($missing !== []) {
                $problems[] = sprintf('%s is missing %d key(s): %s', $locale, count($missing), $this->sample($missing));
            }

            if ($extra !== []) {
                $problems[] = sprintf('%s has %d key(s) no other locale defines: %s', $locale, count($extra), $this->sample($extra));
            }
        }

        $this->assertSame([], $problems, "{$directory}/*/{$group}.php is out of sync:\n".implode("\n", $problems));
    }

    #[Test]
    #[DataProvider('langGroupProvider')]
    public function every_translation_keeps_the_english_placeholders_and_plural_segments(?string $namespace, string $group, string $directory): void
    {
        $english = $this->flatten($this->load($namespace, $group, 'en'));
        $problems = [];

        foreach (['nl', 'fr', 'de'] as $locale) {
            foreach ($this->flatten($this->load($namespace, $group, $locale)) as $key => $value) {
                if (! is_string($value) || ! is_string($english[$key] ?? null)) {
                    continue;
                }

                $expected = $this->placeholders($english[$key]);
                $actual = $this->placeholders($value);

                if ($expected !== $actual) {
                    $problems[] = sprintf('%s.%s placeholders [%s], en has [%s]', $locale, $key, implode(', ', $actual), implode(', ', $expected));
                }

                if (substr_count($english[$key], '|') !== substr_count($value, '|')) {
                    $problems[] = sprintf('%s.%s has %d plural segment(s), en has %d', $locale, $key, substr_count($value, '|') + 1, substr_count($english[$key], '|') + 1);
                }
            }
        }

        $this->assertSame([], $problems, "{$directory}/*/{$group}.php breaks placeholders:\n".implode("\n", $problems));
    }

    /**
     * Union of the en and nl keys: the two locales the panel was built in.
     *
     * @return list<string>
     */
    private function referenceKeys(?string $namespace, string $group): array
    {
        $keys = [];

        foreach (['en', 'nl'] as $locale) {
            $keys = array_merge($keys, array_keys($this->flatten($this->load($namespace, $group, $locale))));
        }

        return array_values(array_unique($keys));
    }

    /**
     * @return array<string, mixed>
     */
    private function load(?string $namespace, string $group, string $locale): array
    {
        $loaded = $this->app['translator']->getLoader()->load($locale, $group, $namespace ?? '*');

        return is_array($loaded) ? $loaded : [];
    }

    /**
     * @param  array<string|int, mixed>  $values
     * @return array<string, mixed>
     */
    private function flatten(array $values, string $prefix = ''): array
    {
        $flat = [];

        foreach ($values as $key => $value) {
            $path = $prefix === '' ? (string) $key : $prefix.'.'.$key;

            if (is_array($value) && $value !== []) {
                $flat += $this->flatten($value, $path);
            } else {
                $flat[$path] = $value;
            }
        }

        return $flat;
    }

    /**
     * Laravel `:name` placeholders, sorted and de-duplicated. A colon after a
     * word character or another colon (times, URLs) is not a placeholder.
     *
     * @return list<string>
     */
    private function placeholders(string $value): array
    {
        preg_match_all('/(?<![\w:]):([A-Za-z_][A-Za-z0-9_]*)/', $value, $matches);

        $names = array_values(array_unique($matches[1]));
        sort($names);

        return $names;
    }

    /**
     * @param  list<string>  $keys
     */
    private function sample(array $keys): string
    {
        $shown = array_slice($keys, 0, 8);

        return implode(', ', $shown).(count($keys) > count($shown) ? ', …' : '');
    }
}
