<?php

namespace App\Modules\Inventory\Application;

use App\Modules\Inventory\Contracts\StockMode;
use App\Modules\Inventory\Contracts\StockMovementReason;
use App\Modules\Inventory\Infrastructure\Models\StockLevel;

/**
 * Fija cómo se vende una variante y, si es limitada, sus unidades.
 *
 * Las unidades solo se aplican mientras la ficha no tenga más movimientos que altas
 * iniciales, o si cambia de modo: el stock vivo (ventas, fabricación, ajustes) manda.
 * La usan la ficha de artículo del panel y el importador.
 */
class SetStockMode
{
    public function __construct(private readonly AdjustStock $adjustStock) {}

    /**
     * @return ?string aviso si las unidades no se han aplicado para no pisar el stock vivo
     */
    public function __invoke(int $variantId, StockMode $mode, ?int $units = null, ?int $threshold = null, ?int $userId = null, bool $simulate = false): ?string
    {
        $level = $simulate
            ? StockLevel::query()->where('product_variant_id', $variantId)->first()
            : StockLevel::query()->firstOrCreate(['product_variant_id' => $variantId], ['mode' => StockMode::OnDemand, 'quantity' => 0]);
        if ($level === null) {
            return null;
        }

        $modeChanges = $level->mode !== $mode;
        $hasLiveMovements = $level->movements()->where('reason', '!=', StockMovementReason::Initial)->exists();
        $level->mode = $mode;
        if ($threshold !== null) {
            $level->low_stock_threshold = $threshold;
        }

        $delta = 0;
        $warning = null;
        if ($mode->tracksQuantity() && $units !== null && $units !== $level->quantity) {
            if (! $hasLiveMovements || $modeChanges) {
                $delta = $units - $level->quantity;
            } else {
                $warning = "ya tiene movimientos de stock: quedan {$level->quantity} y se pedían {$units}. No se cambia; ajústalo desde Stock.";
            }
        }

        if (! $simulate) {
            $level->save();
            if ($delta !== 0) {
                ($this->adjustStock)($variantId, $delta, $hasLiveMovements ? StockMovementReason::Adjustment : StockMovementReason::Initial, 'Alta del artículo', $userId);
            }
        }

        return $warning;
    }
}
