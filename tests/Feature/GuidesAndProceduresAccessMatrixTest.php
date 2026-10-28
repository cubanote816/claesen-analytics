<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Filament\Guides\Ets6ProjectArchiveGuide as Guide;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\Core\Models\{Organization, Site, User};
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * The super_admin-only gate of the ETS6 Project Archive guide, across every role and both panels.
 *
 * Written by the independent verifier: every non-super_admin role the panels admit, with
 * organization enforcement on and off, gets 403 and no sidebar item; an inactive super_admin is
 * refused; and the gate is re-checked on Livewire hydrate, not only on the first GET. Users who
 * cannot enter the admin panel at all (client, project_manager, no role) never reach the page:
 * EnsurePanelAccess redirects them to /auth/no-access first, which GuidesAndProceduresTest covers
 * through the panel itself.
 */
final class GuidesAndProceduresAccessMatrixTest extends TestCase
{
    use RefreshDatabase;

    private function account(string $panel, string $role, bool $active = true): User
    {
        $this->withoutVite();
        config(['organizations.enforce' => true]);
        $org = Organization::claesenId();
        if ($panel === 'bertels') {
            $org = Organization::factory()->create(['slug' => 'electro-bertels'])->id;
            Site::factory()->create(['organization_id' => $org, 'key' => 'electro-bertels']);
        }
        Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $user = User::factory()->create(['organization_id' => $org, 'is_active' => $active]);
        $user->assignRole($role);
        $this->actingAs($user);
        Filament::setCurrentPanel(Filament::getPanel($panel));
        return $user;
    }

    private function url(string $panel): string
    {
        return ($panel === 'bertels' ? '/bertels' : '').'/guides/ets6-project-archive';
    }

    public static function denied(): iterable
    {
        foreach (['admin', 'bertels'] as $panel) {
            foreach (['admin', 'viewer', 'financial_manager', 'hr_manager', 'technician'] as $role) {
                foreach ([false, true] as $enforce) {
                    yield "$panel $role enforcement=".(int) $enforce => [$panel, $role, $enforce];
                }
            }
        }
    }

    #[DataProvider('denied')]
    public function test_direct_access_and_sidebar(string $panel, string $role, bool $enforce): void
    {
        $this->account($panel, $role);
        config(['organizations.enforce' => $enforce]);
        $this->assertFalse(Guide::canAccess());
        $this->get($this->url($panel))->assertForbidden();
        $this->get($panel === 'bertels' ? '/bertels' : '/')
            ->assertOk()->assertDontSee('ETS6 Project Archive');
    }

    public static function panels(): iterable
    {
        yield ['admin'];
        yield ['bertels'];
    }

    #[DataProvider('panels')]
    public function test_inactive_super_admin_is_forbidden(string $panel): void
    {
        $this->account($panel, 'super_admin', false);
        $this->assertFalse(Guide::canAccess());
        $this->get($this->url($panel))->assertForbidden();
        Livewire::test(Guide::class)->assertForbidden();
    }

    #[DataProvider('panels')]
    public function test_livewire_rechecks_role_after_mount(string $panel): void
    {
        $user = $this->account($panel, 'super_admin');
        $page = Livewire::test(Guide::class)->assertOk();
        $user->syncRoles([]);
        $page->call('$refresh')->assertForbidden();
    }

    #[DataProvider('panels')]
    public function test_livewire_rechecks_active_after_mount(string $panel): void
    {
        $user = $this->account($panel, 'super_admin');
        $page = Livewire::test(Guide::class)->assertOk();
        $user->update(['is_active' => false]);
        $page->call('$refresh')->assertForbidden();
    }

    public static function locales(): iterable
    {
        foreach (['admin', 'bertels'] as $panel) {
            foreach (['nl', 'en', 'fr', 'de'] as $locale) {
                yield "$panel $locale" => [$panel, $locale];
            }
        }
    }

    #[DataProvider('locales')]
    public function test_locale_and_skin(string $panel, string $locale): void
    {
        // A platform super_admin's home is Claesen, even in the other panel.
        $user = $this->account($panel, 'super_admin');
        $user->update(['organization_id' => Organization::claesenId()]);
        $this->withHeader('Accept-Language', $locale)->get($this->url($panel))
            ->assertOk()->assertSee(__('navigation.groups.guides_procedures', [], $locale))
            ->assertSee('ets6-guide--'.($panel === 'bertels' ? 'bertels' : 'claesen'), false)
            ->assertDontSee('ets6-guide--'.($panel === 'bertels' ? 'claesen' : 'bertels'), false);
    }
}
