<?php

namespace App\Providers\Filament;

use Filament\Auth\MultiFactor\App\AppAuthentication;
use Filament\Auth\MultiFactor\Email\EmailAuthentication;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationItem;
use Filament\Pages\Dashboard;
use Filament\View\PanelsRenderHook;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Modules\Core\Filament\Pages\Auth\Login;
use Modules\Core\Http\Middleware\AssignCorrelationId;
use Modules\Core\Http\Middleware\BrowserLocaleMiddleware;
use Modules\Core\Http\Middleware\EnsurePasswordIsSet;
use Modules\Core\Http\Middleware\ResolveOrganizationContext;
use Modules\Core\Http\Middleware\UpdateUserActivity;

/**
 * F1/P6 spike (CLA-549) — docs/ai/adr-multi-organization.md.
 *
 * Deliberately minimal: no resources, pages or widgets of its own. Its only
 * job is to prove the technical assumptions the ADR flags as unverified
 * before building anything real on top — a second Filament panel sharing the
 * same auth guard/session as `admin` (D1: the org boundary lives in the URL,
 * not the session), the same Azure login page/callback without touching
 * MicrosoftAuthController, and Filament's own per-panel authorization hook
 * (see Modules\Core\Models\User::canAccessPanel()) rather than a bespoke
 * middleware duplicating EnsurePanelAccess's Claesen role allowlist.
 *
 * Not `->default()` and not registered in EnsurePanelAccess's allowlist —
 * `User::canAccessPanel()` is what actually gates this panel to super_admin
 * only, since no real Bertels user exists yet (ADR D10, "regla de hierro").
 *
 * Rollback (per the ADR's own P6 row): remove this class from
 * bootstrap/providers.php.
 */
class BertelsPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('bertels')
            ->path('bertels')
            ->login(Login::class)
            // CLA-464 (ADR D8) — same MFA configuration as `admin`, see its
            // provider for the full rationale. Only super_admin can reach
            // this panel at all (CLA-549), so the role gate in
            // EnsureAdminRoleMultiFactorAuthenticationIsEnabled always
            // matches here in practice; kept identical rather than
            // special-cased for one panel.
            ->multiFactorAuthentication([
                AppAuthentication::make(),
                EmailAuthentication::make(),
            ])
            ->multiFactorAuthenticationRequiredMiddlewareName(\Modules\Core\Http\Middleware\EnsureAdminRoleMultiFactorAuthenticationIsEnabled::class)
            ->requiresMultiFactorAuthentication()
            ->colors([
                'primary' => Color::hex('#EE7203'), // Electro Bertels orange
                'gray' => Color::hex('#121212'),    // Electro Bertels deep black
                'danger' => Color::Red,
                'success' => Color::Green,
                'warning' => Color::Amber,
                'info' => Color::hex('#EE7203'),
            ])
            // Real browser verification (2026-09-18) found bertels-brand-logo-light.jpg
            // (265x360, a tall stacked icon+wordmark lockup) nearly illegible at any
            // topbar-sized height — the wordmark below the icon gets crushed. The
            // dark-mode wordmark (1774x887, a wide horizontal lockup) has no light-mode
            // equivalent among the assets handed over for this spike, so light mode
            // uses the square icon-only mark instead (472x452, same file as the
            // favicon) rather than stretching a badly-fitted asset.
            ->brandName('Electro Bertels')
            ->renderHook(
                PanelsRenderHook::USER_MENU_BEFORE,
                fn (): string => view('core::filament.organization-indicator')->render(),
            )
            ->brandLogo(asset('img/bertels-favicon.jpg'))
            ->darkModeBrandLogo(asset('img/bertels-brand-logo-dark.png'))
            ->brandLogoHeight('2.5rem')
            ->favicon(asset('img/bertels-favicon.jpg'))
            // No discoverResources/Pages/Widgets on purpose — F3/F4 give
            // Bertels its own resources once P5 enforcement exists.
            // CLA-598: the Website cluster (projects, leads, announcements, site settings)
            // is the only cluster under app/Filament/Clusters; every resource in it is
            // scoped to the panel's own site (App\Filament\Concerns\ScopedToPanelSite).
            ->discoverClusters(in: app_path('Filament/Clusters'), for: 'App\\Filament\\Clusters')
            ->pages([
                Dashboard::class,
            ])
            ->widgets([])
            // F1/P4+P6 (CLA-460 cont.): the same audited switch back to
            // Claesen. Always visible here — only super_admin can reach this
            // panel at all (User::canAccessPanel()) — kept as an explicit
            // ->visible() anyway for defense in depth / symmetry with admin.
            ->navigationItems([
                NavigationItem::make(fn () => __('navigation.switch_to_claesen'))
                    ->url(fn () => route('core.switch-panel', ['panel' => 'admin', 'from' => 'bertels']))
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->visible(fn () => auth()->user()?->hasRole('super_admin')),
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
                BrowserLocaleMiddleware::class,
                EnsurePasswordIsSet::class,
                // Deliberately no EnsurePanelAccess here — that middleware's
                // allowlist is Claesen's role list. User::canAccessPanel()
                // already gates this panel to super_admin via Filament's own
                // Authenticate middleware (authMiddleware, below).
                // F2/CLA-465 real fix, found while building it: this panel
                // builds its OWN explicit middleware list rather than the
                // 'web' group alias, so bootstrap/app.php's
                // $middleware->web(append: [...]) NEVER reached this panel's
                // routes — confirmed with a real request (Cache::has() proof
                // for UpdateUserActivity's own side effect returned false
                // before this fix). AssignCorrelationId/UpdateUserActivity/
                // ResolveOrganizationContext all belong here explicitly.
                AssignCorrelationId::class,
                UpdateUserActivity::class,
                ResolveOrganizationContext::class,
                \Modules\Core\Http\Middleware\EnsureUserBelongsToPanelSite::class,
            ])
            ->persistentMiddleware([
                \Modules\Core\Http\Middleware\EnsureUserBelongsToPanelSite::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ])
            ->spa();
    }
}
