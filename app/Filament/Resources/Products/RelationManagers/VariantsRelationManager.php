<?php

namespace App\Filament\Resources\Products\RelationManagers;

use App\Filament\Support\Fields;
use App\Modules\Catalog\Domain\Sku;
use App\Modules\Catalog\Infrastructure\Models\Product;
use App\Modules\Stores\Infrastructure\Models\Store;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\ColorColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Variantes (talla y color) de un producto.
 */
class VariantsRelationManager extends RelationManager
{
    protected static string $relationship = 'variants';

    protected static ?string $title = 'Variantes';

    protected static ?string $modelLabel = 'variante';

    protected static ?string $pluralModelLabel = 'variantes';

    public function form(Schema $schema): Schema
    {
        $suggest = function (Get $get, Set $set): void {
            /** @var Product $product */
            $product = $this->getOwnerRecord();
            $store = Store::query()->whereKey($product->store_id)->value('subdomain');
            $set('sku', Sku::suggest((string) $store, $product->slug, $get('size'), $get('color')));
        };

        return $schema->columns(2)->components([
            TextInput::make('size')->label('Talla')->maxLength(20)->live(onBlur: true)->afterStateUpdated($suggest),
            TextInput::make('color')->label('Color')->maxLength(40)->live(onBlur: true)->afterStateUpdated($suggest),
            ColorPicker::make('color_hex')->label('Muestra de color'),
            Fields::money('price_cents')->label('Precio propio con IVA')->helperText('Vacío = precio del producto.'),
            TextInput::make('sku')->label('Referencia (SKU)')->required()->maxLength(64)->unique(ignoreRecord: true)->helperText('Se propone sola al escribir talla y color.'),
            Toggle::make('active')->label('A la venta')->default(true),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('sku')
            ->reorderable('position')
            ->defaultSort('position')
            ->columns([
                TextColumn::make('sku')->label('SKU')->searchable(),
                TextColumn::make('size')->label('Talla'),
                TextColumn::make('color')->label('Color'),
                ColorColumn::make('color_hex')->label(''),
                Fields::moneyColumn('price_cents')->label('Precio propio'),
                IconColumn::make('active')->label('A la venta')->boolean(),
            ])
            ->headerActions([CreateAction::make()])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }
}
