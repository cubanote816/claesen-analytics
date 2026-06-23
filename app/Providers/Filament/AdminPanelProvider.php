<?php

namespace App\Providers\Filament;

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
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

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
                    \Illuminate\Support\Facades\Blade::render("@livewire('session-keeper', ['lifetime' => 7200, 'warningThreshold' => 300])"),
            );

            FilamentView::registerRenderHook(
                PanelsRenderHook::AUTH_LOGIN_FORM_BEFORE,
                static fn(): string => view('core::filament.auth.microsoft-login-errors')->render(),
            );

            FilamentView::registerRenderHook(
                PanelsRenderHook::AUTH_LOGIN_FORM_AFTER,
                static fn(): string => view('core::filament.auth.microsoft-login-button')->render(),
            );
        });
    }
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('')
            ->login()
            ->databaseNotifications()
            ->databaseNotificationsPolling('30s')
            ->navigationGroups([
                NavigationGroup::make('Analyse & Intelligentie')
                    ->label(__('navigation.groups.analyse_intelligentie'))
                    ->icon('heroicon-o-presentation-chart-line'),
                NavigationGroup::make('Workforce & Performance')
                    ->label(__('navigation.groups.workforce_performance'))
                    ->icon('heroicon-o-user-group'),
                NavigationGroup::make('Growth & Acquisition')
                    ->label(__('navigation.groups.growth_acquisition'))
                    ->icon('heroicon-o-chart-bar-square'),
                NavigationGroup::make('Intelligence Hub')
                    ->label(__('navigation.groups.intelligence_hub'))
                    ->icon('heroicon-o-sparkles'),
                NavigationGroup::make('Content & Website')
                    ->label(__('navigation.groups.content_website'))
                    ->icon('heroicon-o-globe-alt'),
                NavigationGroup::make('FieldOps & Installations')
                    ->label(__('navigation.groups.field_ops'))
                    ->icon('heroicon-o-map-pin'),
                NavigationGroup::make('User Management')
                    ->label(__('navigation.groups.user_management'))
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
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
                \Modules\Core\Http\Middleware\BrowserLocaleMiddleware::class,
                \Modules\Core\Http\Middleware\EnsurePasswordIsSet::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ])
            ->spa()
            ->defaultThemeMode(\Filament\Enums\ThemeMode::Dark)
            ->navigationItems([
                NavigationItem::make(__('website.v1_demo_link'))
                    ->url('https://backend.claesen-verlichting.be/', shouldOpenInNewTab: true)
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->group('Content & Website')
                    ->sort(10),
                NavigationItem::make(__('website.safety_pwa_link'))
                    ->url('https://service.claesen-verlichting.be/', shouldOpenInNewTab: true)
                    ->icon('heroicon-o-shield-check')
                    ->group('Content & Website')
                    ->sort(11),
                NavigationItem::make('FieldOps PWA')
                    ->url('https://fieldops.claesen-verlichting.be/', shouldOpenInNewTab: true)
                    ->icon('heroicon-o-device-phone-mobile')
                    ->group('FieldOps & Installations')
                    ->sort(99),
            ]);
    }
}
