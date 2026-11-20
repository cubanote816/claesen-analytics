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
        $required = ModuleActionInventory::anchors(base_path("Modules/{$module}"), ModuleGuide::filamentRoots($directory));

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

    /** CLA-718: grouped resources (FieldOps Catalogs/*) and actions declared in traits (also through another trait). */
    public function test_grouped_resources_and_trait_actions_take_the_scope_of_their_screen(): void
    {
        $this->assertSame([
            'action-gadget-report-archive' => ['Concerns/HasArchiveAction.php'],
            'action-gadget-type-archive' => ['Concerns/HasArchiveAction.php'],
            'action-gadget-type-create' => ['Resources/Catalogs/GadgetTypes/Pages/ListGadgetTypes.php'],
            'action-gadget-type-edit' => ['Resources/Catalogs/GadgetTypeResource.php'],
            'action-has-orphan-action-orphan' => ['Concerns/HasOrphanAction.php'],
            'action-settings-page-archive' => ['Concerns/HasArchiveAction.php'],
        ], self::sortPaths(ModuleActionInventory::anchors(base_path('tests/Fixtures/documentation/nested-module'))));
    }

    /** CLA-718 review: traits resolve by qualified name (grouped imports with aliases, same short name). */
    public function test_traits_resolve_by_qualified_name(): void
    {
        $this->assertSame([
            'action-archive-page-archive' => ['Concerns/Archive/HasButtons.php'],
            'action-export-page-export' => ['Concerns/Export/HasButtons.php'],
        ], self::sortPaths(ModuleActionInventory::anchors(base_path('tests/Fixtures/documentation/qualified-module'))));
    }

    /** CLA-718: a guide lists screens that live outside its module (Website lives in app/Filament/Clusters). */
    public function test_guide_front_matter_adds_filament_roots_outside_the_module(): void
    {
        $roots = ModuleGuide::filamentRoots(base_path('tests/Fixtures/documentation/cluster-guide'));

        $this->assertSame([base_path('tests/Fixtures/documentation/cluster')], $roots);
        $this->assertSame(
            ['action-sign-publish' => ['Resources/SignResource.php']],
            ModuleActionInventory::anchors(base_path('tests/Fixtures/documentation/no-such-module'), $roots),
        );
        $this->assertSame([], ModuleGuide::filamentRoots(ModuleGuide::directoryFor('Mailing')));
    }

    public function test_a_filament_root_that_does_not_exist_fails_loudly(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('tests/Fixtures/documentation/does-not-exist');

        ModuleGuide::filamentRoots(base_path('tests/Fixtures/documentation/missing-root-guide'));
    }

    public function test_named_arguments_and_aliased_imports_are_inventoried(): void
    {
        $dir = sys_get_temp_dir() . '/guide-' . bin2hex(random_bytes(8));
        mkdir($dir . '/Filament/Pages', 0777, true);
        $file = $dir . '/Filament/Pages/Report.php';

        try {
            foreach ([
                "Action::make(name: 'publish');",
                "use Filament\\Actions\\Action as Button;\nButton::make('publish');",
                "use Filament\\Actions\\CreateAction as NewThing;\nNewThing::make();",
            ] as $index => $source) {
                file_put_contents($file, "<?php\n" . $source);
                $this->assertSame(
                    [$index === 2 ? 'action-report-create' : 'action-report-publish'],
                    array_keys(ModuleActionInventory::anchors($dir)),
                    $source,
                );
            }
        } finally {
            @unlink($file);
            @rmdir($dir . '/Filament/Pages');
            @rmdir($dir . '/Filament');
            @rmdir($dir);
        }
    }

    public function test_non_action_factories_are_ignored(): void
    {
        $dir = sys_get_temp_dir() . '/guide-' . bin2hex(random_bytes(8));
        mkdir($dir . '/Filament/Pages', 0777, true);
        $file = $dir . '/Filament/Pages/Report.php';

        try {
            file_put_contents($file, "<?php\nTextColumn::make('name'); SchemaActions::make([]); BulkActionGroup::make([]);");
            $this->assertSame([], ModuleActionInventory::anchors($dir));
        } finally {
            @unlink($file);
            @rmdir($dir . '/Filament/Pages');
            @rmdir($dir . '/Filament');
            @rmdir($dir);
        }
    }

    public function test_dutch_mailing_guide_names_the_real_send_button(): void
    {
        $html = ModuleGuide::render(ModuleGuide::directoryFor('Mailing'), 'nl')->html;

        $this->assertStringContainsString('Nu verzenden', $html);
        $this->assertStringNotContainsString('Nu versturen', $html);
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
