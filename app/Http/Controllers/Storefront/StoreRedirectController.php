<?php

namespace App\Http\Controllers\Storefront;

use App\Storefront\Urls;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * <clave>.clavedealma.es/… y clavedealma.es/tienda/<clave>/… → clavedealma.es/<clave>/…
 * Redirección permanente: Google suma toda la autoridad en una sola dirección (ADR 0013).
 */
class StoreRedirectController
{
    public function __invoke(Request $request, string $store, ?string $path = null): RedirectResponse
    {
        $target = rtrim(Urls::home(strtolower($store)), '/');
        $path = trim((string) $path, '/');
        $query = $request->getQueryString();

        return redirect()->to($target.($path === '' ? '' : '/'.$path).($query ? '?'.$query : ''), 301);
    }
}
