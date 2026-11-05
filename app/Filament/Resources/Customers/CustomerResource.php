<?php

namespace App\Filament\Resources\Customers;

use App\Filament\Resources\Customers\Pages\ListCustomers;
use App\Filament\Resources\Customers\Pages\ViewCustomer;
use App\Filament\Resources\Orders\OrderResource;
use App\Modules\Identity\Contracts\Role;
use App\Modules\Identity\Infrastructure\Models\User;
use App\Modules\Orders\Contracts\OrderStatus;
use App\Modules\Orders\Infrastructure\Models\Order;
use App\Storefront\Account\CustomerOrders;
use App\Storefront\Checkout\Countries;
use App\Support\Money;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/**
 * «Clientes» como WooCommerce → Clientes: quién tiene cuenta, cuántos pedidos ha hecho,
 * cuánto ha gastado y cuándo compró por última vez (ADR 0021). Solo lectura y solo
 * superadmin (son datos de clientes). Los pedidos sin cuenta están en Pedidos.
 */
class CustomerResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $slug = 'clientes';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static string|UnitEnum|null $navigationGroup = 'Comercio';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Clientes';

    protected static ?string $modelLabel = 'cliente';

    protected static ?string $pluralModelLabel = 'clientes';

    protected static ?string $recordTitleAttribute = 'name';

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user instanceof User && $user->isSuperadmin();
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    /**
     * @return Builder<User>
     */
    public static function getEloquentQuery(): Builder
    {
        return User::query()->role(Role::Customer->value)->with('address');
    }

    /**
     * Pedidos pagados del cliente (los mismos que ve en «Mi cuenta»), también los de prueba,
     * como en WooCommerce. Las cifras de ventas sin pruebas están en el Escritorio e Informes.
     *
     * @return Builder<Order>
     */
    private static function paid(User $user): Builder
    {
        return CustomerOrders::query($user)->whereIn('status', OrderStatus::paid());
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Nombre')->searchable()->sortable()->weight('bold'),
                TextColumn::make('email')->label('Email')->searchable()->copyable(),
                IconColumn::make('email_verified_at')->label('Email confirmado')->boolean()
                    ->state(fn (User $record): bool => $record->email_verified_at !== null),
                TextColumn::make('orders')->label('Pedidos')->alignEnd()
                    ->state(fn (User $record): int => self::paid($record)->count()),
                TextColumn::make('spent')->label('Total gastado')->alignEnd()
                    ->state(fn (User $record): string => Money::fromCents((int) self::paid($record)->sum('total_cents'))->format()),
                TextColumn::make('last_order')->label('Último pedido')
                    ->state(fn (User $record): ?string => CustomerOrders::query($record)->latest('id')->first()?->created_at->timezone('Europe/Madrid')->format('d/m/Y')),
                TextColumn::make('address.country')->label('País')
                    ->formatStateUsing(fn (?string $state): string => $state === null ? '—' : (Countries::ALL[$state] ?? $state))->toggleable(),
                TextColumn::make('created_at')->label('Registro')->date('d/m/Y')->sortable(),
            ])
            ->filters([
                TernaryFilter::make('email_verified_at')->label('Email confirmado')->nullable(),
            ])
            ->recordActions([ViewAction::make()])
            ->defaultSort('created_at', 'desc');
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->columns(3)->components([
            Section::make('Cliente')->columnSpan(1)->schema([
                TextEntry::make('name')->label('Nombre'),
                TextEntry::make('email')->label('Email')->copyable(),
                IconEntry::make('verified')->label('Email confirmado')->boolean()
                    ->state(fn (User $record): bool => $record->email_verified_at !== null),
                TextEntry::make('created_at')->label('Registro')->dateTime('d/m/Y H:i'),
                TextEntry::make('spent')->label('Total gastado')
                    ->state(fn (User $record): string => Money::fromCents((int) self::paid($record)->sum('total_cents'))->format()),
            ]),
            Section::make('Dirección de facturación y envío')->columnSpan(2)->schema([
                TextEntry::make('address_text')->hiddenLabel()
                    ->state(function (User $record): string {
                        $a = $record->address;

                        return $a === null ? 'Todavía no ha guardado ninguna dirección.'
                            : implode(' · ', array_filter([$a->name, $a->address_line1, $a->address_line2, trim($a->postal_code.' '.$a->city), $a->province, Countries::ALL[$a->country] ?? $a->country, $a->phone]));
                    }),
            ]),
            Section::make('Pedidos')->columnSpanFull()->schema([
                RepeatableEntry::make('customer_orders')->hiddenLabel()
                    ->state(fn (User $record): array => CustomerOrders::query($record)->latest('id')->limit(50)->get()
                        ->map(fn (Order $o): array => [
                            'link' => '<a class="font-semibold underline" href="'.e(OrderResource::getUrl('view', ['record' => $o])).'">'.e((string) $o->reference).'</a>',
                            'date' => $o->created_at->timezone('Europe/Madrid')->format('d/m/Y'),
                            'status' => $o->status->label(),
                            'total' => Money::fromCents($o->total_cents)->format(),
                        ])->all())
                    ->table([
                        RepeatableEntry\TableColumn::make('Pedido'),
                        RepeatableEntry\TableColumn::make('Fecha'),
                        RepeatableEntry\TableColumn::make('Estado'),
                        RepeatableEntry\TableColumn::make('Total'),
                    ])
                    ->schema([
                        TextEntry::make('link')->html(),
                        TextEntry::make('date'),
                        TextEntry::make('status')->badge(),
                        TextEntry::make('total'),
                    ])
                    ->placeholder('Sin pedidos todavía.'),
            ]),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCustomers::route('/'),
            'view' => ViewCustomer::route('/{record}'),
        ];
    }
}
