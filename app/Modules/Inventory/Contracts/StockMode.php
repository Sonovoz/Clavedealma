<?php

namespace App\Modules\Inventory\Contracts;

use App\Support\Contracts\HasLabel;

/**
 * Cómo se vende una variante (talla/color).
 */
enum StockMode: string implements HasLabel
{
    /** Se fabrica cada unidad tras el pago. Sin límite. */
    case OnDemand = 'on_demand';
    /** N unidades disponibles; al acabarse, "agotado". */
    case LimitedThenSoldOut = 'limited_sold_out';
    /** N unidades en stock (envío rápido); después se fabrica bajo pedido. */
    case LimitedThenOnDemand = 'limited_on_demand';

    public function label(): string
    {
        return match ($this) {
            self::OnDemand => 'Bajo pedido',
            self::LimitedThenSoldOut => 'Unidades limitadas (se agota)',
            self::LimitedThenOnDemand => 'Stock y después bajo pedido',
        };
    }

    public function tracksQuantity(): bool
    {
        return $this !== self::OnDemand;
    }
}
