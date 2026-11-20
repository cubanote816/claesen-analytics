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
 * An action declared in a trait belongs to every screen that uses the trait; a trait nobody uses
 * keeps its own file name as scope, so its actions still need a section (CLA-718).
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
                        'source' => (string) file_get_contents($file->getPathname()),
                    ];
                }
            }
        }

        $declaredTrait = [];
        $traitUsers = [];
        $factories = [];
        foreach ($files as $index => $file) {
            $declaredTrait[$index] = preg_match(self::TRAIT, $file['source'], $declared) === 1
                ? self::qualify($declared[1], self::namespaceOf($file['source']), [])
                : null;

            foreach (self::usedTraits($file['source']) as $trait) {
                $traitUsers[$trait][] = $index;
            }

            if (($factory = self::factory($file['source'])) !== null) {
                $factories[$factory['class']] = $factory + ['index' => $index];
            }
        }
        $factoryBody = array_column($factories, 'body', 'index');
        $factoryNames = array_column($factories, 'name', 'class');

        $anchors = [];
        foreach ($files as $index => ['relative' => $relative, 'source' => $source]) {
            $scopes = $declaredTrait[$index] !== null
                ? self::traitScopes($declaredTrait[$index], $files, $declaredTrait, $traitUsers, [])
                : [];
            $scopes = $scopes === [] ? [self::scope($relative)] : $scopes;

            preg_match_all(self::ALIAS, $source, $aliases, PREG_SET_ORDER);
            $aliasOf = array_column($aliases, 1, 2);

            preg_match_all(self::MAKE, $source, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);

            foreach ($matches as $match) {
                [$called, $offset] = $match[1];
                $explicit = $match[3][0] ?? '';

                // The action a factory's make() builds is documented where the factory is called.
                if (isset($factoryBody[$index]) && $offset >= $factoryBody[$index][0] && $offset < $factoryBody[$index][1]) {
                    continue;
                }

                $class = str_ends_with($called, 'Action') ? $called : ($aliasOf[$called] ?? null);
                if ($class === null) {
                    continue;
                }

                $name = $explicit === '' && isset($factoryNames[$called])
                    ? $factoryNames[$called]
                    : self::actionName($class, $explicit);
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
     * @param  list<array{relative: string, source: string}>  $files
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
                : [self::scope($files[$user]['relative'])])];
        }

        return array_values(array_unique($scopes));
    }

    /**
     * An action factory: a plain `*Action` class with a static make(), such as Website's
     * `PublishSiteNowAction::make()` → `Action::make('publish_now')`. The name is the first action
     * that make() itself builds; its call sites take that name. Actions declared elsewhere in the
     * file keep the file's own scope (CLA-718 review).
     *
     * @return array{class: string, name: string, body: array{0: int, 1: int}}|null
     */
    private static function factory(string $source): ?array
    {
        if (preg_match('/^\s*(?:final\s+)?class\s+(\w+Action)\b(?![^{]*\bextends\b)/m', $source, $class) !== 1
            || preg_match('/\bstatic\s+function\s+make\s*\([^{]*\{/', $source, $make, PREG_OFFSET_CAPTURE) !== 1) {
            return null;
        }

        $start = $make[0][1] + strlen($make[0][0]);
        for ($depth = 1, $end = $start; $end < strlen($source) && $depth > 0; $end++) {
            $depth += ['{' => 1, '}' => -1][$source[$end]] ?? 0;
        }

        preg_match_all(self::MAKE, substr($source, $start, $end - $start), $matches, PREG_SET_ORDER);
        foreach ($matches as $match) {
            if (str_ends_with($match[1], 'Action') && ($name = self::actionName($match[1], $match[3] ?? '')) !== null) {
                return ['class' => $class[1], 'name' => $name, 'body' => [$start, $end]];
            }
        }

        return null;
    }

    private static function namespaceOf(string $source): string
    {
        return preg_match('/^\s*namespace\s+([\w\\\\]+)\s*;/m', $source, $namespace) === 1 ? $namespace[1] : '';
    }

    /**
     * Fully qualified name, the way PHP resolves a trait reference: a leading `\` is absolute, an
     * imported name or alias expands to its import, anything else is relative to the namespace.
     *
     * @param  array<string, string>  $imports  alias => qualified name
     */
    private static function qualify(string $name, string $namespace, array $imports): string
    {
        if (str_starts_with($name, '\\')) {
            return ltrim($name, '\\');
        }

        $first = Str::before($name, '\\');
        if (isset($imports[$first])) {
            return $imports[$first] . Str::after($name, $first);
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

        preg_match_all('/^\s*use\s+([\w\\\\]+)(?:\s+as\s+(\w+))?\s*;/m', substr($source, 0, $declaration[0][1]), $imported, PREG_SET_ORDER);
        $imports = [];
        foreach ($imported as $import) {
            $imports[($import[2] ?? '') !== '' ? $import[2] : Str::afterLast($import[1], '\\')] = ltrim($import[1], '\\');
        }

        preg_match_all(self::TRAIT_USE, substr($source, $declaration[0][1]), $uses);

        $traits = [];
        foreach ($uses[1] as $list) {
            foreach (explode(',', $list) as $name) {
                $traits[] = self::qualify(trim($name), self::namespaceOf($source), $imports);
            }
        }

        return array_values(array_unique($traits));
    }

    /** The segment after `Resources/` that names the resource, skipping grouping folders. */
    private static function resourceSegment(array $segments): string
    {
        $last = count($segments) - 1;

        for ($i = 1; $i <= $last; $i++) {
            $name = basename($segments[$i], '.php');

            if (str_ends_with($name, 'Resource') || ($i < $last && in_array($segments[$i + 1], self::RESOURCE_PARTS, true))) {
                return $segments[$i];
            }
        }

        return $segments[1];
    }

    private static function scope(string $relative): string
    {
        $segments = explode('/', $relative);

        if ($segments[0] === 'Resources' && isset($segments[1])) {
            $segment = basename(self::resourceSegment($segments), '.php');
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
