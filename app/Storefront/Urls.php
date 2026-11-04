<?php

namespace App\Storefront;

use App\Modules\Stores\Infrastructure\Models\Store;

/**
 * Enlaces del escaparate. Según STORE_URL_MODE apuntan al subdominio de la tienda
 * (farruquito.clavedealma.es) o a la ruta /tienda/farruquito.
 */
final class Urls
{
    /**
     * @param  array<string, mixed>  $parameters
     */
    public static function to(Store|string $store, string $page = 'home', array $parameters = []): string
    {
        $subdomain = $store instanceof Store ? $store->subdomain : $store;
        $prefix = config('storefront.url_mode') === 'subdomain' ? 'storefront.subdomain.' : 'storefront.path.';

        return route($prefix.$page, ['store' => $subdomain] + $parameters);
    }

    public static function home(Store|string $store): string
    {
        return self::to($store);
    }

    public static function collection(Store|string $store, string $slug): string
    {
        return self::to($store, 'collection', ['collection' => $slug]);
    }

    public static function product(Store|string $store, string $slug): string
    {
        return self::to($store, 'product', ['product' => $slug]);
    }

    /** Imagen propia de un diseño (portadas, logotipos…), servida con control de visibilidad. */
    public static function designAsset(string $design, string $file): string
    {
        return route('storefront.design-asset', ['design' => $design, 'file' => $file]);
    }
}
