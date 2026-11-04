<?php

namespace App\Filament\Resources\BaseGarments;

use App\Filament\Resources\BaseGarments\Pages\CreateBaseGarment;
use App\Filament\Resources\BaseGarments\Pages\EditBaseGarment;
use App\Filament\Resources\BaseGarments\Pages\ListBaseGarments;
use App\Filament\Resources\Products\ProductResource;
use App\Filament\Support\ActiveStore;
use App\Filament\Support\ScopedToActiveStore;
use App\Modules\Catalog\Domain\SizeScale;
use App\Modules\Catalog\Infrastructure\Models\BaseGarment;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class BaseGarmentResource extends Resource
{
    use ScopedToActiveStore;

    protected static ?string $slug = 'prendas-base';

    protected static ?string $model = BaseGarment::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSwatch;

    protected static string|UnitEnum|null $navigationGroup = 'Productos';

    protected static ?int $navigationSort = 3;

    protected static ?string $modelLabel = 'prenda base';

    protected static ?string $pluralModelLabel = 'prendas base';

    protected static ?string $navigationLabel = 'Prendas base';

    protected static ?string $recordTitleAttribute = 'name';

    /** Cortes habituales (se puede escribir otro). */
    public const FITS = ['Regular', 'Oversize', 'Boxy', 'Slim', 'Relaxed'];

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            Section::make('Prenda')
                ->columns(2)
                ->schema([
                    TextInput::make('code')->label('Código')->required()->maxLength(30)->unique(ignoreRecord: true, modifyRuleUsing: fn ($rule) => $rule->where('store_id', ActiveStore::id()))->placeholder('SUD-320'),
                    TextInput::make('name')->label('Nombre')->required()->placeholder('Sudadera con capucha 320 g'),
                    TextInput::make('material')->label('Tejido')->placeholder('Felpa perchada 320 g/m²'),
                    Textarea::make('description')->label('Descripción interna')->columnSpanFull(),
                    Toggle::make('active')->label('Disponible para nuevos productos')->default(true),
                ]),

            Section::make('Ficha textil')
                ->description('Se muestra en todos los artículos hechos con esta prenda.')
                ->columns(2)
                ->schema([
                    TextInput::make('composition')
                        ->label('Composición')
                        ->placeholder('100 % algodón')
                        ->helperText('Obligatoria para publicar (Reglamento UE 1007/2011).')
                        ->maxLength(255)
                        ->columnSpanFull(),
                    TextInput::make('fit')->label('Corte')->datalist(self::FITS)->maxLength(40),
                    TextInput::make('origin_country')->label('País de fabricación')->placeholder('España')->maxLength(60),
                    Textarea::make('care')->label('Cuidados')->placeholder("Lavar a 30 °C del revés.\nNo usar secadora.")->rows(3)->columnSpanFull(),
                ]),

            Section::make('Envío')
                ->description('El precio del envío lo calcula el baremo de cada destino (Ajustes → Zonas de envío) con el peso del pedido.')
                ->columnSpanFull()
                ->schema([
                    TextInput::make('weight_grams')
                        ->label('Peso para el envío')
                        ->numeric()
                        ->minValue(1)
                        ->maxValue(30000)
                        ->suffix('g')
                        ->helperText('La prenda doblada con su bolsa, tal como sale del taller.'),
                ]),

            Section::make('Guía de tallas')
                ->description('Medidas de la prenda en cm, tomadas en plano. La tienda las enseña en «Ver guía de tallas».')
                ->columnSpanFull()
                ->schema([
                    Repeater::make('sizes')
                        ->hiddenLabel()
                        ->relationship()
                        ->orderColumn('position')
                        ->defaultItems(0)
                        ->addActionLabel('Añadir talla')
                        ->table([
                            TableColumn::make('Talla'),
                            TableColumn::make('Ancho de pecho (cm)'),
                            TableColumn::make('Largo (cm)'),
                            TableColumn::make('Manga (cm)'),
                        ])
                        ->schema([
                            TextInput::make('size')->required()->maxLength(20)->datalist(SizeScale::STANDARD),
                            self::measure('chest_cm'),
                            self::measure('length_cm'),
                            self::measure('sleeve_cm'),
                        ]),
                    TextInput::make('size_guide_note')
                        ->label('Nota de la guía')
                        ->placeholder('Gorra de talla única con cierre ajustable (54–60 cm).')
                        ->maxLength(255),
                ]),
        ]);
    }

    private static function measure(string $name): TextInput
    {
        return TextInput::make($name)->numeric()->minValue(0)->maxValue(300)->step(0.5);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->withCount(['products', 'sizes']))
            ->columns([
                TextColumn::make('code')->label('Código')->searchable()->sortable(),
                TextColumn::make('name')->label('Nombre')->searchable(),
                TextColumn::make('composition')->label('Composición')->placeholder('Falta')->toggleable(),
                TextColumn::make('sizes_count')->label('Tallas en guía')->alignEnd()->toggleable(),
                TextColumn::make('products_count')->label('Productos')->alignEnd(),
                IconColumn::make('active')->label('Activa')->boolean(),
            ])
            ->recordActions([
                EditAction::make(),
                Action::make('newArticle')
                    ->label('Crear artículo')
                    ->icon(Heroicon::OutlinedPlus)
                    ->url(fn (BaseGarment $record): string => ProductResource::getUrl('create', ['prenda' => $record->getKey()])),
            ])
            ->defaultSort('code');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBaseGarments::route('/'),
            'create' => CreateBaseGarment::route('/create'),
            'edit' => EditBaseGarment::route('/{record}/edit'),
        ];
    }
}
