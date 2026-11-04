<?php

namespace App\Modules\Orders\Contracts\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * El pedido ha salido: se avisa al cliente con el seguimiento.
 */
final class OrderShipped implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(public readonly int $orderId) {}
}
