<?php

namespace App\Modules\Orders\Contracts\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Un pedido ya pagado se ha cancelado y se ha devuelto el importe.
 * Lo escucha Inventario para reponer las unidades que salieron del stock.
 */
final class PaidOrderCancelled implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(public readonly int $orderId, public readonly string $reference) {}
}
