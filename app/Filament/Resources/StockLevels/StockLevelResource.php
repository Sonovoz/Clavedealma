<?php

namespace App\Filament\Resources\StockLevels;

use App\Filament\Resources\StockLevels\Pages\EditStockLevel;
use App\Filament\Resources\StockLevels\Pages\ListStockLevels;
use App\Filament\Resources\StockLevels\RelationManagers\MovementsRelationManager;
use App\Filament\Support\Fields;
use App\Modules\Inventory\Application\AdjustStock;
use App\Modules\Inventory\Contracts\StockMode;
use App\Modules\Inventory\Contracts\StockMovementReason;
use App\Modules\Inventory\Infrastructure\Models\StockLevel;
use App\Modules\Stores\Infrastructure\Models\Store;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use InvalidArgumentException;
use UnitEnum;

/**
 * Stock por variante. Las fichas se crean solas al crear una variante.
 * La tabla une catálogo y tiendas: esta es la capa de interfaz, no un módulo.
 */
class StockLevelResource extends Resource
{
    protected static ?string $slug = 'stock';

    protected static ?string $model = StockLevel::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArchiveBox;

    protected static string|UnitEnum|null $navigationGroup = 'Productos';

    protected static ?int $navigationSort = 4;

    protected static ?string $modelLabel = 'stock';

    protected static ?string $pluralModelLabel = 'inventario';

    protected static ?string $navigationLabel = 'Inventario';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            Select::make('mode')
                ->label('Modo de venta')
                ->options(Fields::options(StockMode::class))
                ->required()
                ->live(),
            TextInput::make('low_stock_threshold')
                ->label('Avisar cuando queden')
                ->numeric()
                ->minValue(0)
                ->suffix('unidades')
                ->visible(fn (Get $get): bool => $get('mode') !== StockMode::OnDemand->value && $get('mode') !== StockMode::OnDemand),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query
                ->join('product_variants', 'product_variants.id', '=', 'stock_levels.product_variant_id')
                ->join('products', 'products.id', '=', 'product_variants.product_id')
                ->join('stores', 'stores.id', '=', 'products.store_id')
                ->select('stock_levels.*', 'product_variants.sku', 'product_variants.size', 'product_variants.color', 'products.name as product_name', 'stores.name as store_name'))
            ->columns([
                TextColumn::make('product_name')->label('Producto')->searchable(query: fn (Builder $query, string $search) => $query->where('products.name', 'like', "%{$search}%")),
                TextColumn::make('variant')->label('Variante')->state(fn (StockLevel $record): string => implode(' · ', array_filter([$record->getAttribute('size'), $record->getAttribute('color')]))),
                TextColumn::make('store_name')->label('Tienda'),
                TextColumn::make('sku')
                    ->label('SKU')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->searchable(query: fn (Builder $query, string $search) => $query->where('product_variants.sku', 'like', "%{$search}%")),
                TextColumn::make('mode')->label('Modo')->badge()->formatStateUsing(fn (StockMode $state): string => $state->label())->color(fn (StockMode $state): string => $state === StockMode::OnDemand ? 'gray' : 'info'),
                TextColumn::make('quantity')
                    ->label('Unidades')
                    ->alignEnd()
                    ->sortable()
                    ->formatStateUsing(fn (int $state, StockLevel $record): string => $record->mode->tracksQuantity() ? (string) $state : '—')
                    ->color(fn (StockLevel $record): ?string => $record->isLow() ? 'danger' : null)
                    ->weight(fn (StockLevel $record): ?string => $record->isLow() ? 'bold' : null),
            ])
            ->filters([
                SelectFilter::make('mode')->label('Modo')->options(Fields::options(StockMode::class)),
                SelectFilter::make('store')
                    ->label('Tienda')
                    ->options(fn (): array => Store::query()->orderBy('name')->pluck('name', 'id')->all())
                    ->query(fn (Builder $query, array $data) => $query->when($data['value'] ?? null, fn (Builder $q, $id) => $q->where('products.store_id', $id))),
                Filter::make('low')
                    ->label('Stock bajo')
                    ->query(fn (Builder $query) => $query->where('stock_levels.mode', '!=', StockMode::OnDemand->value)
                        ->whereNotNull('stock_levels.low_stock_threshold')
                        ->whereColumn('stock_levels.quantity', '<=', 'stock_levels.low_stock_threshold')),
            ])
            ->recordActions([
                self::adjustAction(),
                EditAction::make()->label('Modo')->iconButton()->tooltip('Cambiar modo de venta'),
            ])
            ->defaultSort('product_name');
    }

    public static function adjustAction(): Action
    {
        return Action::make('adjust')
            ->label('Ajustar')
            ->icon(Heroicon::OutlinedPlusCircle)
            ->visible(fn (StockLevel $record): bool => $record->mode->tracksQuantity())
            ->schema([
                TextInput::make('delta')->label('Unidades (+ entrada, − salida)')->integer()->required()->notIn([0]),
                Select::make('reason')
                    ->label('Motivo')
                    ->options(Fields::options(StockMovementReason::class))
                    ->default(StockMovementReason::Production->value)
                    ->required(),
                TextInput::make('note')->label('Nota')->maxLength(255),
            ])
            ->action(function (StockLevel $record, array $data, AdjustStock $adjustStock): void {
                try {
                    $level = $adjustStock(
                        $record->product_variant_id,
                        (int) $data['delta'],
                        StockMovementReason::from($data['reason']),
                        $data['note'] ?? null,
                        auth()->id(),
                    );
                    Notification::make()->title("Stock actualizado: {$level->quantity} unidades")->success()->send();
                } catch (InvalidArgumentException $e) {
                    Notification::make()->title($e->getMessage())->danger()->send();
                }
            });
    }

    public static function getRelations(): array
    {
        return [MovementsRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListStockLevels::route('/'),
            'edit' => EditStockLevel::route('/{record}/edit'),
        ];
    }
}
