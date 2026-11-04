<?php

namespace App\Modules\Payments\Contracts;

/**
 * Aviso de la pasarela traducido a nuestro idioma: cobro hecho, rechazado o ninguno de los dos.
 */
final readonly class GatewayEvent
{
    public const SUCCEEDED = 'succeeded';

    public const FAILED = 'failed';

    public const OTHER = 'other';

    public function __construct(
        public string $eventId,
        public string $type,
        public ?string $providerReference,
        public ?int $amountCents = null,
        public ?string $currency = null,
        public ?string $failureMessage = null,
    ) {}
}
