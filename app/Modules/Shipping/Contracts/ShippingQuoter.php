<?php

namespace App\Modules\Shipping\Contracts;

interface ShippingQuoter
{
    /** Destino de una dirección (país ISO de 2 letras + código postal); null si no enviamos allí. */
    public function zoneFor(string $country, ?string $postalCode): ?ShippingZone;

    /** Precio del envío; null si el destino no tiene tarifa activa o el peso no entra en el baremo. */
    public function quote(ShippingZone $zone, int $weightGrams, int $subtotalCents): ?ShippingQuote;
}
