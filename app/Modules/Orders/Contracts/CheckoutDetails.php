<?php

namespace App\Modules\Orders\Contracts;

/**
 * Datos que el cliente escribe al tramitar el pedido (ya validados).
 */
final readonly class CheckoutDetails
{
    public function __construct(
        public string $email,
        public string $name,
        public string $phone,
        public string $addressLine1,
        public ?string $addressLine2,
        public string $postalCode,
        public string $city,
        public ?string $province,
        public string $country,
        public ?string $note,
        public string $termsVersion,
        /** Cliente con cuenta que hace el pedido; null = invitado (ADR 0021). */
        public ?int $customerId = null,
    ) {}
}
