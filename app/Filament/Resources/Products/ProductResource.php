<?php

namespace App\Filament\Resources\Products;

use App\Filament\Resources\Products\Pages\CreateProduct;
use App\Filament\Resources\Products\Pages\EditProduct;
use App\Filament\Resources\Products\Pages\ListProducts;
use App\Filament\Resources\Products\RelationManagers\VariantsRelationManager;
use App\Filament\Support\Fields;
use App\Modules\Catalog\Contracts\ProductStatus;
use App\Modules\Catalog\Infrastructure\Models\Product;
use App\Modules\Stores\Infrastructure\Models\Store;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;

class ProductResource extends Resource
{
    protected static ?string $slug = 'productos';

    protected static ?string $model = Product::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShoppingBag;

    protected static string|UnitEnum|null $navigationGroup = 'Catálogo';

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'producto';

    protected static ?string $pluralModelLabel = 'productos';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(3)->components([
            Section::make('Producto')
                ->columnSpan(2)
                ->columns(2)
                ->schema([
                    Select::make('store_id')
                        ->label('Tienda')
                        ->options(fn (): array => Store::query()->orderBy('name')->pluck('name', 'id')->all())
                        ->searchable()
                        ->required()
                        ->live(),
                    Select::make('base_garment_id')
                        ->label('Prenda base')
                        ->relationship('baseGarment', 'name', fn (Builder $query) => $query->where('active', true))
                        ->searchable()
                        ->preload(),
                    TextInput::make('reference')
                        ->label('Modelo (referencia)')
                        ->helperText('Clave del artículo en la hoja de importación. Única en la tienda.')
                        ->maxLength(40)
                        ->unique(ignoreRecord: true, modifyRuleUsing: fn ($rule, Get $get) => $rule->where('store_id', $get('store_id'))),
                    TextInput::make('name')->label('Nombre')->required()->maxLength(255),
                    TextInput::make('slug')
                        ->label('Dirección (slug)')
                        ->helperText('Se genera del nombre si se deja vacío.')
                        ->maxLength(255),
                    RichEditor::make('description')->label('Descripción')->columnSpanFull(),
                ]),

            Section::make('Venta')
                ->columnSpan(1)
                ->schema([
                    Select::make('status')
                        ->label('Estado')
                        ->options(Fields::options(ProductStatus::class))
                        ->default(ProductStatus::Draft->value)
                        ->required(),
                    Fields::money('price_cents')->label('Precio con IVA')->required(),
                    Fields::percent('vat_rate_bps')->label('IVA')->default(2100)->required(),
                    Fields::percent('commission_bps')
                        ->label('Comisión propia')
                        ->helperText('Vacío = comisión de la tienda.'),
                ]),

            Section::make('Fabricación')
                ->columnSpan(2)
                ->columns(2)
                ->schema([
                    TextInput::make('lead_time_min_days')->label('Plazo mínimo')->numeric()->minValue(0)->maxValue(60)->suffix('días')->default(5)->required(),
                    TextInput::make('lead_time_max_days')->label('Plazo máximo')->numeric()->minValue(0)->maxValue(60)->suffix('días')->default(7)->required()->gte('lead_time_min_days'),
                    Toggle::make('personalized')
                        ->label('Personalizado para cada cliente')
                        ->helperText('Sin derecho de desistimiento (por ejemplo, con el nombre del cliente).')
                        ->columnSpanFull(),
                    FileUpload::make('design_path')
                        ->label('Archivo del diseño para producción')
                        ->disk('local')
                        ->directory('designs')
                        ->visibility('private')
                        ->downloadable()
                        ->columnSpanFull(),
                ]),

            Section::make('Colecciones')
                ->columnSpan(1)
                ->schema([
                    Select::make('collections')
                        ->label('Colecciones')
                        ->relationship('collections', 'name', fn (Builder $query, Get $get) => $query->where('store_id', $get('store_id')))
                        ->multiple()
                        ->preload(),
                ]),

            Section::make('Imágenes')
                ->columnSpanFull()
                ->schema([
                    Repeater::make('images')
                        ->hiddenLabel()
                        ->relationship()
                        ->orderColumn('position')
                        ->grid(3)
                        ->defaultItems(0)
                        ->addActionLabel('Añadir imagen')
                        ->schema([
                            FileUpload::make('path')->label('Imagen')->image()->disk('public')->directory('products')->required(),
                            TextInput::make('alt')->label('Texto alternativo'),
                        ]),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query
                ->addSelect(['store_name' => Store::query()->select('name')->whereColumn('stores.id', 'products.store_id')])
                ->withCount('variants'))
            ->columns([
                TextColumn::make('reference')->label('Modelo')->searchable()->sortable()->placeholder('—'),
                TextColumn::make('name')->label('Producto')->searchable()->sortable(),
                TextColumn::make('store_name')->label('Tienda')->sortable(),
                Fields::moneyColumn('price_cents')->label('Precio'),
                TextColumn::make('variants_count')->label('Variantes')->alignEnd(),
                TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->formatStateUsing(fn (ProductStatus $state): string => $state->label())
                    ->color(fn (ProductStatus $state): string => $state->color()),
                TextColumn::make('updated_at')->label('Actualizado')->since()->sortable()->toggleable(),
            ])
            ->filters([
                SelectFilter::make('store_id')->label('Tienda')->options(fn (): array => Store::query()->orderBy('name')->pluck('name', 'id')->all()),
                SelectFilter::make('status')->label('Estado')->options(Fields::options(ProductStatus::class)),
                TrashedFilter::make(),
            ])
            ->recordActions([EditAction::make()])
            ->defaultSort('updated_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [VariantsRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProducts::route('/'),
            'create' => CreateProduct::route('/create'),
            'edit' => EditProduct::route('/{record}/edit'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()->withoutGlobalScopes([SoftDeletingScope::class]);
    }
}
