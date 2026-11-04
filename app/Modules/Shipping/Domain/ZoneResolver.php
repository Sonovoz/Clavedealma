<?php

namespace App\Modules\Shipping\Domain;

use App\Modules\Shipping\Contracts\ShippingZone;

/**
 * Destino de un envío a partir del país (ISO 3166-1 alfa-2) y el código postal.
 * Devuelve null si no se envía a ese país.
 */
final class ZoneResolver
{
    /** Países de la UE (además de España y Portugal). */
    private const EU = ['AT', 'BE', 'BG', 'CY', 'CZ', 'DE', 'DK', 'EE', 'FI', 'FR', 'GR', 'HR', 'HU', 'IE', 'IT', 'LT', 'LU', 'LV', 'MT', 'NL', 'PL', 'RO', 'SE', 'SI', 'SK'];

    public static function resolve(string $country, ?string $postalCode = null): ?ShippingZone
    {
        $country = strtoupper(trim($country));

        if ($country === 'ES') {
            $prefix = substr(preg_replace('/\D/', '', (string) $postalCode) ?? '', 0, 2);

            return match ($prefix) {
                '07' => ShippingZone::Balearics,
                '35', '38' => ShippingZone::Canaries,
                '51', '52' => ShippingZone::CeutaMelilla,
                default => ShippingZone::Peninsula,
            };
        }

        return match (true) {
            $country === 'PT' => ShippingZone::Portugal,
            in_array($country, self::EU, true) => ShippingZone::Europe,
            default => null,
        };
    }
}
