<?php

namespace App\Providers\Filament;

use App\Filament\Auth\EditProfile;
use App\Filament\AvatarProviders\InitialsAvatarProvider;
use App\Support\ClubLogo;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Widgets\AccountWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
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
            ->profile(EditProfile::class, isSimple: false)
            ->brandName('Bowls Buddy')
            ->brandLogo(fn () => ClubLogo::url())
            ->brandLogoHeight('2.25rem')
            ->favicon(fn () => ClubLogo::url())
            ->defaultAvatarProvider(InitialsAvatarProvider::class)
            ->colors([
                // A dark grass green, anchored so buttons (600) match the member pages' brand.
                'primary' => [
                    50 => '#f1f7f1',
                    100 => '#dcebdd',
                    200 => '#bcd7bd',
                    300 => '#93bd96',
                    400 => '#62996a',
                    500 => '#2f7a38',
                    600 => '#1b5e20',
                    700 => '#164e1c',
                    800 => '#124117',
                    900 => '#0e3413',
                    950 => '#072408',
                ],
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([
                AccountWidget::class,
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
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
