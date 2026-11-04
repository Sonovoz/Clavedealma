<?php

namespace App\Modules\Payments\Contracts\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * La pasarela confirma el cobro (webhook o comprobación al volver del pago).
 * Lo escucha Pedidos para marcar el pedido como pagado.
 */
final class PaymentSucceeded
{
    use Dispatchable;

    public function __construct(
        public readonly int $orderId,
        public readonly int $amountCents,
        public readonly string $currency,
        public readonly string $provider,
        public readonly string $providerReference,
    ) {}
}
