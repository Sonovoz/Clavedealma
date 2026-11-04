<?php

namespace App\Modules\Inventory\Application;

use App\Modules\Inventory\Contracts\StockMovementReason;
use App\Modules\Inventory\Infrastructure\Models\StockLevel;
use App\Modules\Inventory\Infrastructure\Models\StockMovement;
use App\Modules\Returns\Contracts\Events\ReturnReceived;
use Illuminate\Support\Facades\DB;

/**
 * Piezas devueltas en buen estado → vuelven al stock de las variantes que lo llevan.
 * (Las «bajo pedido» no tienen stock: la pieza queda fuera del sistema.)
 */
class RestockReturn
{
    public function handle(ReturnReceived $event): void
    {
        if (! $event->restock) {
            return;
        }
        foreach ($event->lines as $line) {
            DB::transaction(function () use ($event, $line): void {
                $note = 'Devolución '.$event->reference;
                $level = StockLevel::query()->where('product_variant_id', $line['variant_id'])->lockForUpdate()->first();
                if ($level === null || ! $level->mode->tracksQuantity()
                    || StockMovement::query()->where('product_variant_id', $line['variant_id'])->where('note', $note)->exists()) {
                    return;
                }
                $level->quantity += $line['quantity'];
                $level->save();
                StockMovement::create([
                    'product_variant_id' => $line['variant_id'],
                    'delta' => $line['quantity'],
                    'quantity_after' => $level->quantity,
                    'reason' => StockMovementReason::Return,
                    'note' => $note,
                ]);
            });
        }
    }
}
