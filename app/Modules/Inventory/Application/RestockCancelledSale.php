<?php

namespace App\Modules\Inventory\Application;

use App\Modules\Inventory\Contracts\StockMovementReason;
use App\Modules\Inventory\Infrastructure\Models\StockLevel;
use App\Modules\Inventory\Infrastructure\Models\StockMovement;
use App\Modules\Orders\Contracts\Events\PaidOrderCancelled;
use Illuminate\Support\Facades\DB;

/**
 * Pedido pagado cancelado → vuelven al stock exactamente las unidades que salieron de él
 * al pagarlo (las que se iban a fabricar bajo pedido no existen). Solo una vez.
 */
class RestockCancelledSale
{
    public function handle(PaidOrderCancelled $event): void
    {
        $sold = StockMovement::query()
            ->where('reason', StockMovementReason::Sale)
            ->where('note', 'Pedido '.$event->reference)
            ->selectRaw('product_variant_id, SUM(delta) as total')
            ->groupBy('product_variant_id')
            ->pluck('total', 'product_variant_id');

        foreach ($sold as $variantId => $total) {
            $units = -(int) $total;
            if ($units < 1) {
                continue;
            }
            DB::transaction(function () use ($event, $variantId, $units): void {
                $note = 'Cancelación pedido '.$event->reference;
                if (StockMovement::query()->where('product_variant_id', $variantId)->where('note', $note)->exists()) {
                    return;
                }
                $level = StockLevel::query()->where('product_variant_id', $variantId)->lockForUpdate()->first();
                if ($level === null) {
                    return;
                }
                $level->quantity += $units;
                $level->save();
                StockMovement::create([
                    'product_variant_id' => $variantId,
                    'delta' => $units,
                    'quantity_after' => $level->quantity,
                    'reason' => StockMovementReason::Return,
                    'note' => $note,
                ]);
            });
        }
    }
}
