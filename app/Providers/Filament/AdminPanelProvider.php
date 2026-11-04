<?php

namespace App\Providers\Filament;

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
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
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
            // Si a una política le falta una acción, error en lugar de permitirla.
            ->strictAuthorization()
            ->defaultAvatarProvider(InitialsAvatarProvider::class)
            ->brandName('Clave de Alma')
            ->navigationGroups(['Tiendas', 'Catálogo', 'Operación', 'Envíos', 'Administración'])
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
