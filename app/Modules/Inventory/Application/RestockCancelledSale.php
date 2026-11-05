<?php

namespace App\Modules\Inventory\Application;

use App\Modules\Inventory\Contracts\StockMovementReason;
use App\Modules\Inventory\Infrastructure\Models\ProductStock;
use App\Modules\Inventory\Infrastructure\Models\StockMovement;
use App\Modules\Orders\Contracts\Events\PaidOrderCancelled;
use Illuminate\Support\Facades\DB;

/**
 * Pedido pagado cancelado → vuelven al inventario exactamente las unidades que se
 * descontaron al pagarlo. Solo una vez.
 */
class RestockCancelledSale
{
    public function handle(PaidOrderCancelled $event): void
    {
        $sold = StockMovement::query()
            ->where('reason', StockMovementReason::Sale)
            ->where('note', 'Pedido '.$event->reference)
            ->selectRaw('product_id, SUM(delta) as total')
            ->groupBy('product_id')
            ->pluck('total', 'product_id');

        foreach ($sold as $productId => $total) {
            $units = -(int) $total;
            if ($units < 1) {
                continue;
            }

            DB::transaction(function () use ($event, $productId, $units): void {
                $note = 'Cancelación pedido '.$event->reference;
                if (StockMovement::query()->where('product_id', $productId)->where('note', $note)->exists()) {
                    return;
                }
                $stock = ProductStock::query()->where('product_id', $productId)->lockForUpdate()->first();
                if ($stock === null) {
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
