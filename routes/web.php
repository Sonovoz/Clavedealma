<?php

use App\Http\Controllers\Storefront\DesignAssetController;
use App\Http\Controllers\Storefront\MarketController;
use App\Http\Controllers\Storefront\SitemapController;
use App\Http\Controllers\Storefront\StorefrontController;
use App\Http\Controllers\Storefront\StoreRedirectController;
use App\Modules\Stores\Domain\Subdomain;
use Illuminate\Support\Facades\Route;

/*
| Dirección principal de cada tienda: clavedealma.es/<clave> (ADR 0013).
| <clave>.clavedealma.es y /tienda/<clave> redirigen (301) a ella, conservando la página.
| El patrón de la clave excluye las palabras reservadas (admin, livewire, carrito…).
*/
$key = Subdomain::routePattern();

Route::domain('{store}.'.config('clavedealma.domain'))
    ->where(['store' => $key])
    ->group(function (): void {
        Route::get('/{path?}', StoreRedirectController::class)->where('path', '.*')->name('storefront.redirect.subdomain');
    });

Route::get('/tienda/{store}/{path?}', StoreRedirectController::class)
    ->where(['store' => $key, 'path' => '.*'])
    ->name('storefront.redirect.path');

Route::get('/_diseno/{design}/{file}', DesignAssetController::class)->name('storefront.design-asset');

// SEO: mapa del sitio y robots (docs/seo).
Route::get('/sitemap.xml', [SitemapController::class, 'sitemap'])->name('seo.sitemap');
Route::get('/robots.txt', [SitemapController::class, 'robots'])->name('seo.robots');

// Matriz: portada del market.
Route::get('/', MarketController::class)->name('market');

// Tiendas (siempre al final: capturan /<clave>).
Route::prefix('{store}')
    ->where(['store' => $key])
    ->name('storefront.')
    ->group(function (): void {
        Route::get('/', [StorefrontController::class, 'home'])->name('home');
        Route::get('/coleccion/{collection}', [StorefrontController::class, 'collection'])->name('collection');
        Route::get('/p/{product}', [StorefrontController::class, 'product'])->name('product');
    });
