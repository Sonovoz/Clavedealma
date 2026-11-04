<?php

namespace App\Modules\Orders\Contracts\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Un pedido se ha pagado. Lo escucha Inventario para descontar las unidades.
 */
final class OrderPaid implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    /**
     * @param  list<array{variant_id: int, quantity: int}>  $lines
     */
    public function __construct(public readonly int $orderId, public readonly string $reference, public readonly array $lines) {}
}
