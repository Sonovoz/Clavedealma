<?php

namespace App\Http\Controllers\Storefront;

use App\Modules\Stores\Contracts\StoreStatus;
use App\Modules\Stores\Infrastructure\Models\Store;
use App\Storefront\Designs;
use App\Storefront\StoreAccess;
use App\Storefront\StoreCatalog;
use App\Storefront\Urls;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * Matriz de Clave de Alma (portada del market). Su sección «Tiendas oficiales» se
 * rellena con las tiendas publicadas; el equipo ve también las ocultas, marcadas.
 */
class MarketController
{
    public function __invoke(Request $request, StoreCatalog $catalog): View
    {
        $staff = StoreAccess::isStaff($request->user());

        $stores = Store::query()
            ->when(! $staff, fn ($q) => $q->where('status', StoreStatus::Published))
            ->orderByRaw('CASE WHEN status = ? THEN 0 ELSE 1 END', [StoreStatus::Published->value])
            ->orderBy('published_at')
            ->orderBy('name')
            ->get()
            ->map(function (Store $store) use ($catalog): array {
                $design = Designs::resolve($store->design);
                $collections = $catalog->collections($store)->pluck('name')->all();

                return [
                    'name' => $store->name,
                    'subdomain' => $store->subdomain,
                    'url' => Urls::home($store),
                    // Foto de la tarjeta: resources/designs/<diseño>/tarjeta.webp, si el diseño la tiene.
                    'image' => is_file(resource_path("designs/{$design}/tarjeta.webp")) ? Urls::designAsset($design, 'tarjeta.webp') : null,
                    'ficha' => $collections === [] ? ($store->tagline ?? 'Tienda oficial') : 'Tienda oficial · '.implode(' · ', $collections),
                    'genres' => implode(' ', (array) ($store->theme['genres'] ?? [])),
                    'hidden' => $store->status !== StoreStatus::Published,
                    'status' => $store->status->label(),
                ];
            })
            ->all();

        return view('storefront.market', [
            'stores' => $stores,
            'staff' => $staff,
            'domain' => (string) config('clavedealma.domain'),
        ]);
    }
}
