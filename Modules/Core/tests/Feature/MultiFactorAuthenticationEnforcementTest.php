<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Feature;

use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Models\Organization;
use Modules\Core\Models\Site;
use Modules\Core\Models\User;
use Modules\FieldOps\Filament\Pages\MyWorkOrders;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * CLA-464 (ADR D8) introduced panel MFA for super_admin/admin only. CLA-684
 * widened it: every panel session not signed in through Microsoft must have a
 * second factor configured, whatever its role, via
 * EnsureLocalPasswordMultiFactorAuthenticationIsEnabled (see its own docblock
 * for why that decision lives in the middleware rather than in
 * Panel::requiresMultiFactorAuthentication(), which is evaluated once at boot
 * with no per-request user).
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
 * Superseded for real Microsoft sign-ins by odd/tasks/release-trunk-to-main.md
 * T6/S2c: the callback now marks the session (Modules\Core\Support\
 * AuthenticatedVia) and the middleware skips marked sessions — see
 * MicrosoftSessionMfaExemptionTest. The tests below use actingAs(), which is
 * not a Microsoft sign-in and carries no marker, so they still pin the
 * unmarked behavior.
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

        config(['core.panel_mfa_enforced' => true]);

        $this->withoutVite();

        foreach (['super_admin', 'admin', 'financial_manager', 'hr_manager', 'viewer', 'technician'] as $role) {
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

    /** CLA-684: the role no longer decides — every non-admin panel role is forced too. */
    #[DataProvider('nonAdminPanelRolesProvider')]
    public function test_every_other_panel_role_without_mfa_set_up_is_redirected_to_the_set_up_page(string $role): void
    {
        $user = $this->userWithRole($role);

        $this->actingAs($user)
            ->get('/')
            ->assertRedirect(route('filament.admin.auth.multi-factor-authentication.set-up-required'));
    }

    #[DataProvider('nonAdminPanelRolesProvider')]
    public function test_every_other_panel_role_with_email_authentication_enabled_is_not_redirected(string $role): void
    {
        $user = $this->userWithRole($role);
        $user->toggleEmailAuthentication(true);

        $response = $this->actingAs($user)->get('/');

        $this->assertFalse(
            $response->isRedirect(route('filament.admin.auth.multi-factor-authentication.set-up-required')),
            "{$role} with a configured factor must not be sent to MFA set-up"
        );
    }

    public static function nonAdminPanelRolesProvider(): array
    {
        return [
            'financial_manager' => ['financial_manager'],
            'hr_manager' => ['hr_manager'],
            'viewer' => ['viewer'],
            'technician' => ['technician'],
        ];
    }

    public function test_a_technician_without_mfa_is_sent_to_set_up_and_reaches_my_work_orders_once_it_is_set_up(): void
    {
        $user = $this->userWithRole('technician');
        $myWorkOrders = MyWorkOrders::getUrl(panel: 'admin');

        $this->actingAs($user)
            ->get($myWorkOrders)
            ->assertRedirect(route('filament.admin.auth.multi-factor-authentication.set-up-required'));

        $this->actingAs($user)
            ->get(route('filament.admin.auth.multi-factor-authentication.set-up-required'))
            ->assertSuccessful();

        $user->toggleEmailAuthentication(true);

        $this->actingAs($user->refresh())->get($myWorkOrders)->assertSuccessful();
    }

    /** CLA-684: the bertels panel applies the same rule to its non-admin users, who can still set up and log out. */
    public function test_a_bertels_viewer_without_mfa_must_set_it_up_and_can_still_reach_set_up_and_logout(): void
    {
        $organization = Organization::factory()->create(['slug' => 'electro-bertels']);
        Site::factory()->create([
            'organization_id' => $organization->id,
            'key' => 'electro-bertels',
            'status' => Site::STATUS_ACTIVE,
        ]);

        $user = $this->userWithRole('viewer');
        $user->forceFill(['organization_id' => $organization->id])->save();

        $setUp = route('filament.bertels.auth.multi-factor-authentication.set-up-required');

        $this->actingAs($user->refresh())->get('/bertels')->assertRedirect($setUp);
        $this->get($setUp)->assertSuccessful();
        $this->post(route('filament.bertels.auth.logout'))->assertRedirect();
        $this->assertGuest();
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
