<?php

namespace App\Modules\Orders\Contracts\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Un pedido sin pagar se ha cancelado. Lo escucha Pagos para anular el cobro pendiente.
 */
final class OrderCancelled implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(public readonly int $orderId) {}
}
