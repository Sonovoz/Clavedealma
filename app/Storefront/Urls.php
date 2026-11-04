<?php

namespace App\Storefront;

use App\Modules\Stores\Infrastructure\Models\Store;

/**
 * Enlaces del escaparate. La dirección principal de una tienda es clavedealma.es/<clave>
 * (ADR 0013); el subdominio y /tienda/<clave> solo redirigen aquí.
 */
final class Urls
{
    /**
     * @param  array<string, mixed>  $parameters
     */
    public static function to(Store|string $store, string $page = 'home', array $parameters = []): string
    {
        $key = $store instanceof Store ? $store->subdomain : $store;

        return rtrim((string) config('app.url'), '/').route('storefront.'.$page, ['store' => $key] + $parameters, false);
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

    /** Texto legal de una tienda: clavedealma.es/<clave>/legal/<página>. */
    public static function legal(Store|string $store, string $page): string
    {
        return self::to($store, 'legal', ['page' => $page]);
    }

    /** Dirección principal tal y como se enseña en el panel: clavedealma.es/<clave>. */
    public static function label(Store|string $store): string
    {
        $key = $store instanceof Store ? $store->subdomain : $store;

        return config('clavedealma.domain').'/'.$key;
    }

    /** Imagen propia de un diseño (portadas, logotipos…), servida con control de visibilidad. */
    public static function designAsset(string $design, string $file): string
    {
        return route('storefront.design-asset', ['design' => $design, 'file' => $file]);
    }
}
