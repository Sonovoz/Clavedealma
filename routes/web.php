<?php

use App\Http\Controllers\Storefront\DesignAssetController;
use App\Http\Controllers\Storefront\MarketController;
use App\Http\Controllers\Storefront\StorefrontController;
use Illuminate\Support\Facades\Route;

/*
| Tiendas. Las mismas páginas por subdominio (producción) y por ruta /tienda/<subdominio>
| (pruebas). El grupo de subdominio va primero para que su portada no la capture la matriz.
*/
$tienda = function (): void {
    Route::get('/', [StorefrontController::class, 'home'])->name('home');
    Route::get('/coleccion/{collection}', [StorefrontController::class, 'collection'])->name('collection');
    Route::get('/p/{product}', [StorefrontController::class, 'product'])->name('product');
};

Route::domain('{store}.'.config('clavedealma.domain'))
    ->where(['store' => '[a-z0-9-]+'])
    ->name('storefront.subdomain.')
    ->group($tienda);

Route::prefix('tienda/{store}')
    ->where(['store' => '[a-z0-9-]+'])
    ->name('storefront.path.')
    ->group($tienda);

Route::get('/_diseno/{design}/{file}', DesignAssetController::class)->name('storefront.design-asset');

// Matriz: portada del market.
Route::get('/', MarketController::class)->name('market');
