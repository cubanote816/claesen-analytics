<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Feature;

use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Models\User;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * CLA-464 (ADR D8) — MFA required for super_admin/admin only, via
 * EnsureAdminRoleMultiFactorAuthenticationIsEnabled (see its own docblock for
 * why the role gate lives in that middleware rather than a Closure passed to
 * Panel::requiresMultiFactorAuthentication(), which is evaluated once at
 * boot with no per-request user).
 *
 * Only covers the local-password login path — Modules\Core\Filament\Pages\
 * Auth\Login already carries Filament's MFA challenge verbatim (CLA-363).
 * Azure OAuth logins (MicrosoftAuthController) bypass this middleware's
 * panel entirely at the authentication step, not just the MFA check — out
 * of scope for this ticket, documented in CLAUDE.md.
 */
final class MultiFactorAuthenticationEnforcementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();

        foreach (['super_admin', 'admin', 'financial_manager', 'hr_manager', 'viewer'] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_both_panels_register_app_and_email_multi_factor_providers(): void
    {
        $this->assertSame(
            ['app', 'email_code'],
            array_keys(Filament::getPanel('admin')->getMultiFactorAuthenticationProviders())
        );
        $this->assertSame(
            ['app', 'email_code'],
            array_keys(Filament::getPanel('bertels')->getMultiFactorAuthenticationProviders())
        );
    }

    public function test_an_admin_without_mfa_set_up_is_redirected_to_the_set_up_page(): void
    {
        $user = $this->userWithRole('admin');

        $this->actingAs($user)
            ->get('/')
            ->assertRedirect(route('filament.admin.auth.multi-factor-authentication.set-up-required'));
    }

    public function test_a_super_admin_without_mfa_set_up_is_redirected_on_the_bertels_panel_too(): void
    {
        $user = $this->userWithRole('super_admin');

        $this->actingAs($user)
            ->get('/bertels')
            ->assertRedirect(route('filament.bertels.auth.multi-factor-authentication.set-up-required'));
    }

    public function test_an_admin_with_email_authentication_enabled_reaches_the_dashboard(): void
    {
        $user = $this->userWithRole('admin');
        $user->toggleEmailAuthentication(true);

        $this->actingAs($user)->get('/')->assertSuccessful();
    }

    #[DataProvider('notForcedRolesProvider')]
    public function test_roles_other_than_super_admin_and_admin_are_never_forced(string $role): void
    {
        $user = $this->userWithRole($role);

        $this->actingAs($user)->get('/')->assertSuccessful();
    }

    public static function notForcedRolesProvider(): array
    {
        return [
            'financial_manager' => ['financial_manager'],
            'hr_manager' => ['hr_manager'],
            'viewer' => ['viewer'],
        ];
    }

    public function test_a_guest_hitting_the_set_up_page_directly_is_redirected_to_login(): void
    {
        $this->get('/multi-factor-authentication/set-up')
            ->assertRedirect(route('filament.admin.auth.login'));
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create([
            'is_active' => true,
            'password_set_at' => now(),
        ]);
        $user->assignRole($role);

        return $user->refresh();
    }
}
