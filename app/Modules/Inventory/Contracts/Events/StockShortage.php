<?php

namespace App\Modules\Inventory\Contracts\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * Al pagar un pedido faltaban unidades de una variante que se agota (dos clientes
 * compraron la última a la vez). Lo escucha Pedidos para marcarlo «a revisar».
 */
final class StockShortage
{
    use Dispatchable;

    public function __construct(public readonly int $orderId, public readonly int $variantId, public readonly int $missing) {}
}
