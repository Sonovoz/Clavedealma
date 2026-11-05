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
        /** Pasarelas con página propia (Redsys): adónde vuelve el cliente si paga… */
        public ?string $returnUrl = null,
        /** …y si no paga o cancela. */
        public ?string $cancelUrl = null,
        /** Método elegido en «Métodos de pago» (una clave de PaymentGateway::methods()). */
        public ?string $method = null,
    ) {}
}
