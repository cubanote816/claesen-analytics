<?php

namespace App\Providers\Filament;

use Filament\Auth\MultiFactor\App\AppAuthentication;
use Filament\Auth\MultiFactor\Email\EmailAuthentication;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Navigation\NavigationGroup;
use Filament\Navigation\NavigationItem;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Facades\FilamentView;
use Filament\View\PanelsRenderHook;
use Filament\Widgets\AccountWidget;
use Filament\Widgets\FilamentInfoWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Modules\Core\Http\Middleware\AssignCorrelationId;
use Modules\Core\Http\Middleware\ResolveOrganizationContext;
use Modules\Core\Http\Middleware\UpdateUserActivity;

class AdminPanelProvider extends PanelProvider
{
    public function register(): void
    {
        parent::register();

        $this->app->booted(static function () {
            FilamentView::registerRenderHook(
                PanelsRenderHook::BODY_END,
                static fn(): string =>
                view('prospects::filament.prospects.floating-mailing-button')->render() .
                    view('core::filament.sidebar-scroll-restore')->render() .
                    view('core::filament.session-expired-modal')->render() .
                    \Illuminate\Support\Facades\Blade::render("@livewire('session-keeper')"),
            );

            FilamentView::registerRenderHook(
                PanelsRenderHook::AUTH_LOGIN_FORM_BEFORE,
                static fn(): string => view('core::filament.auth.microsoft-login-errors')->render(),
            );

            FilamentView::registerRenderHook(
                PanelsRenderHook::AUTH_LOGIN_FORM_AFTER,
                static fn(): string => view('core::filament.auth.microsoft-login-button')->render(),
            );

            // CLA-603 — split-screen login from the approved mockup. Registered
            // here because FilamentView::registerRenderHook is global and this
            // provider is already where the backoffice's cross-panel render hooks
            // live: the gate below covers EVERY panel's auth screens (login,
            // logout, password reset, MFA challenge) — admin *and* bertels. The
            // theme restyles .fi-simple-layout itself, so it and the brand column
            // must share the exact same gate.
            $isAuthScreen = static fn (): bool => (bool) request()?->routeIs('filament.*.auth.*');

            FilamentView::registerRenderHook(
                PanelsRenderHook::SIMPLE_LAYOUT_START,
                static fn(): string => $isAuthScreen()
                    ? view('core::filament.auth.brand-panel')->render()
                    : '',
            );

            // Plain <style> instead of Tailwind utilities on purpose: the bertels
            // panel has no ->viteTheme(), so utilities used only by the login views
            // are never generated there — the 416px Microsoft icon of CLA-603 was
            // exactly that (h-5 w-5 resolved to nothing).
            FilamentView::registerRenderHook(
                PanelsRenderHook::HEAD_END,
                static fn(): string => $isAuthScreen()
                    ? view('core::filament.auth.login-theme')->render()
                    : '',
            );

            // CLA-601 — the mockup's sign-in column is deliberately light
            // regardless of the panel's own dark default (admin sets
            // ->defaultThemeMode(Dark)): a real two-tone split (dark brand / light
            // functional form), not a uniformly dark page. Filament's own base
            // (non-dark) theme is already a coherent, accessible light design.
            //
            // Two narrower fixes were tried first and empirically failed (kept here
            // so the next person doesn't retry them): a one-shot
            // classList.remove('dark') at HEAD_END, and overriding the
            // --default-theme-mode CSS custom property the base layout also sets.
            // Both lose the race against vendor/filament/filament/resources/js/
            // dark-mode.js, which re-adds the class from an Alpine.effect()
            // reacting to Alpine.store('theme') — that effect (re)runs once Alpine
            // initialises, which in this app's HTML only happens once livewire.js's
            // own <script src> tag executes, itself emitted by Livewire AFTER any
            // renderHook content (verified via curl on the raw response: our hook's
            // markup is on an earlier line than that script tag, in every hook
            // position tried, including BODY_END) — so nothing we render can run
            // its own classList mutation later than Alpine's.
            // A MutationObserver sidesteps the ordering fight entirely: it fires on
            // every future mutation of <html>'s class attribute, however many times
            // something else re-adds 'dark', for as long as this page lives —
            // correct regardless of exactly when Alpine boots. Never touches
            // localStorage/Alpine.store, so it's a display-only override scoped to
            // the auth screens; the rest of the app keeps whatever the user's own
            // theme preference actually is.
            FilamentView::registerRenderHook(
                PanelsRenderHook::HEAD_END,
                static fn(): string => $isAuthScreen()
                    ? <<<'HTML'
                        <script>
                            (function () {
                                var html = document.documentElement;
                                var strip = function () {
                                    if (html.classList.contains('dark')) {
                                        html.classList.remove('dark');
                                    }
                                };
                                strip();
                                new MutationObserver(strip).observe(html, {
                                    attributes: true,
                                    attributeFilter: ['class'],
                                });
                            })();
                        </script>
                        HTML
                    : '',
            );

            FilamentView::registerRenderHook(
                PanelsRenderHook::HEAD_END,
                static fn (): string => <<<'HTML'
<style id="claesen-filament-topbar-override">
    .fi-topbar-ctn {
        position: fixed !important;
        inset: 0 0 auto 0 !important;
        z-index: 10000 !important;
        width: 100% !important;
        background: #ffffff !important;
        border-bottom: 1px solid rgba(15, 23, 42, 0.08) !important;
    }

    .fi-topbar {
        background: #ffffff !important;
        opacity: 1 !important;
        backdrop-filter: none !important;
        box-shadow: 0 1px 0 rgba(15, 23, 42, 0.06) !important;
    }

    .fi-body-has-topbar .fi-layout {
        padding-top: 4rem !important;
    }

    .fi-topbar-ctn {
        overflow-x: clip !important;
    }

    .dark .fi-topbar-ctn,
    .dark .fi-topbar {
        background: #14141b !important;
    }

    .dark .fi-topbar-ctn {
        border-bottom-color: rgba(255, 255, 255, 0.08) !important;
    }
</style>
HTML
            );

            // CLA-449 — modern favicon/app icon set. Not via Filament's own
            // ->favicon() (HasFavicon trait): that only supports a single
            // <link rel="icon">, not this full set, and would duplicate the
            // .ico entry if combined with this hook.
            FilamentView::registerRenderHook(
                PanelsRenderHook::HEAD_END,
                static fn (): string => <<<'HTML'
<link rel="icon" href="/favicon.ico" sizes="16x16 32x32 48x48 64x64 128x128 256x256">
<link rel="icon" href="/favicon.svg" type="image/svg+xml" sizes="any">
<link rel="icon" href="/favicon-32x32.png" type="image/png" sizes="32x32">
<link rel="apple-touch-icon" href="/apple-touch-icon.png" sizes="180x180">
<link rel="manifest" href="/site.webmanifest">
HTML
            );
        });
    }
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('')
            ->login(\Modules\Core\Filament\Pages\Auth\Login::class)
            // CLA-603: the mockup's forgot/reset screens, backed by this
            // project's own reset mechanism (PasswordResetService) instead of
            // Filament's password_reset_tokens broker — see the pages' docblocks.
            ->passwordReset(
                \Modules\Core\Filament\Pages\Auth\RequestPasswordReset::class,
                \Modules\Core\Filament\Pages\Auth\ResetPassword::class,
            )
            ->databaseNotifications()
            ->databaseNotificationsPolling('30s')
            // CLA-464 (ADR D8): required only for super_admin/admin, not every
            // internal role with panel access — see the middleware's own
            // docblock for why the role gate lives there and not in a Closure
            // passed to requiresMultiFactorAuthentication() (evaluated once at
            // boot, no per-request user). Only covers the local-password login
            // path (Modules\Core\Filament\Pages\Auth\Login already carries
            // Filament's MFA challenge verbatim, CLA-363); Azure OAuth logins
            // (MicrosoftAuthController) bypass this entirely — that path would
            // need Azure Conditional Access, outside this repo.
            ->multiFactorAuthentication([
                AppAuthentication::make(),
                EmailAuthentication::make(),
            ])
            ->multiFactorAuthenticationRequiredMiddlewareName(\Modules\Core\Http\Middleware\EnsureAdminRoleMultiFactorAuthenticationIsEnabled::class)
            ->requiresMultiFactorAuthentication()
            ->navigationGroups([
                NavigationGroup::make('Workforce & Performance')
                    ->label(fn () => __('navigation.groups.workforce_performance'))
                    ->icon('heroicon-o-user-group'),
                NavigationGroup::make('Growth & Acquisition')
                    ->label(fn () => __('navigation.groups.growth_acquisition'))
                    ->icon('heroicon-o-chart-bar-square'),
                NavigationGroup::make('Mailing')
                    ->label(fn () => __('navigation.groups.mailing'))
                    ->icon('heroicon-o-envelope'),
                NavigationGroup::make('Safety & VCA')
                    ->label(fn () => __('navigation.groups.safety_vca'))
                    ->icon('heroicon-o-shield-check'),
                NavigationGroup::make('Content & Website')
                    ->label(fn () => __('navigation.groups.content_website'))
                    ->icon('heroicon-o-globe-alt'),
                NavigationGroup::make('Intelligence Hub')
                    ->label(fn () => __('navigation.groups.intelligence_hub'))
                    ->icon('heroicon-o-sparkles'),
                NavigationGroup::make('Field Operations')
                    ->label(fn () => __('navigation.groups.field_operations'))
                    ->icon('heroicon-o-wrench-screwdriver'),
                NavigationGroup::make('User Management')
                    ->label(fn () => __('navigation.groups.user_management'))
                    ->icon('heroicon-o-cog-6-tooth'),
            ])
            ->colors([
                'primary' => Color::hex('#00aeef'), // Claesen Cyan
                'success' => Color::hex('#a5d610'), // Claesen Lime
                'danger' => Color::hex('#e6007e'),  // Claesen Magenta
                'warning' => Color::hex('#fcd34d'), // Claesen Amber
                'gray' => Color::Slate,
                'info' => Color::hex('#00aeef'),
            ])
            ->font('Outfit')
            ->sidebarCollapsibleOnDesktop()
            ->collapsibleNavigationGroups()
            ->renderHook(
                PanelsRenderHook::USER_MENU_BEFORE,
                fn (): string => view('core::filament.organization-indicator')->render(),
            )
            ->brandLogo(asset('img/brand-logo-light.png'))
            ->darkModeBrandLogo(asset('img/brand-logo-dark.png'))
            ->brandLogoHeight('3rem')
            ->viteTheme('resources/css/filament/admin/theme.css')
            ->plugin(
                \LaraZeus\SpatieTranslatable\SpatieTranslatablePlugin::make()
                    ->defaultLocales(['nl', 'en', 'fr', 'de'])
            )
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverClusters(in: app_path('Filament/Clusters'), for: 'App\\Filament\\Clusters')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            /** @phpstan-ignore-next-line */
            ->tap(function (Panel $panel) {
                foreach (\Nwidart\Modules\Facades\Module::allEnabled() as $module) {
                    $name = $module->getName();

                    // Resources
                    if (is_dir(module_path($name, 'Filament/Resources'))) {
                        $panel->discoverResources(
                            in: module_path($name, 'Filament/Resources'),
                            for: "Modules\\{$name}\\Filament\\Resources"
                        );
                    }

                    // Pages
                    if (is_dir(module_path($name, 'Filament/Pages'))) {
                        $panel->discoverPages(
                            in: module_path($name, 'Filament/Pages'),
                            for: "Modules\\{$name}\\Filament\\Pages"
                        );
                    }

                    // Widgets
                    if (is_dir(module_path($name, 'Filament/Widgets'))) {
                        $panel->discoverWidgets(
                            in: module_path($name, 'Filament/Widgets'),
                            for: "Modules\\{$name}\\Filament\\Widgets"
                        );
                    }
                }
            })
            ->pages([
                Dashboard::class,
            ])
            ->widgets([
                // Default widgets removed to clean up the dashboard
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
                \Modules\Core\Http\Middleware\BrowserLocaleMiddleware::class,
                \Modules\Core\Http\Middleware\EnsurePasswordIsSet::class,
                \Modules\Core\Http\Middleware\EnsurePanelAccess::class,
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
            ->spa()
            ->defaultThemeMode(\Filament\Enums\ThemeMode::Dark)
            ->navigationItems([
                NavigationItem::make(fn () => __('website.v1_demo_link'))
                    ->url('https://backend.claesen-verlichting.be/', shouldOpenInNewTab: true)
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->group(fn () => __('navigation.groups.content_website'))
                    ->sort(10),
                NavigationItem::make(fn () => __('website.safety_pwa_link'))
                    ->url('https://service.claesen-verlichting.be/', shouldOpenInNewTab: true)
                    ->icon('heroicon-o-shield-check')
                    ->group(fn () => __('navigation.groups.content_website'))
                    ->sort(11),
                // F1/P4+P6 (CLA-460 cont.): audited panel-switch selector.
                // Ungrouped on purpose — a cross-cutting super_admin action,
                // not Website content. No real Bertels user exists yet (D10),
                // so this stays gated to super_admin until P5/P7.
                NavigationItem::make(fn () => __('navigation.switch_to_bertels'))
                    ->url(fn () => route('core.switch-panel', ['panel' => 'bertels', 'from' => 'admin']))
                    ->icon('heroicon-o-building-office-2')
                    ->visible(fn () => auth()->user()?->hasRole('super_admin'))
                    ->sort(100),
            ]);
    }
}
