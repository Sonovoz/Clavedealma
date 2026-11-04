<?php

namespace App\Storefront\Checkout;

/**
 * Países a los que se puede pedir (el envío y su precio los decide el panel: Envíos).
 */
final class Countries
{
    public const ALL = [
        'ES' => 'España', 'PT' => 'Portugal', 'AT' => 'Austria', 'BE' => 'Bélgica', 'BG' => 'Bulgaria',
        'CY' => 'Chipre', 'HR' => 'Croacia', 'CZ' => 'Chequia', 'DK' => 'Dinamarca', 'EE' => 'Estonia',
        'FI' => 'Finlandia', 'FR' => 'Francia', 'DE' => 'Alemania', 'GR' => 'Grecia', 'HU' => 'Hungría',
        'IE' => 'Irlanda', 'IT' => 'Italia', 'LV' => 'Letonia', 'LT' => 'Lituania', 'LU' => 'Luxemburgo',
        'MT' => 'Malta', 'NL' => 'Países Bajos', 'PL' => 'Polonia', 'RO' => 'Rumanía', 'SK' => 'Eslovaquia',
        'SI' => 'Eslovenia', 'SE' => 'Suecia',
    ];

    public static function postalPattern(string $country): string
    {
        return match ($country) {
            'ES' => '/^\d{5}$/',
            'PT' => '/^\d{4}-?\d{3}$/',
            default => '/^[A-Za-z0-9 \-]{3,10}$/',
        };
    }
}
