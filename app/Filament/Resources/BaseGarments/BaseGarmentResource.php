<?php

namespace App\Filament\Resources\BaseGarments;

use App\Filament\Resources\BaseGarments\Pages\CreateBaseGarment;
use App\Filament\Resources\BaseGarments\Pages\EditBaseGarment;
use App\Filament\Resources\BaseGarments\Pages\ListBaseGarments;
use App\Modules\Catalog\Infrastructure\Models\BaseGarment;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class BaseGarmentResource extends Resource
{
    protected static ?string $slug = 'prendas-base';

    protected static ?string $model = BaseGarment::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSwatch;

    protected static string|UnitEnum|null $navigationGroup = 'Catálogo';

    protected static ?int $navigationSort = 3;

    protected static ?string $modelLabel = 'prenda base';

    protected static ?string $pluralModelLabel = 'prendas base';

    protected static ?string $navigationLabel = 'Prendas base';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            TextInput::make('code')->label('Código')->required()->maxLength(30)->unique(ignoreRecord: true)->placeholder('SUD-320'),
            TextInput::make('name')->label('Nombre')->required()->placeholder('Sudadera con capucha 320 g'),
            TextInput::make('material')->label('Material')->placeholder('Algodón orgánico'),
            TextInput::make('weight_grams')->label('Peso')->numeric()->suffix('g')->helperText('Sirve para calcular el envío.'),
            Textarea::make('description')->label('Descripción')->columnSpanFull(),
            Toggle::make('active')->label('Disponible para nuevos productos')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->withCount('products'))
            ->columns([
                TextColumn::make('code')->label('Código')->searchable()->sortable(),
                TextColumn::make('name')->label('Nombre')->searchable(),
                TextColumn::make('material')->label('Material')->toggleable(),
                TextColumn::make('products_count')->label('Productos')->alignEnd(),
                IconColumn::make('active')->label('Activa')->boolean(),
            ])
            ->recordActions([EditAction::make()])
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
