<?php

namespace App\Filament\Support;

use App\Modules\Orders\Infrastructure\Models\Order;
use App\Platform\Documents\Download;
use App\Platform\Documents\PackingSlip;
use App\Platform\Documents\ProductionSheet;
use App\Platform\Documents\SalesReport;
use App\Platform\Documents\ShippingReport;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Toggle;
use Filament\Support\Icons\Heroicon;

/**
 * Botones de descarga de los documentos PDF del panel.
 */
final class Documents
{
    public static function productionSheet(): Action
    {
        return Action::make('productionSheetPdf')
            ->label('Hoja de fabricación (PDF)')
            ->icon(Heroicon::OutlinedDocumentArrowDown)
            ->color('gray')
            ->action(fn () => Download::pdf('pdf.production', ProductionSheet::data(), 'hoja-de-fabricacion-'.now()->format('Y-m-d').'.pdf'));
    }

    public static function productionOrder(): Action
    {
        return Action::make('productionOrderPdf')
            ->label('Orden de fabricación')
            ->icon(Heroicon::OutlinedDocumentArrowDown)
            ->color('gray')
            ->action(fn (Order $record) => Download::pdf('pdf.production', ProductionSheet::data(Order::query()->with('lines')->whereKey($record->id)->get()), 'orden-fabricacion-'.$record->reference.'.pdf'));
    }

    public static function packingSlip(): Action
    {
        return Action::make('packingSlipPdf')
            ->label('Albarán de envío')
            ->icon(Heroicon::OutlinedDocumentArrowDown)
            ->color('gray')
            ->visible(fn (Order $record): bool => $record->status->isPaid())
            ->action(fn (Order $record) => Download::pdf('pdf.packing-slip', PackingSlip::data($record), 'albaran-'.$record->reference.'.pdf'));
    }

    public static function shippingReport(): Action
    {
        return Action::make('shippingReportPdf')
            ->label('Estado de envíos (PDF)')
            ->icon(Heroicon::OutlinedDocumentArrowDown)
            ->color('gray')
            ->action(fn () => Download::pdf('pdf.shipping', ShippingReport::data(), 'estado-envios-'.now()->format('Y-m-d').'.pdf', 'landscape'));
    }

    public static function salesReport(): Action
    {
        return Action::make('salesReportPdf')
            ->label('Informe de ventas (PDF)')
            ->icon(Heroicon::OutlinedDocumentArrowDown)
            ->modalHeading('Informe de ventas')
            ->modalSubmitActionLabel('Descargar PDF')
            ->schema([
                DatePicker::make('from')->label('Desde')->native(false)->displayFormat('d/m/Y')->default(now()->startOfMonth())->required(),
                DatePicker::make('to')->label('Hasta')->native(false)->displayFormat('d/m/Y')->default(now())->required()->afterOrEqual('from'),
                Toggle::make('tests')->label('Incluir compras de prueba')->default(false),
            ])
            ->action(function (array $data) {
                $from = CarbonImmutable::parse((string) $data['from'], 'Europe/Madrid');
                $to = CarbonImmutable::parse((string) $data['to'], 'Europe/Madrid');

                return Download::pdf('pdf.sales', SalesReport::data($from, $to, (bool) $data['tests']), 'ventas-'.$from->format('Y-m-d').'-'.$to->format('Y-m-d').'.pdf');
            });
    }
}
