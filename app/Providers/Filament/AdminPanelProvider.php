<?php

namespace App\Providers\Filament;

use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Navigation\NavigationGroup;
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
                static fn (): string => view('prospects::filament.prospects.floating-mailing-button')->render(),
            );

            FilamentView::registerRenderHook(
                PanelsRenderHook::AUTH_LOGIN_FORM_AFTER,
                static fn (): string => view('core::filament.auth.microsoft-login-button')->render(),
            );
        });
    }
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->colors([
                'primary' => Color::Indigo,
                'gray' => Color::Slate,
                'danger' => Color::Rose,
                'success' => Color::Emerald,
                'warning' => Color::Orange,
                'info' => Color::Blue,
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
                    ->defaultLocales(['nl', 'en'])
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
                AccountWidget::class,
                FilamentInfoWidget::class,
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
            ])
            ->authMiddleware([
                Authenticate::class,
            ])
            ->spa()
            ->navigationGroups([
                // NavigationGroup::make()
                //      ->label('Analyse & Intelligentie')
                //      ->icon('heroicon-o-sparkles'),
                NavigationGroup::make()
                    ->label('Groei & Acquisitie')
                    ->icon('heroicon-o-chart-bar-square'),
                // NavigationGroup::make()
                //     ->label('Operatie & Personeel')
                //     ->icon('heroicon-o-cpu-chip'),
                NavigationGroup::make()
                    ->label('Inhoud & Website')
                    ->icon('heroicon-o-globe-alt'),
                NavigationGroup::make()
                    ->label('Systeem & Beheer')
                    ->icon('heroicon-o-cog-6-tooth'),
            ]);
    }
}
