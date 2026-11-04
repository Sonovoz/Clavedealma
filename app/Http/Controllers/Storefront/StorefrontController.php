<?php

namespace App\Http\Controllers\Storefront;

use App\Modules\Catalog\Infrastructure\Models\Collection;
use App\Modules\Stores\Infrastructure\Models\Store;
use App\Storefront\Designs;
use App\Storefront\Seo\SeoBuilder;
use App\Storefront\StoreAccess;
use App\Storefront\StoreCatalog;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * Páginas de una tienda: portada (diseño propio + pasarela), colección y ficha de producto.
 * Funciona igual por subdominio (farruquito.clavedealma.es) que por ruta (/tienda/farruquito).
 */
class StorefrontController
{
    public function __construct(private readonly StoreCatalog $catalog, private readonly SeoBuilder $seo) {}

    public function home(Request $request, string $store): View
    {
        $shop = $this->store($request, $store);

        $cards = $this->catalog->cards($shop);

        return view(Designs::view($shop->design, 'home'), $this->shared($request, $shop) + [
            'cards' => $cards,
            'activeCollection' => null,
            'seo' => $this->seo->store($shop, $cards),
        ]);
    }

    public function collection(Request $request, string $store, string $collection): View
    {
        $shop = $this->store($request, $store);
        $current = Collection::query()->where('store_id', $shop->id)->where('slug', $collection)->first();
        abort_if($current === null, 404);

        $cards = $this->catalog->cards($shop, $current);

        return view(Designs::view($shop->design, 'collection'), $this->shared($request, $shop) + [
            'cards' => $cards,
            'activeCollection' => $current,
            'seo' => $this->seo->collection($shop, $current, $cards),
        ]);
    }

    public function product(Request $request, string $store, string $product): View
    {
        $shop = $this->store($request, $store);
        $item = $this->catalog->product($shop, $product, StoreAccess::isStaff($request->user()));
        abort_if($item === null, 404);

        return view(Designs::view($shop->design, 'product'), $this->shared($request, $shop) + [
            'item' => $item,
            'seo' => $this->seo->product($shop, $item),
        ]);
    }

    private function store(Request $request, string $subdomain): Store
    {
        $store = Store::query()->where('subdomain', strtolower($subdomain))->first();
        abort_if($store === null || ! StoreAccess::canView($store, $request->user()), 404);

        return $store;
    }

    /**
     * @return array<string, mixed>
     */
    private function shared(Request $request, Store $store): array
    {
        return [
            'store' => $store,
            'design' => Designs::resolve($store->design),
            'assets' => Designs::assets($store->design),
            'collections' => $this->catalog->collections($store),
            'preview' => StoreAccess::isPreview($store),
            'staff' => StoreAccess::isStaff($request->user()),
        ];
    }
}
