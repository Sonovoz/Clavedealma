<?php

namespace App\Filament\Resources\Orders\Pages;

use App\Filament\Resources\Orders\OrderActions;
use App\Filament\Resources\Orders\OrderResource;
use App\Filament\Support\Documents;
use Filament\Actions\ActionGroup;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;

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
            ActionGroup::make([Documents::productionOrder(), Documents::packingSlip()])
                ->label('PDF')->icon(Heroicon::OutlinedDocumentArrowDown)->button()->color('gray'),
        ];
    }
}
