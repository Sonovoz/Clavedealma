<?php

namespace App\Modules\Promotions\Contracts;

use App\Support\Contracts\HasLabel;

/** «Tipo de descuento» de un cupón, con los nombres de WooCommerce (ADR 0024). */
enum CouponType: string implements HasLabel
{
    /** Importe en puntos básicos: 1500 = 15 %. */
    case Percent = 'percent';
    /** Importe en céntimos, sobre el carrito. */
    case FixedCart = 'fixed_cart';

    public function label(): string
    {
        return match ($this) {
            self::Percent => 'Descuento porcentual',
            self::FixedCart => 'Descuento fijo en el carrito',
        };
    }
}
