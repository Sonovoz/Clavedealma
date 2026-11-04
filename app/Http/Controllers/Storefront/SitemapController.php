<?php

namespace App\Http\Controllers\Storefront;

use App\Modules\Catalog\Contracts\ProductStatus;
use App\Modules\Catalog\Infrastructure\Models\Collection;
use App\Modules\Catalog\Infrastructure\Models\Product;
use App\Modules\Stores\Contracts\StoreStatus;
use App\Modules\Stores\Infrastructure\Models\Store;
use App\Storefront\Seo\SeoBuilder;
use App\Storefront\Urls;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

/**
 * sitemap.xml y robots.txt. Solo entran tiendas publicadas y artículos publicados;
 * fuera de producción robots.txt cierra todo (docs/seo).
 */
class SitemapController
{
    public function sitemap(): Response
    {
        $urls = [['loc' => route('market'), 'lastmod' => null, 'images' => []]];

        $stores = Store::query()->where('status', StoreStatus::Published)->get();
        foreach ($stores as $store) {
            $products = Product::query()
                ->where('store_id', $store->id)
                ->where('status', ProductStatus::Published)
                ->with('images')
                ->get();

            $urls[] = ['loc' => Urls::home($store), 'lastmod' => $products->max('updated_at') ?? $store->updated_at, 'images' => []];

            $collections = Collection::query()->where('store_id', $store->id)
                ->whereHas('products', fn ($q) => $q->where('status', ProductStatus::Published))
                ->orderBy('position')->get();
            foreach ($collections as $collection) {
                $urls[] = ['loc' => Urls::collection($store, $collection->slug), 'lastmod' => $collection->updated_at, 'images' => []];
            }

            foreach ($products as $product) {
                $urls[] = [
                    'loc' => Urls::product($store, $product->slug),
                    'lastmod' => $product->updated_at,
                    'images' => $product->images->where('is_swatch', false)->take(10)->map(fn ($i): string => Storage::disk('public')->url($i->path))->values()->all(),
                ];
            }
        }

        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n"
            .'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">'."\n";
        foreach ($urls as $url) {
            $xml .= '  <url><loc>'.e($url['loc']).'</loc>';
            if ($url['lastmod'] !== null) {
                $xml .= '<lastmod>'.$url['lastmod']->toAtomString().'</lastmod>';
            }
            foreach ($url['images'] as $image) {
                $xml .= '<image:image><image:loc>'.e($image).'</image:loc></image:image>';
            }
            $xml .= "</url>\n";
        }
        $xml .= '</urlset>'."\n";

        return response($xml, 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }

    public function robots(): Response
    {
        $lines = SeoBuilder::indexing()
            ? ['User-agent: *', 'Disallow: /admin', 'Disallow: /livewire', 'Disallow: /carrito', 'Disallow: /pedido', 'Disallow: /webhooks', 'Allow: /', '', 'Sitemap: '.route('seo.sitemap')]
            : ['# Servidor de pruebas: no indexar', 'User-agent: *', 'Disallow: /'];

        return response(implode("\n", $lines)."\n", 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }
}
