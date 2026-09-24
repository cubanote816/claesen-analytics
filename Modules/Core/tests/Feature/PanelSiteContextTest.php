<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Feature;

use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Modules\Core\Http\Middleware\EnsureUserBelongsToPanelSite;
use Modules\Core\Models\Organization;
use Modules\Core\Models\Site;
use Modules\Core\Models\User;
use Modules\Core\Services\OrganizationContext;
use Spatie\Permission\Models\Role;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

/**
 * CLA-598 (T2): inside a Filament panel the panel decides the site, and only
 * that site's organization may work in it.
 */
final class PanelSiteContextTest extends TestCase
{
    use RefreshDatabase;

    private Site $bertelsSite;

    protected function setUp(): void
    {
        parent::setUp();

        $organization = Organization::factory()->create(['slug' => 'electro-bertels']);
        $this->bertelsSite = Site::factory()->create(['organization_id' => $organization->id, 'key' => 'electro-bertels']);

        foreach (['super_admin', 'admin'] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }
    }

    private function inPanel(string $panelId): void
    {
        Filament::setCurrentPanel(Filament::getPanel($panelId));
    }

    private function claesenUser(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user->refresh();
    }

    public function test_a_claesen_super_admin_in_the_bertels_panel_gets_the_bertels_site(): void
    {
        $this->actingAs($this->claesenUser('super_admin'));
        $this->inPanel('bertels');

        $this->assertSame($this->bertelsSite->id, app(OrganizationContext::class)->siteId());
    }

    public function test_the_admin_panel_keeps_the_claesen_site(): void
    {
        $this->actingAs($this->claesenUser('admin'));
        $this->inPanel('admin');

        $this->assertSame(Site::claesenId(), app(OrganizationContext::class)->siteId());
    }

    public function test_a_mapped_panel_without_a_site_row_never_falls_back_to_the_users_site(): void
    {
        $this->bertelsSite->delete();
        $this->actingAs($this->claesenUser('super_admin'));
        $this->inPanel('bertels');

        $this->assertNull(app(OrganizationContext::class)->siteId());
    }

    public function test_outside_any_panel_the_site_still_comes_from_the_users_organization(): void
    {
        Filament::setCurrentPanel(null);
        $this->actingAs($this->claesenUser('admin'));

        $this->assertSame(Site::claesenId(), app(OrganizationContext::class)->siteId());
    }

    public function test_middleware_lets_a_claesen_admin_into_the_admin_panel(): void
    {
        config(['organizations.enforce' => true]);
        $this->actingAs($this->claesenUser('admin'));
        $this->inPanel('admin');

        $response = (new EnsureUserBelongsToPanelSite)->handle(Request::create('/'), fn () => response('ok'));

        $this->assertSame('ok', $response->getContent());
    }

    public function test_middleware_rejects_a_claesen_admin_in_the_bertels_panel(): void
    {
        config(['organizations.enforce' => true]);
        $this->actingAs($this->claesenUser('admin'));
        $this->inPanel('bertels');

        $this->expectException(HttpException::class);
        (new EnsureUserBelongsToPanelSite)->handle(Request::create('/'), fn () => response('ok'));
    }

    public function test_middleware_rejects_a_bertels_user_in_the_admin_panel(): void
    {
        config(['organizations.enforce' => true]);
        $user = User::factory()->create(['organization_id' => $this->bertelsSite->organization_id]);
        $user->assignRole('admin');
        $this->actingAs($user);
        $this->inPanel('admin');

        $this->expectException(HttpException::class);
        (new EnsureUserBelongsToPanelSite)->handle(Request::create('/'), fn () => response('ok'));
    }

    public function test_middleware_lets_a_bertels_admin_into_the_bertels_panel_and_super_admin_anywhere(): void
    {
        config(['organizations.enforce' => true]);

        $bertels = User::factory()->create(['organization_id' => $this->bertelsSite->organization_id]);
        $bertels->assignRole('admin');
        $this->actingAs($bertels);
        $this->inPanel('bertels');
        $this->assertSame('ok', (new EnsureUserBelongsToPanelSite)->handle(Request::create('/'), fn () => response('ok'))->getContent());

        $this->actingAs($this->claesenUser('super_admin'));
        $this->assertSame('ok', (new EnsureUserBelongsToPanelSite)->handle(Request::create('/'), fn () => response('ok'))->getContent());
    }

    public function test_middleware_is_inert_with_the_flag_off(): void
    {
        config(['organizations.enforce' => false]);
        $this->actingAs($this->claesenUser('admin'));
        $this->inPanel('bertels');

        $response = (new EnsureUserBelongsToPanelSite)->handle(Request::create('/'), fn () => response('ok'));

        $this->assertSame('ok', $response->getContent());
    }
}
