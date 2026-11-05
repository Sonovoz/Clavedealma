<?php

namespace App\Modules\Payments\Contracts;

final readonly class StartedPayment
{
    /**
     * @param  array<string, string>  $redirectFields
     */
    public function __construct(
        public string $providerReference,
        /** Lo necesita el formulario de pago del navegador (Stripe Payment Element). */
        public ?string $clientSecret,
        /** Pasarelas con página propia (Redsys): el navegador envía estos campos a esta dirección. */
        public ?string $redirectUrl = null,
        public array $redirectFields = [],
    ) {}

    public function redirects(): bool
    {
        return $this->redirectUrl !== null;
    }
}
