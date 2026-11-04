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
    ) {}
}
