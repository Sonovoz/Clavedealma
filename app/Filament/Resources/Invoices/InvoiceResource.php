<?php

namespace App\Filament\Resources\Invoices;

use App\Filament\Resources\Invoices\Pages\ListInvoices;
use App\Filament\Resources\Invoices\Pages\ViewInvoice;
use App\Filament\Resources\Orders\OrderResource;
use App\Modules\Invoicing\Contracts\AeatStatus;
use App\Modules\Invoicing\Contracts\InvoiceType;
use App\Modules\Invoicing\Infrastructure\Models\Invoice;
use App\Modules\Invoicing\Infrastructure\Models\InvoiceLine;
use App\Platform\Documents\Download;
use App\Platform\Documents\InvoiceDocument;
use App\Support\Money;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Facturas emitidas (solo lectura: una factura no se edita ni se borra). Superadministradores.
 */
class InvoiceResource extends Resource
{
    protected static ?string $slug = 'facturas';

    protected static ?string $model = Invoice::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static string|UnitEnum|null $navigationGroup = 'Ventas';

    protected static ?string $modelLabel = 'factura';

    protected static ?string $pluralModelLabel = 'facturas';

    protected static ?string $recordTitleAttribute = 'code';

    protected static ?int $navigationSort = 20;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function pdfAction(): Action
    {
        return Action::make('invoicePdf')
            ->label('PDF')
            ->icon(Heroicon::OutlinedDocumentArrowDown)
            ->color('gray')
            ->action(fn (Invoice $record) => Download::pdf('pdf.invoice', InvoiceDocument::data($record), 'factura-'.$record->code.'.pdf'));
    }

    public static function table(Table $table): Table
    {
        $money = fn (int $state): string => Money::fromCents($state)->format();

        return $table
            ->columns([
                TextColumn::make('code')->label('Número')->weight('bold')->searchable(['code', 'order_reference'])
                    ->description(fn (Invoice $record): string => $record->type->label()),
                TextColumn::make('issued_at')->label('Fecha')->dateTime('d/m/Y H:i')->sortable(),
                TextColumn::make('order_reference')->label('Pedido')
                    ->url(fn (Invoice $record): string => OrderResource::getUrl('view', ['record' => $record->order_id])),
                TextColumn::make('total_cents')->label('Total')->formatStateUsing($money)->alignEnd(),
                TextColumn::make('aeat_status')->label('AEAT')->badge()->visibleFrom('lg')
                    ->formatStateUsing(fn (AeatStatus $state): string => $state->label())
                    ->color(fn (AeatStatus $state): string => $state->color()),
            ])
            ->filters([
                SelectFilter::make('type')->label('Tipo')->options(collect(InvoiceType::cases())->mapWithKeys(fn (InvoiceType $t): array => [$t->value => $t->label()])->all()),
                SelectFilter::make('aeat_status')->label('Estado en la AEAT')->options(collect(AeatStatus::cases())->mapWithKeys(fn (AeatStatus $s): array => [$s->value => $s->label()])->all()),
            ])
            ->recordActions([ViewAction::make(), self::pdfAction()->iconButton()->tooltip('Descargar PDF')])
            ->recordUrl(fn (Invoice $record): string => self::getUrl('view', ['record' => $record]))
            ->defaultSort('id', 'desc');
    }

    public static function infolist(Schema $schema): Schema
    {
        $money = fn (int $state): string => Money::fromCents($state)->format();

        return $schema->columns(3)->components([
            Section::make('Detalle')->columnSpan(2)->schema([
                RepeatableEntry::make('lines')->hiddenLabel()->table([
                    RepeatableEntry\TableColumn::make('Concepto'),
                    RepeatableEntry\TableColumn::make('Ud.')->alignEnd(),
                    RepeatableEntry\TableColumn::make('IVA')->alignEnd(),
                    RepeatableEntry\TableColumn::make('Importe')->alignEnd(),
                ])->schema([
                    TextEntry::make('description'),
                    TextEntry::make('quantity')->alignEnd(),
                    TextEntry::make('vat_rate_bps')->formatStateUsing(fn (int $state): string => ($state / 100).' %')->alignEnd(),
                    TextEntry::make('total_cents')->formatStateUsing($money)->alignEnd()
                        ->helperText(fn (InvoiceLine $record): string => 'Base '.Money::fromCents($record->base_cents)->format().' · IVA '.Money::fromCents($record->vat_cents)->format()),
                ]),
                TextEntry::make('totals')->label('Totales')->state(fn (Invoice $record): string => 'Base '.$money($record->base_cents).' · IVA '.$money($record->vat_cents).' · Total '.$money($record->total_cents)),
            ]),
            Section::make('Factura')->columnSpan(1)->schema([
                TextEntry::make('type')->label('Tipo')->formatStateUsing(fn (InvoiceType $state): string => $state->label()),
                TextEntry::make('issued_at')->label('Expedida')->dateTime('d/m/Y H:i'),
                TextEntry::make('order_reference')->label('Pedido')->url(fn (Invoice $record): string => OrderResource::getUrl('view', ['record' => $record->order_id])),
                TextEntry::make('rectifies.code')->label('Rectifica a')->visible(fn (Invoice $record): bool => $record->rectifies_id !== null),
                TextEntry::make('reason')->label('Motivo')->visible(fn (Invoice $record): bool => $record->reason !== null),
                TextEntry::make('customer_name')->label('Cliente')->placeholder('—'),
            ]),
            Section::make('Veri*Factu')->columnSpanFull()->columns(2)->schema([
                TextEntry::make('aeat_status')->label('Estado en la AEAT')->badge()
                    ->formatStateUsing(fn (AeatStatus $state): string => $state->label())
                    ->color(fn (AeatStatus $state): string => $state->color()),
                TextEntry::make('aeat_error')->label('Respuesta de la AEAT')->placeholder('—'),
                TextEntry::make('hash')->label('Huella')->fontFamily('mono')->copyable(),
                TextEntry::make('previous_hash')->label('Huella anterior')->fontFamily('mono')->placeholder('— (primer registro)'),
                TextEntry::make('record_generated_at')->label('Fecha y hora del registro'),
                TextEntry::make('qr_url')->label('URL del QR')->url(fn (Invoice $record): string => $record->qr_url, shouldOpenInNewTab: true)->limit(60),
            ]),
        ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['lines', 'rectifies']);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListInvoices::route('/'),
            'view' => ViewInvoice::route('/{record}'),
        ];
    }
}
