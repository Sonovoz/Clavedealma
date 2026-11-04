<?php

namespace App\Modules\Cart\Contracts;

use App\Modules\Catalog\Contracts\SellableVariant;
use App\Modules\Stores\Contracts\StoreInfo;

/**
 * Una línea de la cesta con su precio actual. $problem explica por qué no se puede comprar
 * tal cual (agotada, retirada…); $quantity ya viene ajustada al máximo disponible.
 */
final readonly class PricedLine
{
    public function __construct(
        public SellableVariant $variant,
        public StoreInfo $store,
        public int $quantity,
        public int $requested,
        public ?int $available,
        public ?string $problem,
    ) {}

    public function totalCents(): int
    {
        return $this->variant->unitPriceCents * $this->quantity;
    }

    public function buyable(): bool
    {
        return $this->quantity > 0;
    }
}
