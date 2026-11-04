<?php

namespace App\Modules\Inventory\Contracts;

interface StockAvailability
{
    /**
     * Unidades que se pueden vender de cada variante ahora mismo:
     * null = sin límite (se fabrica bajo pedido); 0 = agotada.
     *
     * @param  list<int>  $variantIds
     * @return array<int, ?int>
     */
    public function maxSellable(array $variantIds): array;
}
