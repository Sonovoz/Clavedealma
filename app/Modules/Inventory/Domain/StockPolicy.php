<?php

namespace App\Modules\Inventory\Domain;

use App\Modules\Inventory\Contracts\StockMode;

/**
 * Reglas de disponibilidad. Sin dependencias de framework: se prueba aislada.
 */
final class StockPolicy
{
    public static function canSell(StockMode $mode, int $available, int $requested): bool
    {
        if ($requested < 1) {
            return false;
        }

        return $mode !== StockMode::LimitedThenSoldOut || $requested <= $available;
    }

    /**
     * Cuántas de las unidades pedidas salen del stock ya fabricado.
     * El resto se fabrica bajo pedido.
     */
    public static function fromStock(StockMode $mode, int $available, int $requested): int
    {
        if (! $mode->tracksQuantity()) {
            return 0;
        }

        return max(0, min($available, $requested));
    }

    public static function isLow(StockMode $mode, int $available, ?int $threshold): bool
    {
        return $mode->tracksQuantity() && $threshold !== null && $available <= $threshold;
    }
}
