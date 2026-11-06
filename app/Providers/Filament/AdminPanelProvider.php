<?php

namespace App\Providers\Filament;

use App\Filament\Pages\Dashboard;
use App\Filament\Resources\BaseGarments\Pages\CreateBaseGarment;
use App\Filament\Resources\BaseGarments\Pages\EditBaseGarment;
use App\Filament\Resources\BaseGarments\Pages\ListBaseGarments;
use App\Filament\Resources\Collections\Pages\CreateCollection;
use App\Filament\Resources\Collections\Pages\EditCollection;
use App\Filament\Resources\Collections\Pages\ListCollections;
use App\Filament\Resources\Products\Pages\CreateProduct;
use App\Filament\Resources\Products\Pages\EditProduct;
use App\Filament\Resources\Products\Pages\ListProducts;
use App\Filament\Support\InitialsAvatarProvider;
use App\Http\Middleware\EnsureTwoFactorForStaff;
use Filament\Auth\MultiFactor\App\AppAuthentication;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
use Filament\Widgets\AccountWidget;
use Illuminate\Contracts\View\View;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Blade;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    /** Páginas del catálogo, que se trabajan siempre dentro de la tienda activa. */
    private const CATALOG_PAGES = [
        ListProducts::class, CreateProduct::class, EditProduct::class,
        ListCollections::class, CreateCollection::class, EditCollection::class,
        ListBaseGarments::class, CreateBaseGarment::class, EditBaseGarment::class,
    ];

    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            // «Mi perfil»: nombre, contraseña y verificación en dos pasos.
            ->profile(isSimple: false)
            // Verificación en dos pasos con app autenticadora y códigos de recuperación (ADR 0028).
            // Filament la exige a todos; EnsureTwoFactorForStaff solo al equipo (User::mustUseTwoFactor).
            ->multiFactorAuthentication(
                [AppAuthentication::make()->recoverable()->brandName('Clave de Alma')],
                isRequired: true,
            )
            ->multiFactorAuthenticationRequiredMiddlewareName(EnsureTwoFactorForStaff::class)
            // Si a una política le falta una acción, error en lugar de permitirla.
            ->strictAuthorization()
            ->defaultAvatarProvider(InitialsAvatarProvider::class)
            ->brandName('Clave de Alma')
            // La campana: avisos nuevos de los artistas para los administradores (ADR 0023).
            ->databaseNotifications()
            ->databaseNotificationsPolling('60s')
            // Menú en el orden de WooCommerce (WordPress), para que resulte familiar.
            ->navigationGroups(['Comercio', 'Productos', 'Análisis', 'Marketing', 'Tiendas', 'Ajustes', 'Herramientas'])
            // Barra de desplazamiento fina y discreta en el menú lateral.
            ->renderHook(PanelsRenderHook::HEAD_END, fn (): View => view('filament.panel-styles'))
            // Selector «Tienda: X»: el catálogo se trabaja siempre dentro de una tienda.
            ->renderHook(PanelsRenderHook::TOPBAR_START, fn (): string => Blade::render('@livewire(\'store-switcher\')'))
            // Y en cada página del catálogo, en grande: «Tienda: X».
            ->renderHook(PanelsRenderHook::PAGE_START, fn (): View => view('filament.active-store-banner'), scopes: self::CATALOG_PAGES)
            ->colors([
                'primary' => Color::Amber,
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
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
