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
 * F2/CLA-464 (2026-09-21) — corrected claim, verified with real requests
 * against an isolated DB before writing this comment (not assumed from
 * reading vendor source alone): this panel-wide "you must have a factor
 * configured" redirect is NOT local-login-only. Filament\Auth\MultiFactor\
 * Http\Middleware\EnsureMultiFactorAuthenticationIsEnabled is attached via
 * Filament\Pages\Concerns\HasRoutes::getRouteMiddleware() to every page
 * route in the panel — it only reads Filament::auth()->user(), regardless
 * of how that session was established. An Azure-authenticated super_admin
 * (MicrosoftAuthController::callback() calls the same plain Auth::login())
 * with no factor configured gets redirected to the set-up page exactly
 * like a local-login user — see test_an_azure_authenticated_admin_without_
 * mfa_is_also_redirected_to_the_set_up_page below.
 *
 * What genuinely IS local-login-only is the interactive per-login
 * challenge (entering a fresh code before being logged in at all) —
 * Filament\Auth\Pages\Login::authenticate() holds the user in a
 * "$userUndertakingMultiFactorAuthentication" pending state (a Livewire
 * component property, not a session flag) until the challenge form's own
 * validation passes, THEN calls Auth::login(). MicrosoftAuthController
 * calls Auth::login() directly and unconditionally — so an Azure session
 * for a user who already has a factor configured walks straight to the
 * dashboard without ever being asked for a fresh code that session — see
 * test_an_azure_authenticated_admin_with_mfa_already_set_up_is_never_
 * challenged_for_a_fresh_code below.
 *
 * The real, narrower remaining gap: this app never re-verifies a second
 * factor for an Azure-authenticated session after the one-time setup — a
 * hijacked/replayed Azure session (or an Azure account with no extra
 * tenant-side Conditional Access) rides entirely on Azure's own auth here.
 * Closing that would mean building a genuine post-OAuth-callback challenge
 * page (holding a pending-user handoff outside Login's own Livewire
 * component state, then reusing Filament's challenge form components) —
 * a real, scoped feature of its own, not attempted in this ticket.
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

    public function test_an_azure_authenticated_admin_without_mfa_is_also_redirected_to_the_set_up_page(): void
    {
        $user = $this->azureStyleUserWithRole('super_admin');

        $this->actingAs($user)
            ->get('/')
            ->assertRedirect(route('filament.admin.auth.multi-factor-authentication.set-up-required'));
    }

    public function test_an_azure_authenticated_admin_with_mfa_already_set_up_is_never_challenged_for_a_fresh_code(): void
    {
        $user = $this->azureStyleUserWithRole('super_admin');
        $user->toggleEmailAuthentication(true);

        // actingAs() is the same "already fully authenticated, no
        // interactive login form involved" shape as MicrosoftAuthController's
        // Auth::login($user) — no code is ever entered for this request.
        $this->actingAs($user)->get('/')->assertSuccessful();
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

    private function azureStyleUserWithRole(string $role): User
    {
        $user = User::factory()->create([
            'is_active' => true,
            'password' => null,
            'password_set_at' => null,
            'microsoft_id' => 'azure-oid-'.uniqid(),
        ]);
        $user->assignRole($role);

        return $user->refresh();
    }
}
