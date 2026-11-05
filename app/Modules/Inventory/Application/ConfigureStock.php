<?php

namespace App\Modules\Inventory\Application;

use App\Modules\Inventory\Contracts\StockMovementReason;
use App\Modules\Inventory\Contracts\StockStatus;
use App\Modules\Inventory\Infrastructure\Models\ProductStock;

/**
 * Pestaña «Inventario» de la ficha (y el importador): estado, «¿Gestionar inventario?»,
 * cantidad y umbral de pocas existencias de un producto.
 *
 * La cantidad se aplica como diferencia sobre la que había al abrir la ficha
 * ($loadedQuantity): si mientras tanto se vendió algo, no se pisa. Sin $loadedQuantity
 * (importador) solo se fija mientras no haya ventas; si las hay, avisa y no la cambia.
 */
class ConfigureStock
{
    public function __construct(private readonly AdjustStock $adjust) {}

    /**
     * @return ?string aviso si la cantidad no se ha aplicado
     */
    public function __invoke(int $productId, StockStatus $status, bool $managed, ?int $quantity = null, ?int $threshold = null, ?int $loadedQuantity = null, ?int $userId = null, bool $simulate = false): ?string
    {
        $stock = ProductStock::query()->where('product_id', $productId)->first() ?? new ProductStock(['product_id' => $productId]);
        $current = $stock->exists ? $stock->quantity : 0;

        $delta = 0;
        $warning = null;
        if ($managed && $quantity !== null) {
            if ($loadedQuantity !== null) {
                $delta = $quantity - $loadedQuantity;
            } elseif ($quantity !== $current) {
                $sold = $stock->exists && $stock->movements()->where('reason', StockMovementReason::Sale)->exists();
                if ($sold) {
                    $warning = "ya tiene ventas: quedan {$current} y se pedían {$quantity}. No se cambia; ajústalo en la pestaña Inventario de la ficha.";
                } else {
                    $delta = $quantity - $current;
                }
            }
        }

        if ($simulate) {
            return $warning;
        }

        $stock->status = $status;
        $stock->managed = $managed;
        $stock->low_stock_threshold = $managed ? $threshold : null;
        $stock->save();

        $apply = max($delta, -$current);
        if ($apply !== 0) {
            $hasMovements = $stock->movements()->exists();
            ($this->adjust)($productId, $apply, $hasMovements ? StockMovementReason::Adjustment : StockMovementReason::Initial, 'Inventario de la ficha', $userId);
        }

        return $warning;
    }
}
