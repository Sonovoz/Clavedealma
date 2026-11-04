<?php

namespace App\Filament\Resources\ShippingRates;

use App\Filament\Resources\ShippingRates\Pages\EditShippingRate;
use App\Filament\Resources\ShippingRates\Pages\ListShippingRates;
use App\Filament\Support\Fields;
use App\Modules\Shipping\Contracts\ShippingZone;
use App\Modules\Shipping\Infrastructure\Models\Carrier;
use App\Modules\Shipping\Infrastructure\Models\ZoneRate;
use App\Support\Money;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/**
 * Zonas de envío (como en WooCommerce): transportista elegido por Clave de Alma, baremo por peso,
 * envío gratis y plazo. Los destinos son fijos (no se crean ni se borran). Solo superadmin.
 */
class ShippingRateResource extends Resource
{
    protected static ?string $slug = 'tarifas-envio';

    protected static ?string $model = ZoneRate::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMapPin;

    protected static string|UnitEnum|null $navigationGroup = 'Ajustes';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Zonas de envío';

    protected static ?string $modelLabel = 'zona de envío';

    protected static ?string $pluralModelLabel = 'zonas de envío';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            Section::make('Destino')
                ->columns(2)
                ->schema([
                    TextEntry::make('zone')->label('Destino')->state(fn (ZoneRate $record): string => $record->zone->label()),
                    Toggle::make('active')
                        ->label('Enviamos a este destino')
                        ->helperText('Necesita transportista y al menos un tramo de peso.')
                        ->inline(false),
                    Select::make('carrier_id')
                        ->label('Transportista')
                        ->options(fn (): array => Carrier::query()->where('active', true)->orderBy('name')->pluck('name', 'id')->all())
                        ->helperText('Lo elige Clave de Alma. El cliente no lo ve al pagar.')
                        ->requiredIf('active', true),
                    Fields::money('free_from_cents')
                        ->label('Envío gratis desde')
                        ->helperText('Importe de la compra con IVA. Vacío = nunca gratis.'),
                    TextInput::make('min_days')->label('Plazo de entrega mínimo')->numeric()->minValue(0)->maxValue(30)->suffix('días laborables')->required(),
                    TextInput::make('max_days')->label('Plazo de entrega máximo')->numeric()->minValue(0)->maxValue(30)->suffix('días laborables')->required()->gte('min_days'),
                ]),

            Section::make('Baremo por peso')
                ->description('Precio del envío con IVA según el peso total del paquete. Se aplica el primer tramo que lo cubre.')
                ->schema([
                    Repeater::make('bands')
                        ->hiddenLabel()
                        ->relationship()
                        ->orderColumn('position')
                        ->defaultItems(0)
                        ->addActionLabel('Añadir tramo')
                        ->table([TableColumn::make('Hasta'), TableColumn::make('Precio con IVA')])
                        ->schema([
                            Fields::kilograms('max_weight_grams')->required(),
                            Fields::money('price_cents')->required(),
                        ])
                        ->requiredIf('active', true)
                        ->minItems(fn ($get): int => $get('active') ? 1 : 0),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['carrier', 'bands']))
            ->columns([
                TextColumn::make('zone')->label('Destino')->formatStateUsing(fn (ShippingZone $state): string => $state->label()),
                TextColumn::make('carrier.name')->label('Transportista')->placeholder('Sin elegir'),
                TextColumn::make('bands')
                    ->label('Baremo')
                    ->state(fn (ZoneRate $record): string => $record->bands->isEmpty()
                        ? 'Sin tramos'
                        : $record->bands->count().' tramos · desde '.Money::fromCents((int) $record->bands->min('price_cents'))->format()),
                TextColumn::make('free_from_cents')
                    ->label('Gratis desde')
                    ->formatStateUsing(fn (?int $state): string => $state === null ? '—' : Money::fromCents($state)->format())
                    ->placeholder('—'),
                TextColumn::make('min_days')->label('Plazo')->formatStateUsing(fn (ZoneRate $record): string => "{$record->min_days}–{$record->max_days} días"),
                IconColumn::make('active')->label('Activo')->boolean(),
            ])
            ->recordActions([EditAction::make()])
            ->paginated(false);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListShippingRates::route('/'),
            'edit' => EditShippingRate::route('/{record}/edit'),
        ];
    }
}
