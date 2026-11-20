<?php

namespace App\Providers\Filament;

use App\Filament\Widgets\BillingOverview;
use App\Filament\Widgets\FlotaPorOrganizacion;
use App\Filament\Widgets\PlatformOverview;
use App\Filament\Widgets\UltimosVehiculos;
use BezhanSalleh\FilamentShield\FilamentShieldPlugin;
use Filament\Enums\ThemeMode;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Enums\Width;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->brandName('CarCheck')
            ->brandLogo(asset('brand/carcheck-mark.svg'))
            ->brandLogoHeight('2.25rem')
            ->favicon(asset('brand/carcheck-mark.svg'))
            ->font('Outfit')
            ->darkMode(true)
            ->defaultThemeMode(ThemeMode::Dark)
            ->spa()
            ->sidebarCollapsibleOnDesktop()
            ->maxContentWidth(Width::Full)
            ->colors([
                // Mismo azul profundo que la PWA y la landing.
                'primary' => [
                    50 => '#eaf1f7',
                    100 => '#d3e2ef',
                    200 => '#a8c5df',
                    300 => '#77a2c8',
                    400 => '#4a7ca9',
                    500 => '#2f5f8b',
                    600 => '#1d4a70',
                    700 => '#183e5d',
                    800 => '#14334c',
                    900 => '#0f283b',
                    950 => '#081723',
                ],
            ])
            ->navigationGroups([
                'Gestión',
                'Facturación',
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([
                PlatformOverview::class,
                BillingOverview::class,
                FlotaPorOrganizacion::class,
                UltimosVehiculos::class,
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
            ])
            ->plugins([
                FilamentShieldPlugin::make(),
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
