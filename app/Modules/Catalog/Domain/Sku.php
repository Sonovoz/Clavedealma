<?php

namespace App\Modules\Catalog\Domain;

use Illuminate\Support\Str;

/**
 * Referencia única de una variante: TIENDA-PRODUCTO-TALLA-COLOR.
 */
final class Sku
{
    public static function suggest(string $store, string $product, ?string $size, ?string $color): string
    {
        $parts = array_filter([$store, $product, $size, $color], fn (?string $part): bool => filled($part));
        $slug = Str::slug(implode('-', $parts));

        return Str::upper(Str::limit($slug, 60, ''));
    }
}
