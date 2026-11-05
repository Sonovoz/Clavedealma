<?php

namespace App\Modules\Catalog\Contracts;

/**
 * Lo que otros módulos (cesta, pedidos) necesitan saber de una variante para venderla.
 * Precio actual con IVA incluido, en céntimos; nunca un precio guardado en la cesta.
 */
final readonly class SellableVariant
{
    public function __construct(
        public int $variantId,
        public int $productId,
        public int $storeId,
        public string $sku,
        public string $productName,
        public string $productSlug,
        public ?string $reference,
        public ?string $size,
        public ?string $color,
        public int $unitPriceCents,
        public int $vatRateBps,
        /** Comisión propia del artículo; null = la de la tienda. */
        public ?int $commissionBps,
        /** Peso de la prenda base para el envío; null si no se ha indicado. */
        public ?int $weightGrams,
        public int $leadTimeMinDays,
        public int $leadTimeMaxDays,
        public bool $personalized,
        public ?string $imagePath,
        /** Artículo publicado y variante activa. */
        public bool $onSale,
        /** Rebajado ahora (por debajo del precio de referencia de 30 días): para los cupones. */
        public bool $onPromotion = false,
    ) {}

    public function label(): string
    {
        return implode(' · ', array_filter([$this->size, $this->color]));
    }
}
