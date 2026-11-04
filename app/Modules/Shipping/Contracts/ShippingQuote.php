<?php

namespace App\Modules\Shipping\Contracts;

/**
 * Precio de un envío tal como lo ve el cliente (el transportista lo elige Clave de Alma).
 */
final readonly class ShippingQuote
{
    public function __construct(
        public ShippingZone $zone,
        public int $priceCents,
        public bool $free,
        public int $minDays,
        public int $maxDays,
        public string $carrier,
    ) {}

    public function deliveryLabel(): string
    {
        return $this->minDays === $this->maxDays
            ? "Entrega en {$this->minDays} días laborables"
            : "Entrega en {$this->minDays}–{$this->maxDays} días laborables";
    }
}
