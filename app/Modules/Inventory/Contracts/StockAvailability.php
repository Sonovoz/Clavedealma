<?php

namespace App\Modules\Inventory\Contracts;

interface StockAvailability
{
    /**
     * Unidades que se pueden vender de cada producto ahora mismo (sumando todas sus
     * tallas y colores): null = sin límite (se fabrica bajo pedido); 0 = agotado.
     *
     * @param  list<int>  $productIds
     * @return array<int, ?int>
     */
    public function productLimits(array $productIds): array;
}
