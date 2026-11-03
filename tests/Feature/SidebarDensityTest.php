<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Models\Organization;
use Modules\Core\Models\Site;
use Modules\Core\Models\User;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * CLA-643: compact sidebar density (Google AI Studio style) shared by both panels.
 *
 * The density lives in one plain <style> hooked on HEAD_END by both panel providers, so the
 * Claesen and Bertels sidebars keep identical dimensions (DESIGN.md: no visual jump between
 * panels). Real HTTP GETs because the sidebar is panel layout; one test method per panel because
 * Filament's NavigationManager is a scoped binding the test client reuses (see BertelsSidebarTest).
 */
final class SidebarDensityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();

        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $organization = Organization::factory()->create(['slug' => 'electro-bertels']);
        Site::factory()->create(['organization_id' => $organization->id, 'key' => 'electro-bertels']);
    }

    private function superAdmin(): User
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole('super_admin');

        return $user;
    }

    private function densityCss(string $html): string
    {
        $this->assertSame(1, preg_match_all('/<style id="sidebar-density">(.*?)<\/style>/s', $html, $matches), 'The shared sidebar density style must be rendered exactly once.');

        return (string) preg_replace('/\/\*.*?\*\//s', '', $matches[1][0]);
    }

    public function test_the_claesen_admin_panel_renders_the_shared_density_style(): void
    {
        $html = $this->actingAs($this->superAdmin())->get('/')->assertSuccessful()->getContent();

        $this->assertSame($this->densityCss(view('filament.shared.sidebar-density')->render()), $this->densityCss($html));
    }

    public function test_the_bertels_panel_renders_the_same_shared_density_style(): void
    {
        $html = $this->actingAs($this->superAdmin())->get('/bertels')->assertSuccessful()->getContent();

        $this->assertSame($this->densityCss(view('filament.shared.sidebar-density')->render()), $this->densityCss($html));
    }

    public function test_the_density_style_compacts_the_sidebar_like_the_reference(): void
    {
        $css = $this->densityCss(view('filament.shared.sidebar-density')->render());

        // Section headers: small uppercase labels, as in the Google AI Studio reference.
        $this->assertMatchesRegularExpression('/\.fi-sidebar-group-label\s*\{[^}]*text-transform:\s*uppercase;/', $css);
        // The expanded header drops its icon; the collapsed dropdown trigger keeps its own.
        $this->assertMatchesRegularExpression('/\.fi-sidebar-group-btn\s*>\s*\.fi-icon\s*\{[^}]*display:\s*none;/', $css);
        $this->assertStringNotContainsString('fi-sidebar-group-dropdown-trigger-btn', $css);
        // Tighter rows and section spacing than Filament's defaults (p-2 rows, gap-y-7 groups).
        $this->assertMatchesRegularExpression('/\.fi-sidebar-item-btn\s*\{[^}]*padding:\s*0\.375rem 0\.5rem;/', $css);
        $this->assertMatchesRegularExpression('/\.fi-sidebar-nav-groups\s*\{[^}]*row-gap:\s*1rem;/', $css);
    }

    /**
     * Density only: colours stay with each panel's own theme (Claesen's Vite theme, Bertels'
     * colour skin), and the view must emit nothing but its style block.
     */
    public function test_the_density_style_sets_no_colours_and_renders_only_its_style_block(): void
    {
        $html = view('filament.shared.sidebar-density')->render();

        $this->assertStringStartsWith('<style id="sidebar-density">', ltrim($html));
        $this->assertStringEndsWith('</style>', rtrim($html));

        $this->assertDoesNotMatchRegularExpression(
            '/(?<![\w-])(color|background[\w-]*|border-color|outline-color|fill|stroke)\s*:/',
            $this->densityCss($html),
        );
    }
}
