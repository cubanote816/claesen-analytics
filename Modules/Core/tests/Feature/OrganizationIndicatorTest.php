<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Models\Organization;
use Modules\Core\Models\Site;
use Modules\Core\Models\User;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * CLA-600: each panel's top bar names the company it is working on.
 */
final class OrganizationIndicatorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);

        $user = User::factory()->create();
        $user->assignRole('super_admin');
        $this->actingAs($user);
    }

    private function createBertelsSite(): void
    {
        $organization = Organization::factory()->create(['slug' => 'electro-bertels', 'name' => 'Electro Bertels']);
        Site::factory()->create(['organization_id' => $organization->id, 'key' => 'electro-bertels']);
    }

    public function test_the_admin_panel_names_claesen(): void
    {
        $claesen = Organization::query()->findOrFail(Organization::claesenId());

        $this->get('/')
            ->assertOk()
            ->assertSee('data-organization-indicator="'.$claesen->slug.'"', false)
            ->assertSee($claesen->name);
    }

    public function test_the_bertels_panel_names_electro_bertels_with_its_own_brand_name(): void
    {
        $this->createBertelsSite();

        $this->get('/bertels')
            ->assertOk()
            ->assertSee('data-organization-indicator="electro-bertels"', false)
            ->assertSee('Electro Bertels');
    }

    public function test_super_admin_switches_from_the_admin_topbar_not_the_sidebar(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString('data-organization-selector="admin"', $html);
        $this->assertStringContainsString('href="'.route('core.switch-panel', ['panel' => 'bertels', 'from' => 'admin']).'"', $html);
        $this->assertStringContainsString(__('navigation.switch_to_bertels'), $html);
        $this->assertSame(1, substr_count($html, '/switch-panel/bertels?from=admin'));
        $this->assertDoesNotMatchRegularExpression('/<a[^>]*switch-panel\/bertels[^>]*class="fi-sidebar-item-btn"/', $html);
        $this->assertSame(1, preg_match('/<a\b[^>]*href="[^"]*\/switch-panel\/bertels\?from=admin"[^>]*>/', $html, $link));
        $this->assertStringNotContainsString('wire:navigate', $link[0]);
    }

    public function test_super_admin_switches_from_the_bertels_topbar_not_the_sidebar(): void
    {
        $this->createBertelsSite();
        $html = $this->get('/bertels')->assertOk()->getContent();

        $this->assertStringContainsString('data-organization-selector="bertels"', $html);
        $this->assertStringContainsString('href="'.route('core.switch-panel', ['panel' => 'admin', 'from' => 'bertels']).'"', $html);
        $this->assertStringContainsString(__('navigation.switch_to_claesen'), $html);
        $this->assertSame(1, substr_count($html, '/switch-panel/admin?from=bertels'));
        $this->assertDoesNotMatchRegularExpression('/<a[^>]*switch-panel\/admin[^>]*class="fi-sidebar-item-btn"/', $html);
    }

    public function test_other_admins_keep_a_read_only_company_badge_without_switch_link(): void
    {
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole('admin');

        $html = $this->actingAs($user)->get('/')->assertOk()->getContent();

        $this->assertStringContainsString('data-organization-indicator=', $html);
        $this->assertStringNotContainsString('data-organization-selector=', $html);
        $this->assertStringNotContainsString('/switch-panel/bertels', $html);
    }

    public function test_bertels_viewer_keeps_an_informative_badge_without_switch_link(): void
    {
        $this->createBertelsSite();
        Role::firstOrCreate(['name' => 'viewer', 'guard_name' => 'web']);
        $site = Site::query()->where('key', 'electro-bertels')->sole();
        $user = User::factory()->create([
            'is_active' => true,
            'password_set_at' => now(),
            'organization_id' => $site->organization_id,
        ]);
        $user->assignRole('viewer');

        $html = $this->actingAs($user)->get('/bertels')->assertOk()->getContent();

        $this->assertStringContainsString('data-organization-indicator="electro-bertels"', $html);
        $this->assertStringNotContainsString('data-organization-selector=', $html);
        $this->assertStringNotContainsString('/switch-panel/', $html);
    }

    public function test_bertels_super_admin_can_switch_back_even_without_a_local_site(): void
    {
        $html = $this->get('/bertels')->assertOk()->getContent();

        $this->assertStringContainsString('data-organization-selector="bertels"', $html);
        $this->assertStringContainsString('Electro Bertels', $html);
        $this->assertStringContainsString('href="'.route('core.switch-panel', ['panel' => 'admin', 'from' => 'bertels']).'"', $html);
        $this->assertStringNotContainsString('data-organization-indicator=', $html, 'Do not pretend a missing site has an organization record.');
    }
}
