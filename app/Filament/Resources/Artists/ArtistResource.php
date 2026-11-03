<?php

namespace App\Filament\Resources\Artists;

use App\Filament\Resources\Artists\Pages\CreateArtist;
use App\Filament\Resources\Artists\Pages\EditArtist;
use App\Filament\Resources\Artists\Pages\ListArtists;
use App\Filament\Support\Fields;
use App\Modules\Identity\Contracts\Role;
use App\Modules\Identity\Infrastructure\Models\User;
use App\Modules\Stores\Contracts\TaxRegime;
use App\Modules\Stores\Infrastructure\Models\Artist;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;

class ArtistResource extends Resource
{
    protected static ?string $slug = 'artistas';

    protected static ?string $model = Artist::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static string|UnitEnum|null $navigationGroup = 'Tiendas';

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'artista';

    protected static ?string $pluralModelLabel = 'artistas';

    protected static ?string $recordTitleAttribute = 'display_name';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Artista')
                ->columns(2)
                ->schema([
                    TextInput::make('display_name')->label('Nombre artístico')->required()->maxLength(255),
                    TextInput::make('email')->label('Email')->email(),
                    TextInput::make('phone')->label('Teléfono')->tel(),
                    Select::make('user_id')
                        ->label('Usuario de acceso al panel')
                        ->helperText('Usuario con rol "artista" que verá sus ventas. Opcional.')
                        ->options(fn (): array => User::role(Role::Artist->value)->pluck('email', 'id')->all())
                        ->searchable()
                        ->unique(ignoreRecord: true),
                ]),

            Section::make('Datos fiscales (autofacturación)')
                ->description('Los tipos de IVA y de retención los valida el gestor fiscal.')
                ->columns(2)
                ->schema([
                    TextInput::make('legal_name')->label('Nombre o razón social'),
                    TextInput::make('tax_id')->label('NIF / CIF')->maxLength(20),
                    Select::make('tax_regime')->label('Forma jurídica')->options(Fields::options(TaxRegime::class)),
                    DatePicker::make('self_billing_agreement_signed_at')->label('Acuerdo de autofacturación firmado el'),
                    Fields::percent('vat_rate_bps')->label('IVA de sus facturas'),
                    Fields::percent('irpf_rate_bps')->label('Retención de IRPF'),
                    TextInput::make('iban')->label('IBAN')->password()->revealable()->helperText('Se guarda cifrado.')->columnSpanFull(),
                ]),

            Section::make('Dirección fiscal')
                ->columns(2)
                ->collapsed()
                ->schema([
                    TextInput::make('address_line')->label('Dirección')->columnSpanFull(),
                    TextInput::make('postal_code')->label('Código postal'),
                    TextInput::make('city')->label('Población'),
                    TextInput::make('province')->label('Provincia'),
                    TextInput::make('country')->label('País (código)')->default('ES')->length(2),
                ]),

            Textarea::make('notes')->label('Notas internas')->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->withCount('stores'))
            ->columns([
                TextColumn::make('display_name')->label('Artista')->searchable()->sortable(),
                TextColumn::make('legal_name')->label('Nombre fiscal')->searchable()->toggleable(),
                TextColumn::make('tax_id')->label('NIF')->toggleable(),
                TextColumn::make('stores_count')->label('Tiendas')->alignEnd(),
                IconColumn::make('self_billing_agreement_signed_at')
                    ->label('Acuerdo firmado')
                    ->boolean()
                    ->getStateUsing(fn (Artist $record): bool => $record->hasSelfBillingAgreement()),
            ])
            ->filters([TrashedFilter::make()])
            ->recordActions([EditAction::make()])
            ->defaultSort('display_name');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListArtists::route('/'),
            'create' => CreateArtist::route('/create'),
            'edit' => EditArtist::route('/{record}/edit'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()->withoutGlobalScopes([SoftDeletingScope::class]);
    }
}
