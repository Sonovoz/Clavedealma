<?php

namespace App\Filament\Resources\Collections;

use App\Filament\Resources\Collections\Pages\CreateCollection;
use App\Filament\Resources\Collections\Pages\EditCollection;
use App\Filament\Resources\Collections\Pages\ListCollections;
use App\Modules\Catalog\Infrastructure\Models\Collection;
use App\Modules\Stores\Infrastructure\Models\Store;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class CollectionResource extends Resource
{
    protected static ?string $slug = 'colecciones';

    protected static ?string $model = Collection::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSquares2x2;

    protected static string|UnitEnum|null $navigationGroup = 'Catálogo';

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'colección';

    protected static ?string $pluralModelLabel = 'colecciones';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            Select::make('store_id')
                ->label('Tienda')
                ->options(fn (): array => Store::query()->orderBy('name')->pluck('name', 'id')->all())
                ->searchable()
                ->required()
                ->live(),
            TextInput::make('name')->label('Nombre')->required()->maxLength(255),
            TextInput::make('slug')->label('Dirección (slug)')->helperText('Se genera del nombre si se deja vacío.'),
            TextInput::make('position')->label('Orden')->numeric()->default(0),
            Textarea::make('description')->label('Descripción')->columnSpanFull(),
            Select::make('products')
                ->label('Productos')
                ->relationship('products', 'name', fn (Builder $query, Get $get) => $query->where('store_id', $get('store_id')))
                ->multiple()
                ->preload()
                ->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query
                ->addSelect(['store_name' => Store::query()->select('name')->whereColumn('stores.id', 'collections.store_id')])
                ->withCount('products'))
            ->columns([
                TextColumn::make('name')->label('Colección')->searchable()->sortable(),
                TextColumn::make('store_name')->label('Tienda'),
                TextColumn::make('products_count')->label('Productos')->alignEnd(),
                TextColumn::make('position')->label('Orden')->sortable(),
            ])
            ->filters([
                SelectFilter::make('store_id')->label('Tienda')->options(fn (): array => Store::query()->orderBy('name')->pluck('name', 'id')->all()),
            ])
            ->recordActions([EditAction::make()])
            ->defaultSort('position');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCollections::route('/'),
            'create' => CreateCollection::route('/create'),
            'edit' => EditCollection::route('/{record}/edit'),
        ];
    }
}
