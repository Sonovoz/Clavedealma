<?php

namespace App\Modules\Inventory\Application;

use App\Modules\Inventory\Contracts\StockMovementReason;
use App\Modules\Inventory\Infrastructure\Models\ProductStock;
use App\Modules\Inventory\Infrastructure\Models\StockMovement;
use App\Modules\Inventory\Infrastructure\ProductsOfVariants;
use App\Modules\Returns\Contracts\Events\ReturnReceived;
use Illuminate\Support\Facades\DB;

/**
 * Piezas devueltas en buen estado → vuelven al inventario de los productos que lo
 * gestionan. (Las de productos sin límite quedan fuera del sistema.)
 */
class RestockReturn
{
    public function __construct(private readonly ProductsOfVariants $products) {}

    public function handle(ReturnReceived $event): void
    {
        if (! $event->restock) {
            return;
        }

        foreach ($this->products->units($event->lines) as $productId => $units) {
            DB::transaction(function () use ($event, $productId, $units): void {
                $note = 'Devolución '.$event->reference;
                $stock = ProductStock::query()->where('product_id', $productId)->lockForUpdate()->first();
                if ($stock === null || ! $stock->managed
                    || StockMovement::query()->where('product_id', $productId)->where('note', $note)->exists()) {
                    return;
                }

                $stock->quantity += $units;
                $stock->save();
                StockMovement::query()->create([
                    'product_id' => $productId,
                    'delta' => $units,
                    'quantity_after' => $stock->quantity,
                    'reason' => StockMovementReason::Return,
                    'note' => $note,
                ]);
            });
        }
    }
}
