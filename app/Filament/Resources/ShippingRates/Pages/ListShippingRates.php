<?php

namespace App\Filament\Resources\ShippingRates\Pages;

use App\Filament\Resources\ShippingRates\ShippingRateResource;
use App\Filament\Support\Fields;
use App\Modules\Shipping\Application\QuoteShipping;
use App\Modules\Shipping\Contracts\ShippingZone;
use App\Modules\Shipping\Domain\ZoneResolver;
use App\Support\Money;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

class ListShippingRates extends ListRecords
{
    protected static string $resource = ShippingRateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('simulate')
                ->label('Calcular un envío')
                ->icon(Heroicon::OutlinedCalculator)
                ->color('gray')
                ->modalDescription('Comprueba el baremo: lo que pagaría un cliente por un pedido.')
                ->schema([
                    Select::make('country')->label('País')->options(['ES' => 'España', 'PT' => 'Portugal', 'FR' => 'Francia', 'DE' => 'Alemania', 'IT' => 'Italia'])->default('ES')->required(),
                    TextInput::make('postal_code')->label('Código postal')->placeholder('41001'),
                    Fields::kilograms('weight')->label('Peso del paquete')->default(800)->required(),
                    Fields::money('subtotal')->label('Importe de la compra')->default(4990)->required(),
                ])
                ->action(function (array $data, QuoteShipping $quote): void {
                    $zone = ZoneResolver::resolve((string) $data['country'], $data['postal_code'] ?? null);
                    $result = $zone instanceof ShippingZone ? $quote($zone, (int) $data['weight'], (int) $data['subtotal']) : null;

                    $notification = Notification::make()->title($zone === null ? 'No enviamos a ese país' : 'Envío a '.$zone->label());
                    if ($result === null) {
                        $notification->body($zone === null ? '' : 'Sin tarifa: el destino está desactivado, sin transportista o el peso supera el último tramo.')->warning();
                    } else {
                        $notification->body(($result->free ? 'Gratis' : Money::fromCents($result->priceCents)->format()).' · '.$result->deliveryLabel().' · con '.$result->carrier)->success();
                    }
                    $notification->persistent()->send();
                }),
        ];
    }
}
