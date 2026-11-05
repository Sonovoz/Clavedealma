<?php

namespace App\Modules\Inventory\Contracts;

use App\Support\Contracts\HasLabel;

/**
 * «Estado del inventario» de un producto, como en WooCommerce (ADR 0022).
 * Clave de Alma fabrica bajo pedido: «Hay existencias» es lo normal.
 */
enum StockStatus: string implements HasLabel
{
    case InStock = 'in_stock';
    case OutOfStock = 'out_of_stock';

    public function label(): string
    {
        return match ($this) {
            self::InStock => 'Hay existencias',
            self::OutOfStock => 'Agotado',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::InStock => 'Se vende y se fabrica bajo pedido.',
            self::OutOfStock => 'No se vende por ahora: la ficha se ve, pero no se puede añadir al carrito.',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::InStock => 'success',
            self::OutOfStock => 'danger',
        };
    }
}
