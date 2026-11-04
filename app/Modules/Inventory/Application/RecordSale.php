<?php

namespace App\Modules\Inventory\Application;

use App\Modules\Inventory\Contracts\Events\StockShortage;
use App\Modules\Inventory\Contracts\StockMovementReason;
use App\Modules\Inventory\Domain\StockPolicy;
use App\Modules\Inventory\Infrastructure\Models\StockLevel;
use App\Modules\Inventory\Infrastructure\Models\StockMovement;
use App\Modules\Orders\Contracts\Events\OrderPaid;
use Illuminate\Support\Facades\DB;

/**
 * Pedido pagado → salen del stock las unidades ya fabricadas. Lo que no hay se fabrica
 * bajo pedido; si la variante «se agota» y faltan unidades, avisa (StockShortage).
 */
class RecordSale
{
    public function handle(OrderPaid $event): void
    {
        foreach ($event->lines as $line) {
            $missing = DB::transaction(function () use ($event, $line): int {
                $level = StockLevel::query()->where('product_variant_id', $line['variant_id'])->lockForUpdate()->first();
                if ($level === null || ! $level->mode->tracksQuantity()) {
                    return 0;
                }

                $taken = StockPolicy::fromStock($level->mode, $level->quantity, $line['quantity']);
                if ($taken > 0) {
                    $level->quantity -= $taken;
                    $level->save();
                    StockMovement::create([
                        'product_variant_id' => $line['variant_id'],
                        'delta' => -$taken,
                        'quantity_after' => $level->quantity,
                        'reason' => StockMovementReason::Sale,
                        'note' => 'Pedido '.$event->reference,
                    ]);
                }

                return StockPolicy::canSell($level->mode, $taken, $line['quantity']) ? 0 : $line['quantity'] - $taken;
            });

            if ($missing > 0) {
                StockShortage::dispatch($event->orderId, $line['variant_id'], $missing);
            }
        }
    }
}
