<?php

namespace App\Modules\Payments\Contracts\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * Un intento de pago se ha rechazado. El pedido sigue esperando: el cliente puede reintentar.
 */
final class PaymentFailed
{
    use Dispatchable;

    public function __construct(public readonly int $orderId, public readonly ?string $message) {}
}
