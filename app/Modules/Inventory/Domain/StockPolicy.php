<?php

namespace App\Modules\Inventory\Domain;

use App\Modules\Inventory\Contracts\StockStatus;

/**
 * Reglas del inventario por producto. Sin dependencias de framework: se prueba aislada.
 */
final class StockPolicy
{
    /** Unidades que se pueden vender: null = sin límite; 0 = agotado. */
    public static function limit(StockStatus $status, bool $managed, int $quantity): ?int
    {
        if ($status === StockStatus::OutOfStock) {
            return 0;
        }

        return $managed ? max(0, $quantity) : null;
    }

    /** Cuántas de las unidades pagadas salen de la cantidad disponible. */
    public static function taken(bool $managed, int $quantity, int $requested): int
    {
        return $managed ? max(0, min($quantity, $requested)) : 0;
    }

    public static function isLow(bool $managed, int $quantity, ?int $threshold): bool
    {
        return $managed && $threshold !== null && $quantity <= $threshold;
    }
}
