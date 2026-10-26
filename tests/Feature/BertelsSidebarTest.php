<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Filament\Clusters\Website\WebsiteCluster;
use App\Filament\Clusters\Website\WebsiteNavigationGroups;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Models\Organization;
use Modules\Core\Models\Site;
use Modules\Core\Models\User;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * The Electro Bertels sidebar (design source of truth: odd/tasks/bertels-sidebar.reference.html).
 *
 * Real HTTP GETs, not Livewire::test: the sidebar is panel layout, which only a full request
 * renders. Each panel gets its own test method because Filament's NavigationManager is a scoped
 * binding the test client reuses across ->get() calls (see BertelsPanelAccessTest).
 */
final class BertelsSidebarTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();

        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        // The Website cluster only shows once the panel's own site row exists (CLA-598), so the
        // bertels sidebar has nothing to group without it.
        $organization = Organization::factory()->create(['slug' => 'electro-bertels']);
        Site::factory()->create(['organization_id' => $organization->id, 'key' => 'electro-bertels']);
    }

    private function superAdmin(): User
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole('super_admin');

        return $user;
    }

    public function test_the_bertels_sidebar_renders_the_four_groups_in_the_reference_order(): void
    {
        $response = $this->actingAs($this->superAdmin())
            ->get('/bertels/website/settings-general')
            ->assertSuccessful();

        $groupMarkers = array_map(
            fn (string $group): string => 'data-group-label="' . e(WebsiteNavigationGroups::label($group)) . '"',
            WebsiteNavigationGroups::all(),
        );

        $this->assertCount(4, $groupMarkers);

        // Top-level items first (reference: Dashboard, KNX, Switch to Claesen), then the groups.
        $response->assertSeeInOrder([
            'fi-sidebar-nav',
            __('filament-panels::pages/dashboard.title'),
            __('navigation.knx'),
            __('navigation.switch_to_claesen'),
            ...$groupMarkers,
        ], escape: false);

        // Exactly one active item (General): the "Website" root is only active on its own route.
        $this->assertSame(1, substr_count($response->getContent(), 'fi-sidebar-item fi-active'));

        // The groups are Filament's collapsible navigation groups, with their collapse button.
        $response->assertSee('fi-sidebar-group-collapse-btn', escape: false);

        // No leftover "Content & Website" parent group or nested sub-group.
        $response->assertDontSee('data-group-label="' . e(__('navigation.groups.content_website')) . '"', escape: false);
        $response->assertDontSee('fi-sidebar-sub-group-items', escape: false);
    }

    public function test_the_bertels_sidebar_style_hook_and_width_are_present(): void
    {
        $this->actingAs($this->superAdmin())
            ->get('/bertels')
            ->assertSuccessful()
            ->assertSee('<style id="bertels-sidebar-theme">', escape: false)
            ->assertSee('--sidebar-width: 11.25rem', escape: false);
    }

    public function test_the_claesen_admin_sidebar_does_not_get_the_bertels_style_or_flat_items(): void
    {
        $response = $this->actingAs($this->superAdmin())
            ->get('/')
            ->assertSuccessful()
            ->assertDontSee('bertels-sidebar-theme', escape: false)
            ->assertSee('--sidebar-width: 20rem', escape: false);

        // Claesen keeps its single "Content & Website" group; the cluster's four sections are not
        // promoted to top-level groups there.
        $response->assertSee('data-group-label="' . e(__('navigation.groups.content_website')) . '"', escape: false);

        foreach (WebsiteNavigationGroups::all() as $group) {
            $response->assertDontSee('data-group-label="' . e(WebsiteNavigationGroups::label($group)) . '"', escape: false);
        }
    }

    /**
     * Both panels run in SPA mode, and Livewire's navigate merges <head> instead of replacing it, so
     * an unscoped theme <style> survives a panel switch and restyles the other panel. Every rule
     * must hang off the bertels body class (Filament puts `fi-panel-{id}` on <body>).
     */
    public function test_the_bertels_theme_style_is_scoped_to_the_bertels_panel_body(): void
    {
        $html = $this->actingAs($this->superAdmin())->get('/bertels')->assertSuccessful()->getContent();

        $this->assertSame(1, preg_match('/<style id="bertels-sidebar-theme">(.*?)<\/style>/s', $html, $match));

        $css = (string) preg_replace('/\/\*.*?\*\//s', '', $match[1]);

        $this->assertStringNotContainsString(':root', $css);
        $this->assertStringNotContainsString('html .fi-', $css);
        $this->assertStringContainsString('body.fi-panel-bertels {', $css);

        // Every selector of every rule (incl. those inside @media) starts with the scope.
        preg_match_all('/([^{}]+)\{/', (string) preg_replace('/@media[^{]*\{/', '', $css), $rules);
        $selectors = collect($rules[1])
            ->flatMap(fn (string $list): array => explode(',', $list))
            ->map(fn (string $selector): string => trim($selector))
            ->filter();

        $this->assertNotEmpty($selectors);

        foreach ($selectors as $selector) {
            $this->assertStringStartsWith('body.fi-panel-bertels', $selector, "Unscoped selector in the bertels theme: {$selector}");
        }
    }

    public function test_the_panel_switch_links_are_excluded_from_spa_navigation_on_both_panels(): void
    {
        $user = $this->superAdmin();

        foreach (['admin', 'bertels'] as $panelId) {
            $panel = Filament::getPanel($panelId);

            $this->assertTrue($panel->hasSpaMode(), "{$panelId} is expected to run in SPA mode.");
            $this->assertContains('*/switch-panel/*', $panel->getSpaUrlExceptions(), "{$panelId} must exclude the panel switch from SPA navigation.");
        }

        $html = $this->actingAs($user)->get('/bertels')->assertSuccessful()->getContent();

        $this->assertSame(1, preg_match('/<a\b[^>]*href="[^"]*\/switch-panel\/admin[^"]*"[^>]*>/', $html, $link));
        $this->assertStringNotContainsString('wire:navigate', $link[0], 'The switch link must be a full page load.');
    }

    /**
     * Every class sidebar-theme.blade.php styles must exist in Filament's rendered sidebar, and every
     * item must carry its label text: the app overrides Filament's sidebar item view, and after the
     * 5.10 upgrade that override rendered empty labels (Filament passes `label`, not the slot) while
     * every colour and contrast check still passed.
     */
    public function test_the_bertels_sidebar_markup_has_every_class_the_theme_targets_and_visible_labels(): void
    {
        $html = $this->actingAs($this->superAdmin())
            ->get('/bertels/website/settings-general')
            ->assertSuccessful()
            ->getContent();

        foreach ([
            'fi-sidebar', 'fi-sidebar-header', 'fi-sidebar-nav', 'fi-sidebar-nav-groups', 'fi-sidebar-group-items',
            'fi-sidebar-group-btn', 'fi-sidebar-group-label', 'fi-sidebar-group-collapse-btn', 'fi-sidebar-item-btn',
            'fi-sidebar-item-label', 'fi-sidebar-item-icon', 'fi-sidebar-item fi-active',
        ] as $class) {
            $this->assertStringContainsString($class, $html, "The theme targets .{$class}, but Filament no longer renders it.");
        }

        $this->assertSidebarItemLabelsAreRendered($html);
    }

    public function test_the_claesen_admin_sidebar_items_render_their_label_text(): void
    {
        $html = $this->actingAs($this->superAdmin())->get('/')->assertSuccessful()->getContent();

        $this->assertSidebarItemLabelsAreRendered($html);
    }

    private function assertSidebarItemLabelsAreRendered(string $html): void
    {
        $count = preg_match_all('/<span\b[^>]*\bclass="fi-sidebar-item-label"[^>]*>(.*?)<\/span>/s', $html, $matches);

        $this->assertGreaterThan(0, $count, 'The sidebar renders no item labels at all.');

        foreach ($matches[1] as $inner) {
            $text = trim(html_entity_decode(strip_tags((string) preg_replace('/<!--.*?-->/s', '', $inner))));

            $this->assertNotSame('', $text, 'A sidebar item renders an empty label: ' . trim($inner));
        }
    }

    /**
     * The cluster publishes flat items on the bertels panel (one per page, each in its own group,
     * "Website" first in Content) and keeps the nested shape everywhere else.
     */
    public function test_the_cluster_items_are_flat_on_bertels_and_nested_elsewhere(): void
    {
        $this->actingAs($this->superAdmin());

        Filament::setCurrentPanel(Filament::getPanel('bertels'));

        $flat = WebsiteCluster::getNavigationItems();

        $this->assertNotEmpty($flat);

        foreach ($flat as $item) {
            $this->assertEmpty($item->getChildItems(), "{$item->getLabel()} must not nest children on bertels.");
        }

        $contentLabel = WebsiteNavigationGroups::label(WebsiteNavigationGroups::CONTENT);
        $content = collect($flat)->filter(fn ($item): bool => $item->getGroup() === $contentLabel)->sortBy(fn ($item): int => $item->getSort());

        $this->assertSame(__('website.cluster_label'), $content->first()->getLabel(), 'Website is the first item of Content.');

        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $nested = WebsiteCluster::getNavigationItems();

        $this->assertCount(1, $nested);
        $this->assertCount(4, $nested[0]->getChildItems());
    }
}
