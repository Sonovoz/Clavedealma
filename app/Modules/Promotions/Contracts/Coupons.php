<?php

namespace App\Modules\Promotions\Contracts;

interface Coupons
{
    /**
     * Comprueba un código y calcula su descuento sobre las líneas del carrito.
     * Con $email se comprueba también el límite de usos por cliente.
     *
     * @param  list<CouponLine>  $lines
     */
    public function evaluate(string $code, array $lines, ?string $email = null): CouponResult;
}
