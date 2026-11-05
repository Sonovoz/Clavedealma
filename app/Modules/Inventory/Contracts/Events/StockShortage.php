<?php

namespace App\Modules\Inventory\Contracts\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * Se pagó un pedido con más unidades de las que quedaban de un producto con cantidad
 * limitada (dos clientes pagando a la vez las últimas). El pedido queda «a revisar».
 */
class StockShortage
{
    use Dispatchable;

    public function __construct(public readonly int $orderId, public readonly int $productId, public readonly int $missing) {}
}
