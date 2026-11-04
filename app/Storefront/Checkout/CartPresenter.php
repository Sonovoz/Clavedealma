<?php

namespace App\Storefront\Checkout;

use App\Modules\Cart\Contracts\PricedCart;
use App\Modules\Cart\Contracts\PricedLine;
use App\Storefront\Urls;
use App\Support\Money;
use Illuminate\Support\Facades\Storage;

/**
 * Datos de la cesta listos para pintar (la plantilla no calcula nada).
 */
final class CartPresenter
{
    /**
     * @return list<array<string, mixed>>
     */
    public static function lines(PricedCart $cart): array
    {
        return array_map(fn (PricedLine $l): array => [
            'variant_id' => $l->variant->variantId,
            'name' => $l->variant->productName,
            'option' => $l->variant->label(),
            'store' => $l->store->name,
            'url' => Urls::product($l->store->key, $l->variant->productSlug),
            'image' => $l->variant->imagePath === null ? null : Storage::disk('public')->url($l->variant->imagePath),
            'unit' => Money::fromCents($l->variant->unitPriceCents)->format(),
            'total' => Money::fromCents($l->totalCents())->format(),
            'quantity' => $l->quantity,
            'requested' => $l->requested,
            'max' => min($l->available ?? PHP_INT_MAX, (int) config('clavedealma.shop.max_per_line', 10)),
            'problem' => $l->problem,
            'personalized' => $l->variant->personalized,
        ], $cart->lines);
    }
}
