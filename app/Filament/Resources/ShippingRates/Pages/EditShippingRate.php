<?php

namespace App\Filament\Resources\ShippingRates\Pages;

use App\Filament\Resources\ShippingRates\ShippingRateResource;
use Filament\Resources\Pages\EditRecord;

class EditShippingRate extends EditRecord
{
    protected static string $resource = ShippingRateResource::class;

    public function getTitle(): string
    {
        return 'Envíos a '.$this->getRecord()->getAttribute('zone')->label();
    }
}
