<?php

namespace App\Modules\Shipping\Infrastructure\Lookup;

use App\Modules\Shipping\Contracts\CarrierDirectory;
use App\Modules\Shipping\Infrastructure\Models\Carrier;

class EloquentCarrierDirectory implements CarrierDirectory
{
    public function names(): array
    {
        return Carrier::query()->where('active', true)->orderBy('name')->pluck('name')->map(fn ($n): string => (string) $n)->values()->all();
    }

    public function trackingUrl(string $carrier, string $trackingNumber): ?string
    {
        return Carrier::query()->where('name', $carrier)->first()?->trackingUrl($trackingNumber);
    }
}
