<?php

namespace App\Filament\Resources\Orders;

use App\Filament\Resources\Customers\CustomerResource;
use App\Filament\Resources\Invoices\InvoiceResource;
use App\Filament\Resources\Orders\Pages\ListOrders;
use App\Filament\Resources\Orders\Pages\ViewOrder;
use App\Filament\Resources\Returns\ReturnResource;
use App\Filament\Support\Fields;
use App\Modules\Identity\Contracts\Role;
use App\Modules\Identity\Infrastructure\Models\User;
use App\Modules\Invoicing\Infrastructure\Models\Invoice;
use App\Modules\Orders\Contracts\OrderStatus;
use App\Modules\Orders\Infrastructure\Models\Order;
use App\Modules\Orders\Infrastructure\Models\OrderEvent;
use App\Modules\Orders\Infrastructure\Models\OrderLine;
use App\Modules\Payments\Contracts\Refunds;
use App\Modules\Payments\Infrastructure\Models\Payment;
use App\Modules\Returns\Infrastructure\Models\ReturnRequest;
use App\Modules\Shipping\Contracts\ShippingZone;
use App\Storefront\Checkout\Countries;
use App\Support\Money;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Pedidos (solo lectura: un pedido no se edita, ADR 0014). Solo superadministradores.
 * La fabricación y el envío se gestionarán aquí en la fase 5.
 */
class OrderResource extends Resource
{
    protected static ?string $slug = 'pedidos';

    protected static ?string $model = Order::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShoppingBag;

    protected static string|UnitEnum|null $navigationGroup = 'Comercio';

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'pedido';

    protected static ?string $pluralModelLabel = 'pedidos';

    protected static ?string $recordTitleAttribute = 'reference';

    public static function getNavigationBadge(): ?string
    {
        $count = Order::query()->whereIn('status', OrderStatus::paid())->whereNotNull('review_reason')->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Pedidos a revisar';
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        $money = fn (int $state): string => Money::fromCents($state)->format();

        return $table
            ->columns([
                TextColumn::make('reference')->label('Pedido')->searchable()->weight('bold')
                    ->description(fn (Order $record): ?string => $record->is_test ? 'Prueba' : null),
                TextColumn::make('created_at')->label('Fecha')->dateTime('d/m/Y H:i')->sortable(),
                TextColumn::make('customer_name')->label('Cliente')->searchable(['customer_name', 'email'])
                    ->description(fn (Order $record): string => $record->email),
                TextColumn::make('shipping_zone')->label('Destino')->formatStateUsing(fn (ShippingZone $state): string => $state->label())->toggleable(),
                TextColumn::make('total_cents')->label('Total')->formatStateUsing($money)->alignEnd()->sortable(),
                TextColumn::make('status')->label('Estado')->badge()
                    ->formatStateUsing(fn (OrderStatus $state): string => $state->label())
                    ->color(fn (OrderStatus $state): string => $state->color()),
                // Solo se marca lo que hay que revisar; el resto, en blanco.
                IconColumn::make('review_reason')->label('Revisar')
                    ->icon(fn (Order $record): ?Heroicon => $record->review_reason !== null ? Heroicon::OutlinedExclamationTriangle : null)
                    ->color('danger')->tooltip(fn (Order $record): ?string => $record->review_reason),
            ])
            ->filters([
                SelectFilter::make('status')->label('Estado')->options(Fields::options(OrderStatus::class)),
                Filter::make('review')->label('A revisar')->query(fn (Builder $query) => $query->whereNotNull('review_reason')),
                TernaryFilter::make('is_test')->label('Compras de prueba'),
            ])
            ->recordActions([ViewAction::make()])
            ->recordUrl(fn (Order $record): string => self::getUrl('view', ['record' => $record]))
            ->defaultSort('created_at', 'desc');
    }

