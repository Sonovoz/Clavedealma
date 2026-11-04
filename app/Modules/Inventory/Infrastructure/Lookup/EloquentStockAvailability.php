<?php

namespace App\Modules\Inventory\Infrastructure\Lookup;

use App\Modules\Inventory\Contracts\StockAvailability;
use App\Modules\Inventory\Contracts\StockMode;
use App\Modules\Inventory\Infrastructure\Models\StockLevel;

class EloquentStockAvailability implements StockAvailability
{
    public function maxSellable(array $variantIds): array
    {
        $levels = StockLevel::query()->whereIn('product_variant_id', $variantIds)->get()->keyBy('product_variant_id');

        $result = [];
        foreach ($variantIds as $id) {
            $level = $levels->get($id);
            // Sin ficha de stock = bajo pedido. Solo «se agota» pone un límite a la venta.
            $result[$id] = $level !== null && $level->mode === StockMode::LimitedThenSoldOut ? max(0, $level->quantity) : null;
        }

        return $result;
    }
}
