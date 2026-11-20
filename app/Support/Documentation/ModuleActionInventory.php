<?php

declare(strict_types=1);

namespace App\Support\Documentation;

use Illuminate\Support\Str;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Every Filament action a module declares, as the guide anchor that must document it (CLA-693).
 *
 * The guide coverage test (tests/Feature/Documentation/ModuleGuideCoverageTest) fails when an
 * action has no section, so a new button cannot ship without its documentation.
 *
 * Static scan of `<module>/Filament/**.php`, no Livewire boot: `Action::make('send_now')` (also
 * `make(name: 'send_now')` or through a `use ... as Alias` import) is named `send_now`; an unnamed `CreateAction::make()` takes Filament's default name from its
 * class (`create`, `edit`, `delete_bulk`, ...). The anchor is `action-<scope>-<name>`:
 *
 *   Resources/CampaignResource.php, CampaignResource/Pages/*, .../Schemas/*  → campaign
 *   Resources/EmailTemplates/**                                              → email-template
 *   Resources/CampaignResource/RelationManagers/MessagesRelationManager.php → campaign-messages
 *   Pages/MailingDocumentation.php                                           → mailing-documentation
 *   Resources/Catalogs/AccessTypeResource.php, Catalogs/AccessTypes/Pages/*  → access-type (CLA-718)
 *
 * so the same button on a list row and on the record page is one section. Grouping folders such
 * as FieldOps' `Resources/Catalogs` are skipped: the resource is the `*Resource` file or folder,
 * or the folder that holds `Pages`, `Schemas`, `Tables`, ... (CLA-718).
 *
 * An action declared in a trait belongs to every screen that uses the trait, also through another
 * trait; traits are matched by qualified name (namespace, imports, aliases, grouped imports). A
 * trait nobody uses keeps its own file name as scope, so its actions still need a section (CLA-718).
 *
 * Known limit: an action factory such as Website's `PublishSiteNowAction::make()` is named after
 * its class at the call site (`publish_site_now`), and the action it builds inside is listed under
 * the factory file. A static scan cannot follow what make() returns.
 *
 * Screens that live outside the module (Website lives in app/Filament/Clusters/Website) are
 * scanned through extra roots, which a guide lists in its `filament` front matter
 * (see ModuleGuide::filamentRoots()).
 */
final class ModuleActionInventory
{
    private const MAKE = '/\b(\w+)::make\(\s*(?:name\s*:\s*)?(?:([\'"])([^\'"]+)\2)?/';

    /** `use Filament\Actions\Action as Button;` makes `Button::make()` an action too. */
    private const ALIAS = '/^\s*use\s+[\w\\\\]*?\\\\?(\w*Action)\s+as\s+(\w+)\s*;/m';

    private const TRAIT = '/^\s*trait\s+(\w+)/m';

    private const DECLARATION = '/^\s*(?:(?:final|abstract|readonly)\s+)*(?:class|trait|enum)\s+\w+/m';

    /** `use A, B;` inside a class body; `function () use ($x)` never matches (no word after `use`). */
    private const TRAIT_USE = '/\buse\s+([\w\\\\]+(?:\s*,\s*[\w\\\\]+)*)\s*[;{]/';

    /** Folders Filament puts inside a resource: the folder that holds them is the resource. */
    private const RESOURCE_PARTS = ['Pages', 'Schemas', 'Tables', 'RelationManagers', 'Widgets', 'Concerns', 'Actions', 'Forms', 'Infolists'];

    /**
     * @param  list<string>  $extraRoots  absolute Filament roots outside the module (ModuleGuide::filamentRoots())
     * @return array<string, list<string>> anchor => files (relative to their root) that declare it, sorted by anchor
     */
    public static function anchors(string $moduleDirectory, array $extraRoots = []): array
    {
        $roots = array_values(array_filter([rtrim($moduleDirectory, '/') . '/Filament', ...$extraRoots], 'is_dir'));

        $files = [];
        foreach ($roots as $root) {
            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, RecursiveDirectoryIterator::SKIP_DOTS));

