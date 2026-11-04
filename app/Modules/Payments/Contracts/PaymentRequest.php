<?php

namespace App\Modules\Payments\Contracts;

final readonly class PaymentRequest
{
    public function __construct(
        public int $orderId,
        public string $reference,
        public int $amountCents,
        public string $currency,
        public string $email,
        /** Misma clave = mismo cobro en la pasarela (nunca se cobra dos veces). */
        public string $idempotencyKey,
    ) {}
}
