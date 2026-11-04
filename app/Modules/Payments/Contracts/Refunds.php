<?php

namespace App\Modules\Payments\Contracts;

/**
 * Devolver dinero de un pedido pagado (cancelaciones y devoluciones). Siempre al mismo
 * medio de pago con el que se cobró; la misma clave no devuelve dos veces.
 */
interface Refunds
{
    public function refund(int $orderId, int $amountCents, string $idempotencyKey, string $reason): RefundResult;

    /**
     * Devoluciones de dinero hechas, por orden.
     *
     * @return list<array{id: int, order_id: int, amount_cents: int, reason: string}>
     */
    public function succeeded(): array;

    /** Lo ya devuelto de un pedido, en céntimos. */
    public function refundedCents(int $orderId): int;
}