            foreach ($iterator as $file) {
                if ($file->getExtension() === 'php') {
                    $files[] = [
                        'relative' => Str::after($file->getPathname(), rtrim($root, '/') . '/'),
                        'source' => self::withoutComments((string) file_get_contents($file->getPathname())),
                        'root' => $root,
                    ];
                }
            }
        }

        $resourceFiles = [];
        foreach ($files as $file) {
            if (str_ends_with($file['relative'], 'Resource.php')) {
                $resourceFiles[$file['root']][] = $file['relative'];
            }
        }
        foreach ($files as &$file) {
            $file['scope'] = self::scope($file['relative'], $resourceFiles[$file['root']] ?? []);
        }
        unset($file);

        $declaredTrait = [];
        $traitUsers = [];
        foreach ($files as $index => $file) {
            $declaredTrait[$index] = preg_match(self::TRAIT, $file['source'], $declared) === 1
                ? strtolower(self::qualify($declared[1], self::namespaceOf($file['source']), []))
                : null;

            foreach (self::usedTraits($file['source']) as $trait) {
                $traitUsers[$trait][] = $index;
            }
        }

        $anchors = [];
        foreach ($files as $index => ['relative' => $relative, 'source' => $source, 'scope' => $scope]) {
            $scopes = $declaredTrait[$index] !== null
                ? self::traitScopes($declaredTrait[$index], $files, $declaredTrait, $traitUsers, [])
                : [];
            $scopes = $scopes === [] ? [$scope] : $scopes;

            preg_match_all(self::ALIAS, $source, $aliases, PREG_SET_ORDER);
            $aliasOf = array_column($aliases, 1, 2);

            preg_match_all(self::MAKE, $source, $matches, PREG_SET_ORDER);

            foreach ($matches as $match) {
                $class = str_ends_with($match[1], 'Action') ? $match[1] : ($aliasOf[$match[1]] ?? null);
                if ($class === null) {
                    continue;
                }

                $name = self::actionName($class, $match[3] ?? '');
                if ($name === null) {
                    continue;
                }

                foreach ($scopes as $scope) {
                    $anchors['action-' . $scope . '-' . $name][] = $relative;
                }
            }
        }

        ksort($anchors);

        return array_map(fn (array $paths): array => array_values(array_unique($paths)), $anchors);
    }

    /**
     * Scopes of the screens that use a trait, following traits that use traits (Website's settings
     * pages use BuildsSiteSettingsSection, which uses ShowsSitePublicationState).
     *
     * @param  list<array{relative: string, source: string, root: string, scope: string}>  $files
     * @param  array<int, ?string>  $declaredTrait
     * @param  array<string, list<int>>  $traitUsers
     * @param  list<string>  $visited
     * @return list<string> empty when no screen uses the trait
     */
    private static function traitScopes(string $trait, array $files, array $declaredTrait, array $traitUsers, array $visited): array
    {
        if (in_array($trait, $visited, true)) {
            return [];
        }

        $scopes = [];
        foreach ($traitUsers[$trait] ?? [] as $user) {
            $scopes = [...$scopes, ...($declaredTrait[$user] !== null
                ? self::traitScopes($declaredTrait[$user], $files, $declaredTrait, $traitUsers, [...$visited, $trait])
                : [$files[$user]['scope']])];
        }

        return array_values(array_unique($scopes));
    }

    /**
     * Comments are not code: a commented-out trait use or action is not on the screen, and a comment
     * inside an import must not hide it. Line breaks are kept, so line-anchored patterns still match.
     */
    private static function withoutComments(string $source): string
    {
        if (! str_contains($source, '<?php')) {
            return $source;
        }

        $code = '';
        foreach (token_get_all($source) as $token) {
            $code .= ! is_array($token) ? $token : (in_array($token[0], [T_COMMENT, T_DOC_COMMENT], true)
                ? str_repeat("\n", substr_count($token[1], "\n")) . ' '
                : $token[1]);
        }

        return $code;
    }

    private static function namespaceOf(string $source): string
    {
        return preg_match('/^\s*namespace\s+([\w\\\\]+)\s*;/m', $source, $namespace) === 1 ? $namespace[1] : '';
    }

    /**
     * Fully qualified name, the way PHP resolves a trait reference: a leading `\` is absolute, an
     * imported name or alias expands to its import, anything else is relative to the namespace.
     * Class names are case-insensitive in PHP, so callers compare the result lowercased.
     *
     * @param  array<string, string>  $imports  lowercased alias => qualified name
     */
    private static function qualify(string $name, string $namespace, array $imports): string
    {
        if (str_starts_with($name, '\\')) {
            return ltrim($name, '\\');
        }

        $first = Str::before($name, '\\');
        if (isset($imports[strtolower($first)])) {
            return $imports[strtolower($first)] . Str::after($name, $first);
        }

        return ltrim($namespace . '\\' . $name, '\\');
    }

    private static function actionName(string $class, string $explicit): ?string
    {
        if ($explicit !== '') {
            return $explicit;
        }

        $base = Str::beforeLast($class, 'Action');

        return $base === '' ? null : Str::snake($base);
    }

    /** @return list<string> qualified names of the traits a class, trait or enum body uses */
    private static function usedTraits(string $source): array
    {
        if (preg_match(self::DECLARATION, $source, $declaration, PREG_OFFSET_CAPTURE) !== 1) {
            return [];
        }

        // Top-level imports: `use A\B;`, `use A\B as C;`, `use A\B, D\E;` and `use A\{B, C\D as E};`.
        preg_match_all('/^\s*use\s+(?!function\b|const\b)([^;]+);/m', substr($source, 0, $declaration[0][1]), $statements);
        $imports = [];
        foreach ($statements[1] as $statement) {
            $prefix = '';
            if (preg_match('/^([\w\\\\]*)\\\\\s*\{(.*)\}\s*$/s', trim($statement), $group) === 1) {
                [$prefix, $statement] = [$group[1] . '\\', $group[2]];
            }

            foreach (array_filter(array_map('trim', explode(',', $statement))) as $clause) {
                if (preg_match('/^([\w\\\\]+)(?:\s+as\s+(\w+))?$/', $clause, $import) === 1) {
                    $qualified = ltrim($prefix . $import[1], '\\');
                    $imports[strtolower(($import[2] ?? '') !== '' ? $import[2] : Str::afterLast($qualified, '\\'))] = $qualified;
                }
            }
        }

        preg_match_all(self::TRAIT_USE, substr($source, $declaration[0][1]), $uses);

        $traits = [];
        foreach ($uses[1] as $list) {
            foreach (explode(',', $list) as $name) {
                $traits[] = strtolower(self::qualify(trim($name), self::namespaceOf($source), $imports));
            }
        }

        return array_values(array_unique($traits));
    }

    /**
     * The segment after `Resources/` that names the resource, skipping grouping folders. The deepest
     * folder confirmed by a `*Resource.php` (its own name, a sibling `<Folder>Resource.php` or one
     * inside it) wins, so a resource folder named like a Filament part (`Catalogs/Forms`) is still
     * the resource; otherwise the folder that holds `Pages`, `Schemas`, ... is.
     *
     * @param  list<string>  $segments
     * @param  list<string>  $resourceFiles  `*Resource.php` paths in the same root
     */
    private static function resourceSegment(array $segments, array $resourceFiles): string
    {
        $last = count($segments) - 1;
        if (str_ends_with($segments[$last], 'Resource.php')) {
            return $segments[$last];
        }

        for ($i = $last - 1; $i >= 1; $i--) {
            $parent = implode('/', array_slice($segments, 0, $i));
            $folder = $parent . '/' . $segments[$i];

            if (str_ends_with($segments[$i], 'Resource')
                || array_intersect([$folder . 'Resource.php', $parent . '/' . Str::singular($segments[$i]) . 'Resource.php'], $resourceFiles) !== []
                || array_filter($resourceFiles, fn (string $path): bool => dirname($path) === $folder) !== []) {
                return $segments[$i];
            }
        }

        for ($i = 1; $i <= $last; $i++) {
            $name = basename($segments[$i], '.php');

            if (str_ends_with($name, 'Resource') || ($i < $last && in_array($segments[$i + 1], self::RESOURCE_PARTS, true))) {
                return $segments[$i];
            }
        }

        return $segments[1];
    }

    /** @param  list<string>  $resourceFiles  `*Resource.php` paths in the same root */
    private static function scope(string $relative, array $resourceFiles): string
    {
        $segments = explode('/', $relative);

        if ($segments[0] === 'Resources' && isset($segments[1])) {
            $segment = basename(self::resourceSegment($segments, $resourceFiles), '.php');
            $resource = Str::kebab(Str::singular(Str::beforeLast($segment, 'Resource') ?: $segment));

            $index = array_search('RelationManagers', $segments, true);
            if ($index !== false && isset($segments[$index + 1])) {
                $relation = Str::beforeLast(basename($segments[$index + 1], '.php'), 'RelationManager');

                return $resource . '-' . Str::kebab($relation);
            }

            return $resource;
        }

        return Str::kebab(basename(end($segments), '.php'));
    }
}
