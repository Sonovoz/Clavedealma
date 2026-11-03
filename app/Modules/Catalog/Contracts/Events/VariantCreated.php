<?php

namespace App\Modules\Catalog\Contracts\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Se ha creado una variante (talla/color) de un producto.
 * Lo escucha Inventario para crear su ficha de stock.
 */
final class VariantCreated implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(public readonly int $variantId) {}
}
