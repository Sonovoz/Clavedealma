<?php

namespace App\Filament\Resources\Coupons;

use App\Filament\Resources\Coupons\Pages\CreateCoupon;
use App\Filament\Resources\Coupons\Pages\EditCoupon;
use App\Filament\Resources\Coupons\Pages\ListCoupons;
use App\Modules\Promotions\Contracts\CouponType;
use App\Modules\Promotions\Infrastructure\Models\Coupon;
use App\Modules\Stores\Infrastructure\Models\Store;
use App\Support\Money;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/**
 * Marketing → Cupones, como en WooCommerce (ADR 0024): «Datos del cupón» con las pestañas
 * General, Restricción de uso y Límites de uso. Solo superadmin. El importe se guarda en
 * puntos básicos (porcentaje) o céntimos (fijo); aquí se escribe en % o en euros.
 */
class CouponResource extends Resource
{
    protected static ?string $model = Coupon::class;

    protected static ?string $slug = 'cupones';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTicket;

    protected static string|UnitEnum|null $navigationGroup = 'Marketing';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Cupones';

    protected static ?string $modelLabel = 'cupón';

    protected static ?string $pluralModelLabel = 'cupones';

    protected static ?string $recordTitleAttribute = 'code';

    public static function form(Schema $schema): Schema
    {
        $percent = fn (Get $get): bool => ($get('type') ?? CouponType::Percent->value) === CouponType::Percent->value;

        return $schema->columns(1)->components([
            TextInput::make('code')->label('Código del cupón')->required()->maxLength(40)
                ->regex('/^[A-Za-z0-9_-]+$/')->validationMessages(['regex' => 'Solo letras, números, guiones y guiones bajos.'])
                ->unique(ignoreRecord: true)
                ->dehydrateStateUsing(fn (?string $state): string => Coupon::normalize((string) $state))
                ->helperText('Lo que escribe el cliente en el carrito. Da igual mayúsculas o minúsculas.'),
            TextInput::make('description')->label('Descripción (opcional)')->maxLength(255)
                ->helperText('Solo para el equipo: para qué es este cupón.'),
            Tabs::make('Datos del cupón')->tabs([
                Tab::make('General')->icon(Heroicon::OutlinedCog6Tooth)->columns(2)->schema([
                    Select::make('type')->label('Tipo de descuento')->options(collect(CouponType::cases())->mapWithKeys(fn (CouponType $t): array => [$t->value => $t->label()])->all())
                        ->default(CouponType::Percent->value)->selectablePlaceholder(false)->live()->required(),
                    TextInput::make('amount_input')->label(fn (Get $get): string => $percent($get) ? 'Importe del cupón (%)' : 'Importe del cupón (€)')
                        ->required()->rule('regex:/^\d{1,6}([.,]\d{1,2})?$/')->validationMessages(['regex' => 'Escribe un número, como 15 o 15,5.'])
                        ->helperText(fn (Get $get): string => ($percent($get) ? 'Hasta 100. ' : '').'0 si el cupón solo da el envío gratis.'),
                    Toggle::make('free_shipping')->label('Permitir el envío gratuito')->inline(false)
                        ->helperText('El envío sale gratis con este cupón.'),
                    Toggle::make('active')->label('Activo')->default(true)->inline(false),
                    DateTimePicker::make('starts_at')->label('Válido desde (opcional)')->native(false)->seconds(false)->displayFormat('d/m/Y H:i'),
                    DatePicker::make('expires_on')->label('Fecha de caducidad (opcional)')->native(false)->displayFormat('d/m/Y')
                        ->helperText('Vale hasta las 23:59 de ese día.'),
                ]),
                Tab::make('Restricción de uso')->icon(Heroicon::OutlinedNoSymbol)->columns(2)->schema([
                    TextInput::make('minimum_input')->label('Gasto mínimo (€, opcional)')
                        ->rule('regex:/^\d{1,6}([.,]\d{1,2})?$/')->validationMessages(['regex' => 'Escribe un importe, como 40 o 39,90.'])
                        ->helperText('Subtotal del carrito, IVA incluido, para poder usarlo.'),
                    Select::make('store_id')->label('Tienda')->placeholder('Todas las tiendas')
                        ->options(fn (): array => Store::query()->orderBy('name')->pluck('name', 'id')->all())
                        ->helperText('Si eliges una, solo descuenta los artículos de esa tienda.'),
                    Toggle::make('exclude_sale_items')->label('Excluir los artículos en oferta')->inline(false)
                        ->helperText('No descuenta lo que ya está rebajado.'),
                ]),
                Tab::make('Límites de uso')->icon(Heroicon::OutlinedHashtag)->columns(2)->schema([
                    TextInput::make('usage_limit')->label('Límite de uso por cupón')->numeric()->integer()->minValue(1)
                        ->placeholder('Uso ilimitado')->helperText('Cuántas veces se puede usar en total (pedidos pagados).'),
                    TextInput::make('usage_limit_per_customer')->label('Límite de uso por usuario')->numeric()->integer()->minValue(1)
                        ->placeholder('Uso ilimitado')->helperText('Cuántas veces puede usarlo cada cliente (por su email).'),
                ]),
            ]),
        ]);
    }

