<?php

declare(strict_types=1);

namespace Tests\Feature\Documentation;

use App\Support\Documentation\ModuleActionInventory;
use App\Support\Documentation\ModuleGuide;
use Filament\Facades\Filament;
use Filament\Panel;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Fixtures\DocumentationPanel\GadgetResource;
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

    /** CLA-718: a guide lists screens that live outside its module (Website lives in app/Filament/Clusters). */
    public function test_guide_front_matter_adds_filament_roots_outside_the_module(): void
    {
        $roots = ModuleGuide::filamentRoots(base_path('tests/Fixtures/documentation/cluster-guide'));

        $this->assertSame([base_path('tests/Fixtures/documentation/cluster')], $roots);
        $this->assertSame([], ModuleGuide::filamentRoots(ModuleGuide::directoryFor('Mailing')));
    }

    public function test_a_filament_root_that_does_not_exist_fails_loudly(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('tests/Fixtures/documentation/does-not-exist');

        ModuleGuide::filamentRoots(base_path('tests/Fixtures/documentation/missing-root-guide'));
    }

    public function test_dutch_mailing_guide_names_the_real_send_button(): void
    {
        $html = ModuleGuide::render(ModuleGuide::directoryFor('Mailing'), 'nl')->html;

        $this->assertStringContainsString('Nu verzenden', $html);
        $this->assertStringNotContainsString('Nu versturen', $html);
    }

    /** CLA-728: Core's guide is pinned like Mailing's, so a silent inventory change cannot drop a section. */
    public function test_core_inventory_matches_its_screens(): void
    {
        $expected = [];
        foreach (['permission', 'role', 'user'] as $scope) {
            foreach (['create', 'delete', 'delete_bulk', 'edit'] as $name) {
                $expected[] = "action-{$scope}-{$name}";
            }
        }
        foreach (['changeRoles', 'resendActivationEmail', 'revokeSessions'] as $name) {
            $expected[] = "action-user-{$name}";
        }
        sort($expected, SORT_STRING);

        $this->assertSame($expected, array_keys(ModuleActionInventory::anchors(base_path('Modules/Core'))));
    }

    /**
     * CLA-728 verification: hidden actions in an Actions row, a page's own infolist and relation
     * managers registered with RelationManager::make() all count, and the panel state is restored.
     */
    public function test_inventory_reads_hidden_row_actions_page_infolists_and_configured_relation_managers(): void
    {
        Filament::setCurrentPanel(null);
        $panel = Panel::make()->id('documentation-fixture')->resources([GadgetResource::class]);

        $anchors = ModuleActionInventory::anchors(base_path('tests/Fixtures/DocumentationPanel'), [], [$panel]);

        $this->assertSame([
            'action-gadget-audit',
            'action-gadget-hidden_until_ready',
            'action-gadget-roles-inspect_extra',
            'action-gadget-shown',
        ], array_keys($anchors));
        $this->assertNull(Filament::getCurrentPanel());
    }

    /** CLA-728: actions in a custom page's form and footer are part of the screen too. */
    public function test_custom_page_form_actions_are_inventoried(): void
    {
        $anchors = array_keys(ModuleActionInventory::anchors(base_path('Modules/Intelligence')));

        $this->assertContains('action-bi-config-page-save', $anchors);
        $this->assertContains('action-offer-simulator-simulate', $anchors);
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
}
