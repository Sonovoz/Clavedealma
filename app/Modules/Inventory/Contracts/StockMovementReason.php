<?php

namespace App\Modules\Inventory\Contracts;

use App\Support\Contracts\HasLabel;

enum StockMovementReason: string implements HasLabel
{
    case Initial = 'initial';
    case Adjustment = 'adjustment';
    case Production = 'production';
    case Sale = 'sale';
    case Return = 'return';

    public function label(): string
    {
        return match ($this) {
            self::Initial => 'Alta inicial',
            self::Adjustment => 'Ajuste manual',
            self::Production => 'Fabricación de stock',
            self::Sale => 'Venta',
            self::Return => 'Devolución',
        };
    }
}
