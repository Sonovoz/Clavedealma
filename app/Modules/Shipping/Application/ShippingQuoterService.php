<?php

namespace App\Modules\Shipping\Application;

use App\Modules\Shipping\Contracts\ShippingQuote;
use App\Modules\Shipping\Contracts\ShippingQuoter;
use App\Modules\Shipping\Contracts\ShippingZone;
use App\Modules\Shipping\Domain\ZoneResolver;

class ShippingQuoterService implements ShippingQuoter
{
    public function __construct(private readonly QuoteShipping $quote) {}

    public function zoneFor(string $country, ?string $postalCode): ?ShippingZone
    {
        return ZoneResolver::resolve(strtoupper(trim($country)), $postalCode === null ? null : trim($postalCode));
    }

    public function quote(ShippingZone $zone, int $weightGrams, int $subtotalCents): ?ShippingQuote
    {
        return ($this->quote)($zone, $weightGrams, $subtotalCents);
    }
}
