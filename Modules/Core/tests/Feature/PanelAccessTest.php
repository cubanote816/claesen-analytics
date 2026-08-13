<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Feature;

use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\Core\Models\User;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PanelAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['project_manager', 'super_admin', 'admin', 'financial_manager', 'hr_manager', 'viewer', 'client'] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }
    }

    private function activeUser(): User
    {
        return UserFactory::new()->create([
            'password' => bcrypt('Secret1234!'),
            'password_set_at' => now()->subDay(),
            'is_active' => true,
        ]);
    }

    public function test_project_manager_has_no_panel_access(): void
    {
        $user = $this->activeUser();
        $user->assignRole('project_manager');

        $this->assertFalse($user->hasPanelAccess());
    }

    public function test_client_and_roleless_users_have_no_panel_access(): void
    {
        $client = $this->activeUser();
        $client->assignRole('client');

        $mixedRoleClient = $this->activeUser();
        $mixedRoleClient->assignRole(['client', 'admin']);

        $this->assertFalse($client->hasPanelAccess());
        $this->assertFalse($mixedRoleClient->hasPanelAccess());
        $this->assertFalse($this->activeUser()->hasPanelAccess());
    }

    public function test_explicit_internal_roles_have_panel_access(): void
    {
        foreach (['super_admin', 'admin', 'financial_manager', 'hr_manager', 'viewer'] as $role) {
            $user = $this->activeUser();
            $user->assignRole($role);
            $this->assertTrue($user->hasPanelAccess(), "{$role} should have panel access.");
        }
    }

    public function test_project_manager_is_redirected_away_from_the_panel(): void
    {
        $user = $this->activeUser();
        $user->assignRole('project_manager');

        $this->actingAs($user)
            ->get('/')
            ->assertRedirect(route('auth.no-access'));
    }

    public function test_client_user_is_redirected_away_from_the_panel(): void
    {
        $user = $this->activeUser();
        $user->assignRole('client');

        $this->actingAs($user)
            ->get('/')
            ->assertRedirect(route('auth.no-access'));
    }

    public function test_project_manager_sees_the_welcome_page(): void
    {
        $user = $this->activeUser();
        $user->assignRole('project_manager');

        $this->actingAs($user)
            ->get(route('auth.no-access'))
            ->assertOk()
            ->assertSee('Nog geen toegang');
    }

    public function test_user_with_panel_access_is_redirected_away_from_the_welcome_page(): void
    {
        $user = $this->activeUser();
        $user->assignRole('admin');

        $this->actingAs($user)
            ->get(route('auth.no-access'))
            ->assertRedirect('/');
    }

    public function test_project_manager_can_still_log_out_from_the_welcome_page(): void
    {
        $user = $this->activeUser();
        $user->assignRole('project_manager');

        $this->actingAs($user)
            ->post(route('filament.admin.auth.logout'))
            ->assertRedirect(route('filament.admin.auth.login'));

        $this->assertGuest();
    }

    // CLA-363: the login attempt itself must fail for client/technician (no session
    // established at all) — unlike hasPanelAccess()/EnsurePanelAccess above, which
    // only redirect an *already authenticated* user away from panel resources.
    // canAccessPanel() intentionally stays permissive (tests above), so this is
    // exercised through Modules\Core\Filament\Pages\Auth\Login instead.
    public function test_client_and_technician_login_attempts_fail_without_establishing_a_session(): void
    {
        Role::firstOrCreate(['name' => 'technician', 'guard_name' => 'web']);

        foreach (['client', 'technician'] as $role) {
            $user = $this->activeUser();
            $user->assignRole($role);

            Livewire::test(\Modules\Core\Filament\Pages\Auth\Login::class)
                ->fillForm(['email' => $user->email, 'password' => 'Secret1234!'])
                ->call('authenticate')
                ->assertHasFormErrors();

            $this->assertGuest();
        }
    }

    public function test_other_existing_roles_can_still_log_in_through_the_panel(): void
    {
        foreach (['super_admin', 'admin', 'financial_manager', 'hr_manager', 'viewer', 'project_manager'] as $role) {
            $user = $this->activeUser();
            $user->assignRole($role);

            // WithRateLimiting throttles authenticate() at 5/min keyed on
            // component+method+IP — every iteration below shares that key, so it
            // needs clearing between roles or the loop trips its own rate limit.
            \Illuminate\Support\Facades\RateLimiter::clear(
                'livewire-rate-limiter:'.sha1(\Modules\Core\Filament\Pages\Auth\Login::class.'|authenticate|127.0.0.1'),
            );

            Livewire::test(\Modules\Core\Filament\Pages\Auth\Login::class)
                ->fillForm(['email' => $user->email, 'password' => 'Secret1234!'])
                ->call('authenticate')
                ->assertHasNoFormErrors();

            $this->assertAuthenticatedAs($user);

            $this->app['auth']->guard('web')->logout();
        }
    }
}