    /**
     * Del formulario (% o €) a lo que se guarda (puntos básicos o céntimos).
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function toStorage(array $data): array
    {
        $type = CouponType::tryFrom((string) ($data['type'] ?? '')) ?? CouponType::Percent;
        // Sin float (ADR 0005): «15,5» % son 1550 puntos básicos, igual que «15,5» € son 1550 céntimos.
        $amount = trim((string) ($data['amount_input'] ?? '0'));
        $data['amount'] = Money::parse($amount === '' ? '0' : $amount)->cents;
        if ($type === CouponType::Percent) {
            $data['amount'] = min(10000, $data['amount']);
        }
        $minimum = trim((string) ($data['minimum_input'] ?? ''));
        $data['minimum_cents'] = $minimum === '' ? null : Money::parse($minimum)->cents;
        unset($data['amount_input'], $data['minimum_input']);

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function toForm(array $data): array
    {
        $decimal = fn (int $v): string => str_replace(',', '.', Money::fromCents($v)->decimal());
        $data['amount_input'] = $decimal((int) ($data['amount'] ?? 0));
        $data['minimum_input'] = isset($data['minimum_cents']) ? $decimal((int) $data['minimum_cents']) : null;

        return $data;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')->label('Código')->searchable()->weight('bold')->copyable()
                    ->description(fn (Coupon $record): ?string => $record->description),
                TextColumn::make('type')->label('Tipo de cupón')->formatStateUsing(fn (CouponType $state): string => $state->label()),
                TextColumn::make('amount')->label('Importe del cupón')->state(fn (Coupon $record): string => $record->label()),
                TextColumn::make('store_id')->label('Tienda')
                    ->formatStateUsing(fn (?int $state): string => $state === null ? 'Todas' : (string) Store::query()->whereKey($state)->value('name'))
                    ->placeholder('Todas'),
                TextColumn::make('usage')->label('Uso / Límite')
                    ->state(fn (Coupon $record): string => $record->uses().' / '.($record->usage_limit ?? '∞')),
                TextColumn::make('expires_on')->label('Fecha de caducidad')->date('d/m/Y')->placeholder('—')->sortable(),
                IconColumn::make('active')->label('Activo')->boolean(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()->visible(fn (Coupon $record): bool => ! $record->redemptions()->exists()),
            ])
            ->defaultSort('created_at', 'desc')
            ->emptyStateHeading('Todavía no hay cupones')
            ->emptyStateDescription('Crea uno con «Crear cupón»: el cliente lo escribe en el carrito.');
    }

    /** Un cupón ya usado no se borra (se desactiva): sus usos quedan en los pedidos. */
    public static function canDelete(Model $record): bool
    {
        return $record instanceof Coupon && ! $record->redemptions()->exists() && parent::canDelete($record);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCoupons::route('/'),
            'create' => CreateCoupon::route('/crear'),
            'edit' => EditCoupon::route('/{record}/editar'),
        ];
    }
}
