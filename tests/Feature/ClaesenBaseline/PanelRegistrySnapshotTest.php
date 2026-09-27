<?php

declare(strict_types=1);

namespace Tests\Feature\ClaesenBaseline;

use Filament\Facades\Filament;
use Filament\Pages\Dashboard;
use Filament\Panel;
use ReflectionClass;
use Tests\Feature\ClaesenBaseline\Support\MatchesBaselineSnapshot;
use Tests\TestCase;

/**
 * Freezes what the single Claesen panel registers today: its id and path, its
 * navigation groups, and every discovered resource, page and widget — plus, per
 * resource, whether it appears in the sidebar and whether it exposes a record
 * title (which is what makes it reachable through Filament's global search).
 *
 * The multi-organization plan keeps this panel untouched at path '' and adds a
 * second panel for Electro Bertels. This snapshot is what proves the first half
 * of that promise: the Claesen panel must keep exactly the same surface.
 */
final class PanelRegistrySnapshotTest extends TestCase
{
    use MatchesBaselineSnapshot;

    public function test_the_claesen_panel_registry_matches_the_baseline(): void
    {
        $panel = Filament::getPanel('admin');

        $lines = [
            'panel_id='.$panel->getId(),
            'panel_path='.($panel->getPath() === '' ? '<root>' : $panel->getPath()),
            'panel_is_default='.($panel->isDefault() ? 'yes' : 'no'),
            'spa_mode='.($panel->hasSpaMode() ? 'yes' : 'no'),
            'navigation_groups='.implode(',', array_map(
                static fn ($group): string => is_string($group) ? $group : ($group->getLabel() ?? '-'),
                $panel->getNavigationGroups()
            )),
        ];

        foreach ($this->sorted($panel->getResources()) as $resource) {
            $lines[] = sprintf(
                'resource %s | model=%s | slug=%s | navigation=%s | record_title=%s | globally_searchable=%s',
                $resource,
                $resource::getModel(),
                $resource::getSlug(),
                $resource::shouldRegisterNavigation() ? 'yes' : 'no',
                $resource::getRecordTitleAttribute() ?? '-',
                $this->isGloballySearchable($resource) ? 'yes' : 'no',
            );
        }

        foreach ($this->sorted($panel->getPages()) as $page) {
            $lines[] = 'page '.$page;
        }

        foreach ($this->sorted($panel->getWidgets()) as $widget) {
            $lines[] = 'widget '.$widget;
        }

        $this->assertMatchesBaselineSnapshot('panel-registry', implode("\n", $lines));
    }

    public function test_the_registered_panels_are_exactly_claesen_and_the_bertels_spike(): void
    {
        // F1/P6 spike (CLA-549): declared inversion. A third panel appearing
        // here without a matching phase ticket is the regression this guards.
        $this->assertSame(
            ['admin', 'bertels'],
            array_values(array_map(static fn (Panel $panel): string => $panel->getId(), Filament::getPanels())),
        );
    }

    public function test_the_bertels_panel_registers_only_the_website_cluster_of_its_own_site(): void
    {
        // Declared inversion (CLA-598, supersedes the CLA-549 spike's empty panel):
        // Bertels manages its own Website content — exactly the Website cluster's
        // three resources and its site-settings page — and nothing else. Any other
        // resource appearing here without a phase ticket is the regression this guards.
        $panel = Filament::getPanel('bertels');

        $this->assertSame('bertels', $panel->getId());
        $this->assertSame('bertels', $panel->getPath());
        $this->assertFalse($panel->isDefault());
        $this->assertSame(
            $this->sorted([
                \App\Filament\Clusters\Website\Resources\AnnouncementResource::class,
                \App\Filament\Clusters\Website\Resources\ConsultationRequestResource::class,
                \App\Filament\Clusters\Website\Resources\ProjectResource::class,
            ]),
            $this->sorted($panel->getResources()),
        );
        $this->assertSame(
            $this->sorted([
                Dashboard::class,
                \App\Filament\Clusters\Website\Pages\SiteSettingsPage::class,
                \App\Filament\Clusters\Website\WebsiteCluster::class, // a cluster is itself a page
            ]),
            $this->sorted($panel->getPages()),
        );
        $this->assertSame([\App\Filament\Clusters\Website\WebsiteCluster::class], array_values($panel->getClusters()));
    }

    /**
     * @param  array<int|string, class-string>  $classes
     * @return array<int, class-string>
     */
    private function sorted(array $classes): array
    {
        $classes = array_values($classes);
        sort($classes);

        return $classes;
    }

    /**
     * Read the static opt-out without calling canGloballySearch(), which
     * evaluates canAccess() and therefore needs an authenticated user. Here we
     * only record the declared searchable surface.
     *
     * @param  class-string  $resource
     */
    private function isGloballySearchable(string $resource): bool
    {
        $property = (new ReflectionClass($resource))->getProperty('isGloballySearchable');

        return (bool) $property->getDefaultValue() && $resource::getRecordTitleAttribute() !== null;
    }
}