    public static function infolist(Schema $schema): Schema
    {
        $money = fn (?int $state): string => Money::fromCents((int) $state)->format();

        return $schema->columns(3)->components([
            Callout::make('A revisar')
                ->description(fn (Order $record): ?string => $record->review_reason)
                ->danger()
                ->visible(fn (Order $record): bool => $record->review_reason !== null)
                ->columnSpanFull(),
            Callout::make('Compra de prueba')
                ->description('Hecha con el pago simulado o en una tienda sin publicar: no hay dinero real.')
                ->warning()
                ->visible(fn (Order $record): bool => $record->is_test)
                ->columnSpanFull(),

            Section::make('Artículos')->columnSpan(2)->schema([
                RepeatableEntry::make('lines')->hiddenLabel()->table([
                    RepeatableEntry\TableColumn::make('Artículo'),
                    RepeatableEntry\TableColumn::make('Tienda'),
                    RepeatableEntry\TableColumn::make('Ud.')->alignEnd(),
                    RepeatableEntry\TableColumn::make('Importe')->alignEnd(),
                ])->schema([
                    TextEntry::make('product_name')->helperText(fn (OrderLine $record): string => trim($record->sku.' · '.$record->label(), ' ·')),
                    TextEntry::make('store_name'),
                    TextEntry::make('quantity')->alignEnd(),
                    TextEntry::make('total_cents')->formatStateUsing($money)->alignEnd(),
                ]),
                Grid::make(4)->schema([
                    TextEntry::make('subtotal_cents')->label('Subtotal')->formatStateUsing($money),
                    TextEntry::make('shipping_cents')->label('Envío')->formatStateUsing(fn (int $state, Order $record): string => $record->shipping_free ? 'Gratis' : $money($state)),
                    TextEntry::make('vat_cents')->label('IVA incluido')->formatStateUsing($money),
                    TextEntry::make('total_cents')->label('Total')->formatStateUsing($money)->weight('bold'),
                ]),
            ]),

            Section::make('Estado')->columnSpan(1)->schema([
                TextEntry::make('status')->label('Estado')->badge()
                    ->formatStateUsing(fn (OrderStatus $state): string => $state->label())
                    ->color(fn (OrderStatus $state): string => $state->color()),
                TextEntry::make('created_at')->label('Creado')->dateTime('d/m/Y H:i'),
                TextEntry::make('paid_at')->label('Pagado')->dateTime('d/m/Y H:i')->placeholder('—'),
                TextEntry::make('production_started_at')->label('Fabricación empezada')->dateTime('d/m/Y H:i')->visible(fn (Order $record): bool => $record->production_started_at !== null),
                TextEntry::make('ready_at')->label('Listo para enviar')->dateTime('d/m/Y H:i')->visible(fn (Order $record): bool => $record->ready_at !== null),
                TextEntry::make('shipped_at')->label('Enviado')->dateTime('d/m/Y H:i')->visible(fn (Order $record): bool => $record->shipped_at !== null),
                TextEntry::make('tracking_number')->label('Seguimiento')
                    ->state(fn (Order $record): string => $record->tracking_carrier.' · '.$record->tracking_number)
                    ->url(fn (Order $record): ?string => $record->tracking_url, shouldOpenInNewTab: true)
                    ->visible(fn (Order $record): bool => $record->tracking_number !== null),
                TextEntry::make('delivered_at')->label('Entregado')->dateTime('d/m/Y H:i')->visible(fn (Order $record): bool => $record->delivered_at !== null),
                TextEntry::make('refunded')->label('Devuelto')
                    ->state(fn (Order $record): string => Money::fromCents(app(Refunds::class)->refundedCents($record->id))->format())
                    ->visible(fn (Order $record): bool => app(Refunds::class)->refundedCents($record->id) > 0),
                TextEntry::make('payment')->label('Cobro')->state(function (Order $record): string {
                    $payment = Payment::query()->where('order_id', $record->id)->latest('id')->first();

                    return $payment === null ? '—' : $payment->status->label().' · '.$payment->provider.' · '.$payment->provider_reference;
                }),
                TextEntry::make('production')->label('Fabricación + envío')
                    ->state(fn (Order $record): string => "{$record->lead_time_min_days}–{$record->lead_time_max_days} días + {$record->delivery_min_days}–{$record->delivery_max_days} días ({$record->shipping_carrier})"),
            ]),

            Section::make('Cliente y entrega')->columnSpan(2)->columns(2)->schema([
                TextEntry::make('customer_name')->label('Nombre'),
                // Como WooCommerce: «Cliente» con su ficha o «Invitado».
                TextEntry::make('user_id')->label('Cuenta')
                    ->state(fn (Order $record): string => $record->user_id === null ? 'Invitado' : (User::query()->find($record->user_id)->name ?? 'Cuenta borrada'))
                    ->url(fn (Order $record): ?string => $record->user_id !== null && User::query()->whereKey($record->user_id)->role(Role::Customer->value)->exists()
                        ? CustomerResource::getUrl('view', ['record' => $record->user_id]) : null)
                    ->color(fn (Order $record): string => $record->user_id === null ? 'gray' : 'primary'),
                TextEntry::make('email')->label('Email')->copyable(),
                TextEntry::make('phone')->label('Teléfono')->copyable(),
                TextEntry::make('address')->label('Dirección')->state(fn (Order $record): string => implode(', ', array_filter([
                    $record->address_line1, $record->address_line2, $record->postal_code.' '.$record->city, $record->province, Countries::ALL[$record->country] ?? $record->country,
                ]))),
                TextEntry::make('customer_note')->label('Nota del cliente')->placeholder('—')->columnSpanFull(),
            ]),

            Section::make('Devoluciones')->columnSpan(2)
                ->visible(fn (Order $record): bool => ReturnRequest::query()->where('order_id', $record->id)->exists())
                ->schema([
                    TextEntry::make('returns')->hiddenLabel()->html()->state(fn (Order $record): string => ReturnRequest::query()->where('order_id', $record->id)->get()
                        ->map(fn (ReturnRequest $r): string => '<a href="'.e(ReturnResource::getUrl('view', ['record' => $r])).'" style="text-decoration:underline">'.e((string) $r->reference).'</a> · '
                            .e($r->status->label()).' · '.e(Money::fromCents($r->refund_cents)->format()))
                        ->implode('<br>')),
                ]),

            Section::make('Facturas')->columnSpan(2)
                ->visible(fn (Order $record): bool => Invoice::query()->where('order_id', $record->id)->exists())
                ->schema([
                    TextEntry::make('invoices')->hiddenLabel()->html()->state(fn (Order $record): string => Invoice::query()->where('order_id', $record->id)->orderBy('id')->get()
                        ->map(fn (Invoice $i): string => '<a href="'.e(InvoiceResource::getUrl('view', ['record' => $i])).'" style="text-decoration:underline">'.e($i->code).'</a> · '
                            .e($i->type->label()).' · '.e(Money::fromCents($i->total_cents)->format()))
                        ->implode('<br>')),
                ]),

            Section::make('Historial')->columnSpan(1)->schema([
                RepeatableEntry::make('events')->hiddenLabel()->schema([
                    TextEntry::make('type')->hiddenLabel()
                        ->formatStateUsing(fn (string $state, OrderEvent $record): string => $record->created_at->format('d/m H:i').' · '.$record->label())
                        ->helperText(fn (OrderEvent $record): ?string => $record->note),
                ]),
            ]),
        ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['lines', 'events']);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListOrders::route('/'),
            'view' => ViewOrder::route('/{record}'),
        ];
    }
}
