<?php

namespace App\Modules\Promotions\Contracts;

/** Una línea del carrito tal como la necesita un cupón (importes con IVA, en céntimos). */
final readonly class CouponLine
{
    public function __construct(
        /** Clave de la línea (el id de la variante). */
        public int $key,
        public int $storeId,
        public int $totalCents,
        /** Rebajada ahora (precio por debajo del de referencia de 30 días). */
        public bool $onPromotion = false,
    ) {}
}
