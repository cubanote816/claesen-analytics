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
 * Static scan of `<module>/Filament/**.php`, no Livewire boot: `Action::make('send_now')` is
 * named `send_now`; an unnamed `CreateAction::make()` takes Filament's default name from its
 * class (`create`, `edit`, `delete_bulk`, ...). The anchor is `action-<scope>-<name>`:
 *
 *   Resources/CampaignResource.php, CampaignResource/Pages/*, .../Schemas/*  → campaign
 *   Resources/EmailTemplates/**                                              → email-template
 *   Resources/CampaignResource/RelationManagers/MessagesRelationManager.php → campaign-messages
 *   Pages/MailingDocumentation.php                                           → mailing-documentation
 *
 * so the same button on a list row and on the record page is one section.
 */
final class ModuleActionInventory
{
    private const ACTION = '/\b(\w*Action)::make\(\s*(?:([\'"])([^\'"]+)\2)?/';

    /**
     * @return array<string, list<string>> anchor => files (relative to Filament/) that declare it, sorted by anchor
     */
    public static function anchors(string $moduleDirectory): array
    {
        $root = rtrim($moduleDirectory, '/') . '/Filament';
        if (! is_dir($root)) {
            return [];
        }

        $anchors = [];
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, RecursiveDirectoryIterator::SKIP_DOTS));

        foreach ($files as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $relative = Str::after($file->getPathname(), $root . '/');
            preg_match_all(self::ACTION, (string) file_get_contents($file->getPathname()), $matches, PREG_SET_ORDER);

            foreach ($matches as $match) {
                $name = self::actionName($match[1], $match[3] ?? '');
                if ($name === null) {
                    continue;
                }

                $anchor = 'action-' . self::scope($relative) . '-' . $name;
                $anchors[$anchor][] = $relative;
            }
        }

        ksort($anchors);

        return array_map(fn (array $paths): array => array_values(array_unique($paths)), $anchors);
    }

    private static function actionName(string $class, string $explicit): ?string
    {
        if ($explicit !== '') {
            return $explicit;
        }

        $base = Str::beforeLast($class, 'Action');

        return $base === '' ? null : Str::snake($base);
    }

    private static function scope(string $relative): string
    {
        $segments = explode('/', $relative);

        if ($segments[0] === 'Resources' && isset($segments[1])) {
            $resource = Str::kebab(Str::singular(Str::beforeLast(basename($segments[1], '.php'), 'Resource') ?: basename($segments[1], '.php')));

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
