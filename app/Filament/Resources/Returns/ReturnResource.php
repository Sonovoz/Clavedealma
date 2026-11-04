<?php

namespace App\Filament\Resources\Returns;

use App\Filament\Resources\Orders\OrderResource;
use App\Filament\Resources\Returns\Pages\ListReturns;
use App\Filament\Resources\Returns\Pages\ViewReturn;
use App\Modules\Returns\Contracts\ReturnReason;
use App\Modules\Returns\Contracts\ReturnStatus;
use App\Modules\Returns\Infrastructure\Models\ReturnLine;
use App\Modules\Returns\Infrastructure\Models\ReturnRequest;
use App\Support\Money;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Devoluciones de piezas (solo superadministradores). Se aprueban o rechazan y, al recibir
 * el paquete, se reponen las piezas y se devuelve el dinero (ADR 0016).
 */
class ReturnResource extends Resource
{
    protected static ?string $slug = 'devoluciones';

    protected static ?string $model = ReturnRequest::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowUturnLeft;

    protected static string|UnitEnum|null $navigationGroup = 'Ventas';

    protected static ?string $modelLabel = 'devolución';

    protected static ?string $pluralModelLabel = 'devoluciones';

    protected static ?string $recordTitleAttribute = 'reference';

    public static function getNavigationBadge(): ?string
    {
        $count = ReturnRequest::query()->whereIn('status', [ReturnStatus::Requested, ReturnStatus::Received])->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Devoluciones por revisar o con el dinero pendiente';
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('reference')->label('Devolución')->weight('bold')->searchable(['reference', 'order_reference']),
                TextColumn::make('order_reference')->label('Pedido')
                    ->url(fn (ReturnRequest $record): string => OrderResource::getUrl('view', ['record' => $record->order_id])),
                TextColumn::make('created_at')->label('Pedida')->dateTime('d/m/Y H:i')->sortable(),
                TextColumn::make('reason')->label('Motivo')->formatStateUsing(fn (ReturnReason $state): string => $state->label()),
                TextColumn::make('refund_cents')->label('A devolver')->formatStateUsing(fn (int $state): string => Money::fromCents($state)->format())->alignEnd(),
                TextColumn::make('status')->label('Estado')->badge()
                    ->formatStateUsing(fn (ReturnStatus $state): string => $state->label())
                    ->color(fn (ReturnStatus $state): string => $state->color()),
            ])
            ->recordActions([ViewAction::make()])
            ->recordUrl(fn (ReturnRequest $record): string => self::getUrl('view', ['record' => $record]))
            ->defaultSort('created_at', 'desc');
    }

    public static function infolist(Schema $schema): Schema
    {
        $money = fn (int $state): string => Money::fromCents($state)->format();

        return $schema->columns(3)->components([
            Callout::make('El dinero no se ha podido devolver')
                ->description(fn (ReturnRequest $record): ?string => $record->refund_error)
                ->danger()
                ->visible(fn (ReturnRequest $record): bool => $record->refund_error !== null)
                ->columnSpanFull(),
            Callout::make('Error nuestro: el envío de vuelta lo paga Clave de Alma')
                ->description('Indícalo en las instrucciones (por ejemplo, con una etiqueta de recogida).')
                ->warning()
                ->visible(fn (ReturnRequest $record): bool => $record->reason->ourFault() && $record->status === ReturnStatus::Requested)
                ->columnSpanFull(),

            Section::make('Piezas')->columnSpan(2)->schema([
                RepeatableEntry::make('lines')->hiddenLabel()->table([
                    RepeatableEntry\TableColumn::make('Pieza'),
                    RepeatableEntry\TableColumn::make('Ud.')->alignEnd(),
                    RepeatableEntry\TableColumn::make('Precio')->alignEnd(),
                ])->schema([
                    TextEntry::make('product_name')->helperText(fn (ReturnLine $record): string => trim($record->sku.' · '.$record->option, ' ·')),
                    TextEntry::make('quantity')->alignEnd(),
                    TextEntry::make('unit_price_cents')->formatStateUsing($money)->alignEnd(),
                ]),
                TextEntry::make('refund_cents')->label('Importe a devolver')->formatStateUsing($money)->weight('bold')
                    ->helperText(fn (ReturnRequest $record): string => $record->includes_shipping
                        ? 'Incluye el envío pagado: con esta devolución vuelve el pedido entero.'
                        : 'Solo las piezas: el envío se devuelve si vuelve el pedido entero.'),
            ]),

            Section::make('Estado')->columnSpan(1)->schema([
                TextEntry::make('status')->label('Estado')->badge()
                    ->formatStateUsing(fn (ReturnStatus $state): string => $state->label())
                    ->color(fn (ReturnStatus $state): string => $state->color()),
                TextEntry::make('order_reference')->label('Pedido')
                    ->url(fn (ReturnRequest $record): string => OrderResource::getUrl('view', ['record' => $record->order_id])),
                TextEntry::make('created_at')->label('Pedida')->dateTime('d/m/Y H:i'),
                TextEntry::make('approved_at')->label('Aprobada')->dateTime('d/m/Y H:i')->visible(fn (ReturnRequest $r): bool => $r->approved_at !== null),
                TextEntry::make('rejected_at')->label('Rechazada')->dateTime('d/m/Y H:i')->visible(fn (ReturnRequest $r): bool => $r->rejected_at !== null),
                TextEntry::make('received_at')->label('Recibida')->dateTime('d/m/Y H:i')->visible(fn (ReturnRequest $r): bool => $r->received_at !== null)
                    ->helperText(fn (ReturnRequest $r): string => $r->restocked ? 'Repuesta en stock' : 'No repuesta en stock'),
                TextEntry::make('refunded_at')->label('Dinero devuelto')->dateTime('d/m/Y H:i')->visible(fn (ReturnRequest $r): bool => $r->refunded_at !== null),
            ]),

            Section::make('Cliente')->columnSpan(2)->schema([
                TextEntry::make('reason')->label('Motivo')->formatStateUsing(fn (ReturnReason $state): string => $state->label()),
                TextEntry::make('comment')->label('Comentario')->placeholder('—'),
                TextEntry::make('instructions')->label('Instrucciones enviadas')->visible(fn (ReturnRequest $r): bool => $r->instructions !== null),
                TextEntry::make('rejection_reason')->label('Motivo del rechazo')->visible(fn (ReturnRequest $r): bool => $r->rejection_reason !== null),
            ]),
        ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with('lines');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListReturns::route('/'),
            'view' => ViewReturn::route('/{record}'),
        ];
    }
}
