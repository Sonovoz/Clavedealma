<?php

namespace App\Modules\Inventory\Infrastructure\Listeners;

use App\Modules\Catalog\Contracts\Events\VariantCreated;
use App\Modules\Inventory\Contracts\StockMode;
use App\Modules\Inventory\Infrastructure\Models\StockLevel;

/**
 * Toda variante nueva nace "bajo pedido". El fabricante cambia el modo si hace falta.
 */
class CreateStockLevelForVariant
{
    public function handle(VariantCreated $event): void
    {
        StockLevel::query()->firstOrCreate(
            ['product_variant_id' => $event->variantId],
            ['mode' => StockMode::OnDemand, 'quantity' => 0],
        );
    }
}
