<?php

namespace App\Modules\Shipping\Contracts;

use App\Support\Contracts\HasLabel;

/**
 * Destinos de envío. Canarias, Ceuta y Melilla van aparte porque no llevan IVA
 * español (IGIC / IPSI y trámite de aduana): se resolverá en la fase de pagos.
 */
enum ShippingZone: string implements HasLabel
{
    case Peninsula = 'peninsula';
    case Balearics = 'baleares';
    case Canaries = 'canarias';
    case CeutaMelilla = 'ceuta_melilla';
    case Portugal = 'portugal';
    case Europe = 'europa';

    public function label(): string
    {
        return match ($this) {
            self::Peninsula => 'Península',
            self::Balearics => 'Baleares',
            self::Canaries => 'Canarias',
            self::CeutaMelilla => 'Ceuta y Melilla',
            self::Portugal => 'Portugal',
            self::Europe => 'Resto de la Unión Europea',
        };
    }
}
