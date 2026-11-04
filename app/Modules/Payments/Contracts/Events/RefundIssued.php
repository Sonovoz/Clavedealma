<?php

namespace App\Modules\Payments\Contracts\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Se ha devuelto dinero de un pedido (cancelación o devolución de piezas).
 * Lo escucha Facturación para emitir la factura rectificativa.
 */
final class RefundIssued implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public readonly int $refundId,
        public readonly int $orderId,
        public readonly int $amountCents,
        public readonly string $reason,
    ) {}
}
