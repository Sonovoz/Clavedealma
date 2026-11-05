<?php

namespace App\Modules\Orders\Contracts;

final readonly class OrderLineSummary
{
    public function __construct(
        public int $id,
        public int $variantId,
        public string $sku,
        public string $productName,
        public string $option,
        public string $storeName,
        public int $quantity,
        public int $unitPriceCents,
        public bool $personalized,
        public ?string $imagePath,
        public int $vatRateBps = 2100,
        /** Importe de la línea con IVA incluido (ya con el descuento del cupón). */
        public int $totalCents = 0,
        /** Parte del descuento del cupón en esta línea (ADR 0024). */
        public int $discountCents = 0,
    ) {}
}
