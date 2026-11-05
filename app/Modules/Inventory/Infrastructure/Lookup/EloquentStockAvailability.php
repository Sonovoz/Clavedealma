<?php

namespace App\Modules\Inventory\Infrastructure\Lookup;

use App\Modules\Inventory\Contracts\StockAvailability;
use App\Modules\Inventory\Infrastructure\Models\ProductStock;

class EloquentStockAvailability implements StockAvailability
{
    public function productLimits(array $productIds): array
    {
        $stocks = ProductStock::query()->whereIn('product_id', $productIds)->get()->keyBy('product_id');

        $result = [];
        foreach ($productIds as $id) {
            // Sin fila de inventario = hay existencias y se fabrica bajo pedido.
            $result[$id] = $stocks->get($id)?->limit();
        }

        return $result;
    }
}
