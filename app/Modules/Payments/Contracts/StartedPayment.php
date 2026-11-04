<?php

namespace App\Modules\Payments\Contracts;

final readonly class StartedPayment
{
    public function __construct(
        public string $providerReference,
        /** Lo necesita el formulario de pago del navegador (Stripe Payment Element). */
        public ?string $clientSecret,
    ) {}
}
