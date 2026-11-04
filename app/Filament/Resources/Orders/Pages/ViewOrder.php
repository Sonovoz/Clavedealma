<?php

namespace App\Filament\Resources\Orders\Pages;

use App\Filament\Resources\Orders\OrderActions;
use App\Filament\Resources\Orders\OrderResource;
use Filament\Resources\Pages\ViewRecord;

class ViewOrder extends ViewRecord
{
    protected static string $resource = OrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            OrderActions::startProduction(),
            OrderActions::markReady(),
            OrderActions::ship(),
            OrderActions::markDelivered(),
            OrderActions::cancelAndRefund(),
        ];
    }
}
