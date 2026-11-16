<?php

declare(strict_types=1);

namespace Tests\Feature\Documentation;

use App\Filament\Documentation\ModuleDocumentationPage;
use Tests\TestCase;

/**
 * CLA-693 hotfix: production installs with `composer install --no-dev`. The first release
 * parsed guide front matter with symfony/yaml, a dev-only package, and the Documentation
 * navigation label reads that front matter on every panel request: every page returned 500.
 */
class ModuleGuideProductionDependenciesTest extends TestCase
{
    /** @return list<string> PSR-4 prefixes of the packages that `--no-dev` does not install */
    private static function devOnlyNamespaces(): array
    {
        $lock = json_decode((string) file_get_contents(base_path('composer.lock')), true);
        $production = array_column($lock['packages'], 'name');

        // The project's own namespaces (App\, Modules\, ...) are never a dev package, even if
        // some dev package also declares one of them.
        $root = json_decode((string) file_get_contents(base_path('composer.json')), true);
        $own = array_map(
            fn (string $prefix): string => rtrim($prefix, '\\') . '\\',
            array_keys(($root['autoload']['psr-4'] ?? []) + ($root['autoload-dev']['psr-4'] ?? [])),
        );

        $prefixes = [];
        foreach ($lock['packages-dev'] as $package) {
            if (in_array($package['name'], $production, true)) {
                continue;
            }
            foreach (array_keys($package['autoload']['psr-4'] ?? []) as $prefix) {
                $prefix = rtrim($prefix, '\\') . '\\';
                if (! in_array($prefix, $own, true)) {
                    $prefixes[] = $prefix;
                }
            }
        }

        return $prefixes;
    }

    public function test_documentation_code_uses_no_dev_only_package(): void
    {
        $prefixes = self::devOnlyNamespaces();
        $this->assertContains('Symfony\\Component\\Yaml\\', $prefixes, 'sanity: symfony/yaml is dev-only in composer.lock');

        $files = array_merge(
            glob(base_path('app/Support/Documentation/*.php')) ?: [],
            glob(base_path('app/Filament/Documentation/*.php')) ?: [],
        );
        $this->assertNotEmpty($files);

        $violations = [];
        foreach ($files as $file) {
            preg_match_all('/^use\s+([\w\\\\]+)/m', (string) file_get_contents($file), $uses);
            foreach ($uses[1] as $class) {
                foreach ($prefixes as $prefix) {
                    if (str_starts_with($class . '\\', $prefix)) {
                        $violations[] = basename($file) . ': ' . $class;
                    }
                }
            }
        }

        $this->assertSame([], $violations, 'Documentation code must run with composer install --no-dev');
    }

    public function test_navigation_label_never_breaks_the_panel(): void
    {
        $page = new class extends ModuleDocumentationPage
        {
            public static function module(): string
            {
                return 'ModuleWithoutAGuide';
            }

            public static function accessFollows(): string
            {
                return self::class;
            }
        };

        $this->assertSame('ModuleWithoutAGuide', $page::getNavigationLabel());
    }
}
