<?php

declare(strict_types=1);

namespace Tests\Feature\Documentation;

use App\Support\Documentation\ModuleActionInventory;
use App\Support\Documentation\ModuleGuide;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * CLA-693: the guard that keeps module guides current. CI runs it on every pull request.
 *
 * For every module with a user guide (Modules/<Module>/docs/user-guide), each required locale
 * must exist, resolve every UI name, share the same section anchors, and document every
 * Filament action the module declares. A new or renamed action fails here until its
 * section is written in every language. See docs/ai/module-documentation.md.
 */
class ModuleGuideCoverageTest extends TestCase
{
    /** Locales every module guide must ship (user decision, CLA-693). */
    private const REQUIRED_LOCALES = ['en', 'nl'];

    /** @return iterable<string, array{string}> */
    public static function modulesWithAGuide(): iterable
    {
        foreach (glob(dirname(__DIR__, 3) . '/Modules/*/docs/user-guide', GLOB_ONLYDIR) ?: [] as $directory) {
            $module = basename(dirname($directory, 2));

            yield $module => [$module];
        }
    }

    public function test_at_least_one_module_ships_a_guide(): void
    {
        $this->assertNotEmpty(iterator_to_array(self::modulesWithAGuide()));
    }

    #[DataProvider('modulesWithAGuide')]
    public function test_guide_exists_in_every_required_locale(string $module): void
    {
        $locales = ModuleGuide::locales(ModuleGuide::directoryFor($module));

        $this->assertSame([], array_values(array_diff(self::REQUIRED_LOCALES, $locales)), "{$module}: missing guide locales");
    }

    #[DataProvider('modulesWithAGuide')]
    public function test_every_ui_name_resolves(string $module): void
    {
        $directory = ModuleGuide::directoryFor($module);

        foreach (ModuleGuide::locales($directory) as $locale) {
            $this->assertSame([], ModuleGuide::render($directory, $locale)->missingKeys, "{$module} {$locale}.md: unresolved placeholders");
        }
    }

    #[DataProvider('modulesWithAGuide')]
    public function test_every_locale_has_the_same_sections(string $module): void
    {
        $directory = ModuleGuide::directoryFor($module);
        $reference = ModuleGuide::render($directory, ModuleGuide::FALLBACK_LOCALE)->anchors();

        $this->assertSame(array_values(array_unique($reference)), $reference, "{$module} en.md: duplicate anchors");

        foreach (ModuleGuide::locales($directory) as $locale) {
            $this->assertSame($reference, ModuleGuide::render($directory, $locale)->anchors(), "{$module} {$locale}.md: sections differ from en.md");
        }
    }

    #[DataProvider('modulesWithAGuide')]
    public function test_every_filament_action_is_documented(string $module): void
    {
        $directory = ModuleGuide::directoryFor($module);
        $required = ModuleActionInventory::anchors(base_path("Modules/{$module}"));

        foreach (ModuleGuide::locales($directory) as $locale) {
            $anchors = ModuleGuide::render($directory, $locale)->anchors();
            $undocumented = array_diff_key($required, array_flip($anchors));

            $this->assertSame([], $undocumented, "{$module} {$locale}.md: add a section {#anchor} for each action (anchor => declaring files)");
        }
    }

    public function test_inventory_names_actions_by_scope_and_filament_default_names(): void
    {
        $this->assertSame([
            'action-widget-create' => ['Resources/WidgetResource/Pages/ViewWidget.php'],
            'action-widget-delete_bulk' => ['Resources/WidgetResource.php'],
            'action-widget-parts-view' => ['Resources/WidgetResource/RelationManagers/PartsRelationManager.php'],
            'action-widget-publish' => ['Resources/WidgetResource.php', 'Resources/WidgetResource/Pages/ViewWidget.php'],
            'action-widget-report-export' => ['Pages/WidgetReport.php'],
            'action-widget-view' => ['Resources/WidgetResource.php'],
        ], self::sortPaths(ModuleActionInventory::anchors(base_path('tests/Fixtures/documentation/module'))));
    }

    public function test_mailing_inventory_matches_its_screens(): void
    {
        $this->assertSame([
            'action-campaign-approve',
            'action-campaign-cancel',
            'action-campaign-create',
            'action-campaign-messages-view',
            'action-campaign-preview_audience',
            'action-campaign-send_now',
            'action-campaign-submit',
            'action-campaign-view',
            'action-campaign-view_full_content',
            'action-email-template-create',
            'action-email-template-delete',
            'action-email-template-delete_bulk',
            'action-email-template-edit',
        ], array_keys(ModuleActionInventory::anchors(base_path('Modules/Mailing'))));
    }

    /**
     * @param  array<string, list<string>>  $anchors
     * @return array<string, list<string>>
     */
    private static function sortPaths(array $anchors): array
    {
        foreach ($anchors as &$paths) {
            sort($paths);
        }

        return $anchors;
    }
}
