<?php

use App\Http\Controllers\Account\AccountAuthController;
use App\Http\Controllers\Account\AccountController;
use App\Http\Controllers\Account\AccountProfileController;
use App\Http\Controllers\Checkout\CartController;
use App\Http\Controllers\Checkout\CheckoutController;
use App\Http\Controllers\Checkout\PaymentWebhookController;
use App\Http\Controllers\Checkout\ReturnController;
use App\Http\Controllers\Panel\ArtistNoticeFileController;
use App\Http\Controllers\Storefront\DesignAssetController;
use App\Http\Controllers\Storefront\LegalController;
use App\Http\Controllers\Storefront\MarketController;
use App\Http\Controllers\Storefront\SitemapController;
use App\Http\Controllers\Storefront\StorefrontController;
use App\Http\Controllers\Storefront\StoreRedirectController;
use App\Modules\Stores\Domain\Subdomain;
use App\Platform\Legal\LegalPage;
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

// Archivos privados de los avisos de artistas (ADR 0023): solo el artista y el superadmin.
Route::get('/avisos-artistas/{notice}/archivo/{index}', ArtistNoticeFileController::class)
    ->whereNumber(['notice', 'index'])->middleware('auth')->name('artist-notices.file');

// «Mi cuenta» como WooCommerce (ADR 0021): cuenta opcional del cliente. Nunca se indexa.
Route::prefix('mi-cuenta')->name('account.')->group(function (): void {
    Route::get('/', [AccountController::class, 'show'])->name('show');
    Route::middleware('guest')->group(function (): void {
        Route::post('/acceder', [AccountAuthController::class, 'login'])->middleware('throttle:10,1')->name('login');
        Route::post('/registro', [AccountAuthController::class, 'register'])->middleware('throttle:5,1')->name('register');
        Route::get('/recuperar-contrasena', [AccountAuthController::class, 'lostPassword'])->name('password.request');
        Route::post('/recuperar-contrasena', [AccountAuthController::class, 'sendResetLink'])->middleware('throttle:5,1')->name('password.email');
        Route::get('/restablecer-contrasena/{token}', [AccountAuthController::class, 'resetForm'])->name('password.reset');
        Route::post('/restablecer-contrasena', [AccountAuthController::class, 'reset'])->middleware('throttle:10,1')->name('password.update');
    });
    Route::middleware('auth')->group(function (): void {
        Route::post('/salir', [AccountAuthController::class, 'logout'])->name('logout');
        Route::get('/pedidos', [AccountController::class, 'orders'])->name('orders');
        Route::get('/ver-pedido/{reference}', [AccountController::class, 'order'])->where('reference', '[A-Za-z0-9\-]{3,30}')->name('order');
        Route::get('/facturas', [AccountController::class, 'invoices'])->name('invoices');
        Route::get('/devoluciones', [AccountController::class, 'returns'])->name('returns');
        Route::get('/direcciones', [AccountProfileController::class, 'addresses'])->name('addresses');
        Route::get('/editar-direccion', [AccountProfileController::class, 'editAddress'])->name('address.edit');
        Route::post('/editar-direccion', [AccountProfileController::class, 'saveAddress'])->name('address.save');
        Route::get('/editar-cuenta', [AccountProfileController::class, 'editDetails'])->name('details');
        Route::post('/editar-cuenta', [AccountProfileController::class, 'saveDetails'])->middleware('throttle:10,1')->name('details.save');
        Route::post('/verificar', [AccountAuthController::class, 'resendVerification'])->middleware('throttle:3,1')->name('verify.send');
    });
});
// Nombre que usa Laravel para el enlace del email de confirmación.
Route::get('/mi-cuenta/verificar/{id}/{hash}', [AccountAuthController::class, 'verify'])->middleware(['auth', 'signed', 'throttle:6,1'])->name('verification.verify');

// Compra: una cesta y un pago para todas las tiendas (docs/compra). Nunca se indexa.
Route::get('/carrito', [CartController::class, 'show'])->name('cart.show');
Route::post('/carrito', [CartController::class, 'add'])->middleware('throttle:60,1')->name('cart.add');
Route::post('/carrito/actualizar', [CartController::class, 'update'])->middleware('throttle:60,1')->name('cart.update');
// «Finalizar compra» como WooCommerce: datos, «Tu pedido» y pago en una sola página.
Route::get('/finalizar-compra', [CheckoutController::class, 'show'])->name('checkout.show');
Route::post('/finalizar-compra', [CheckoutController::class, 'place'])->middleware('throttle:10,1')->name('checkout.place');
Route::redirect('/pedido', '/finalizar-compra', 301);
Route::get('/pedido/envio', [CheckoutController::class, 'quote'])->middleware('throttle:60,1')->name('checkout.quote');
Route::get('/pedido/{token}/pagar', [CheckoutController::class, 'pay'])->where('token', '[A-Za-z0-9]{48}')->name('checkout.pay');
Route::post('/pedido/{token}/simular', [CheckoutController::class, 'simulate'])->where('token', '[A-Za-z0-9]{48}')->middleware('throttle:20,1')->name('checkout.simulate');
Route::get('/pedido/{token}/devolucion', [ReturnController::class, 'show'])->where('token', '[A-Za-z0-9]{48}')->name('returns.create');
Route::post('/pedido/{token}/devolucion', [ReturnController::class, 'store'])->where('token', '[A-Za-z0-9]{48}')->middleware('throttle:10,1')->name('returns.store');
Route::get('/pedido/{token}/factura/{code}', [CheckoutController::class, 'invoice'])->where(['token' => '[A-Za-z0-9]{48}', 'code' => '[A-Za-z0-9\-]{3,30}'])->name('checkout.invoice');
Route::get('/pedido/{token}', [CheckoutController::class, 'thanks'])->where('token', '[A-Za-z0-9]{48}')->name('checkout.thanks');
Route::post('/webhooks/stripe', PaymentWebhookController::class)->name('payments.webhook.stripe');

// Textos legales de Clave de Alma; los de cada tienda en /<tienda>/legal/… (ADR 0020).
$legalPages = array_map(fn (LegalPage $p): string => $p->value, LegalPage::cases());
Route::get('/legal/{page}', [LegalController::class, 'market'])->whereIn('page', $legalPages)->name('legal');

// Matriz: portada del market.
Route::get('/', MarketController::class)->name('market');

// Tiendas (siempre al final: capturan /<clave>).
Route::prefix('{store}')
    ->where(['store' => $key])
    ->name('storefront.')
    ->group(function () use ($legalPages): void {
        Route::get('/', [StorefrontController::class, 'home'])->name('home');
        Route::get('/coleccion/{collection}', [StorefrontController::class, 'collection'])->name('collection');
        Route::get('/p/{product}', [StorefrontController::class, 'product'])->name('product');
        Route::get('/legal/{page}', [LegalController::class, 'store'])->whereIn('page', $legalPages)->name('legal');
    });
