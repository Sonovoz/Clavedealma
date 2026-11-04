<?php

namespace App\Filament\Resources\Carriers;

use App\Filament\Resources\Carriers\Pages\CreateCarrier;
use App\Filament\Resources\Carriers\Pages\EditCarrier;
use App\Filament\Resources\Carriers\Pages\ListCarriers;
use App\Modules\Shipping\Infrastructure\Models\Carrier;
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
use UnitEnum;

/**
 * Empresas de transporte con las que trabaja Clave de Alma (NACEX, GLS…). Solo superadmin.
 */
class CarrierResource extends Resource
{
    protected static ?string $slug = 'transportistas';

    protected static ?string $model = Carrier::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTruck;

    protected static string|UnitEnum|null $navigationGroup = 'Envíos';

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'transportista';

    protected static ?string $pluralModelLabel = 'transportistas';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            TextInput::make('name')->label('Empresa')->required()->maxLength(80)->unique(ignoreRecord: true),
            Toggle::make('active')->label('Trabajamos con ella')->default(true)->inline(false),
            TextInput::make('tracking_url')
                ->label('Enlace de seguimiento')
                ->placeholder('https://…/seguimiento?envio={codigo}')
                ->helperText('Pon {codigo} donde va el número de envío. El cliente lo recibirá en su email.')
                ->url()
                ->columnSpanFull(),
            Textarea::make('notes')->label('Notas internas')->placeholder('Contacto, horario de recogida, número de cliente…')->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Empresa')->searchable()->sortable(),
                TextColumn::make('tracking_url')->label('Seguimiento')->limit(40)->placeholder('—'),
                IconColumn::make('active')->label('Activa')->boolean(),
            ])
            ->recordActions([EditAction::make()])
            ->defaultSort('name');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCarriers::route('/'),
            'create' => CreateCarrier::route('/create'),
            'edit' => EditCarrier::route('/{record}/edit'),
        ];
    }
}
