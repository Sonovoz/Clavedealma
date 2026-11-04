<?php

namespace App\Modules\Shipping\Application;

use App\Modules\Shipping\Contracts\ShippingQuote;
use App\Modules\Shipping\Contracts\ShippingZone;
use App\Modules\Shipping\Domain\WeightScale;
use App\Modules\Shipping\Infrastructure\Models\RateBand;
use App\Modules\Shipping\Infrastructure\Models\ZoneRate;

/**
 * Precio del envío de un pedido: destino + peso total + importe de la compra.
 * Null si el destino no está activo, no tiene transportista o el peso no entra en el baremo.
 */
class QuoteShipping
{
    public function __invoke(ShippingZone $zone, int $weightGrams, int $subtotalCents): ?ShippingQuote
    {
        $rate = ZoneRate::query()->with(['carrier', 'bands'])->where('zone', $zone)->where('active', true)->first();
        if ($rate === null || $rate->carrier === null || ! $rate->carrier->active) {
            return null;
        }

        $bands = $rate->bands->map(fn (RateBand $b): array => ['max_grams' => $b->max_weight_grams, 'price_cents' => $b->price_cents])->all();
        $price = WeightScale::price(array_values($bands), max(1, $weightGrams));
        if ($price === null) {
            return null;
        }

        $free = $rate->free_from_cents !== null && $subtotalCents >= $rate->free_from_cents;

        return new ShippingQuote($zone, $free ? 0 : $price, $free, $rate->min_days, $rate->max_days, $rate->carrier->name);
    }
}
