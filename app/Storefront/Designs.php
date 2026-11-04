<?php

namespace App\Storefront;

/**
 * Registro de diseños de tienda (config/storefront.php).
 */
final class Designs
{
    /**
     * @return array<string, string> clave => nombre
     */
    public static function options(): array
    {
        return collect((array) config('storefront.designs'))->map(fn (array $d): string => (string) $d['label'])->all();
    }

    public static function resolve(?string $key): string
    {
        return array_key_exists((string) $key, (array) config('storefront.designs')) ? (string) $key : 'base';
    }

    /**
     * @return list<string> entradas de Vite
     */
    public static function assets(string $key): array
    {
        return array_values((array) config('storefront.designs.'.self::resolve($key).'.assets'));
    }

    /** Vista Blade del diseño para una página; si el diseño no la tiene, la de la plantilla base. */
    public static function view(string $key, string $page): string
    {
        $view = 'storefront.designs.'.self::resolve($key).'.'.$page;

        return view()->exists($view) ? $view : 'storefront.designs.base.'.$page;
    }
}
