<?php

namespace App\Modules\Inventory\Application;

use App\Modules\Inventory\Contracts\StockMovementReason;
use App\Modules\Inventory\Infrastructure\Models\StockLevel;
use App\Modules\Inventory\Infrastructure\Models\StockMovement;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Suma o resta unidades de una variante y deja constancia del movimiento.
 */
class AdjustStock
{
    public function __invoke(int $variantId, int $delta, StockMovementReason $reason, ?string $note = null, ?int $userId = null): StockLevel
    {
        if ($delta === 0) {
            throw new InvalidArgumentException('El ajuste no puede ser cero.');
        }

        return DB::transaction(function () use ($variantId, $delta, $reason, $note, $userId): StockLevel {
            $level = StockLevel::query()->where('product_variant_id', $variantId)->lockForUpdate()->firstOrFail();

            if ($level->quantity + $delta < 0) {
                throw new InvalidArgumentException("No hay unidades suficientes: quedan {$level->quantity}.");
            }

            $level->quantity += $delta;
            $level->save();

            StockMovement::create([
                'product_variant_id' => $variantId,
                'delta' => $delta,
                'quantity_after' => $level->quantity,
                'reason' => $reason,
                'note' => $note,
                'user_id' => $userId,
            ]);

            return $level;
        });
    }
}
