<?php

namespace App\Modules\Inventory\Application;

use App\Modules\Inventory\Contracts\StockMovementReason;
use App\Modules\Inventory\Infrastructure\Models\ProductStock;
use App\Modules\Inventory\Infrastructure\Models\StockMovement;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Suma o resta unidades al inventario de un producto y deja constancia del movimiento.
 */
class AdjustStock
{
    public function __invoke(int $productId, int $delta, StockMovementReason $reason, ?string $note = null, ?int $userId = null): ProductStock
    {
        if ($delta === 0) {
            throw new InvalidArgumentException('El ajuste no puede ser cero.');
        }

        return DB::transaction(function () use ($productId, $delta, $reason, $note, $userId): ProductStock {
            ProductStock::query()->firstOrCreate(['product_id' => $productId]);
            $stock = ProductStock::query()->where('product_id', $productId)->lockForUpdate()->firstOrFail();
            if ($stock->quantity + $delta < 0) {
                throw new InvalidArgumentException("No hay unidades suficientes: quedan {$stock->quantity}.");
            }

            $stock->quantity += $delta;
            $stock->save();

            StockMovement::query()->create([
                'product_id' => $productId,
                'delta' => $delta,
                'quantity_after' => $stock->quantity,
                'reason' => $reason,
                'note' => $note,
                'user_id' => $userId,
            ]);

            return $stock;
        });
    }
}
