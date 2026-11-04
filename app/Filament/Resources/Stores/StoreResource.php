<?php

namespace App\Filament\Resources\Stores;

use App\Filament\Resources\Stores\Pages\CreateStore;
use App\Filament\Resources\Stores\Pages\EditStore;
use App\Filament\Resources\Stores\Pages\ListStores;
use App\Filament\Support\Fields;
use App\Modules\Stores\Contracts\StoreStatus;
use App\Modules\Stores\Domain\Subdomain;
use App\Modules\Stores\Infrastructure\Models\Store;
use App\Platform\Ai\AiException;
use App\Storefront\Designs;
use App\Storefront\Genres;
use App\Storefront\Seo\SeoAssistant;
use App\Storefront\Seo\SeoText;
use App\Storefront\Urls;
use BackedEnum;
use Closure;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;

class StoreResource extends Resource
{
    protected static ?string $slug = 'tiendas';

    protected static ?string $model = Store::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingStorefront;

    protected static string|UnitEnum|null $navigationGroup = 'Tiendas';

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'tienda';

    protected static ?string $pluralModelLabel = 'tiendas';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Tienda')
                ->columns(2)
                ->schema([
                    Select::make('artist_id')
                        ->label('Artista')
                        ->relationship('artist', 'display_name')
                        ->searchable()
                        ->preload()
                        ->required(),
                    TextInput::make('name')->label('Nombre de la tienda')->required()->maxLength(255),
                    TextInput::make('subdomain')
                        ->label('Dirección de la tienda')
                        ->required()
                        ->maxLength(40)
                        ->prefix(config('clavedealma.domain').'/')
                        ->helperText('Minúsculas, números y guiones. Ejemplo: lunabruma. No se cambia después de publicar: Google perdería la tienda.')
                        ->unique(ignoreRecord: true)
                        ->rule(fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
                            if (! Subdomain::isValid(strtolower(trim((string) $value)))) {
                                $fail('Esta dirección no es válida o está reservada para el market.');
                            }
                        }),
                    Select::make('status')
                        ->label('Estado')
                        ->options(Fields::options(StoreStatus::class))
                        ->default(StoreStatus::Draft->value)
                        ->required(),
                    Fields::percent('commission_bps')
                        ->label('Comisión del artista')
                        ->helperText('Sobre el precio sin IVA y sin envío.')
                        ->required(),
                ]),

            Section::make('Presentación')
                ->schema([
                    Select::make('design')
                        ->label('Diseño de la tienda')
                        ->options(Designs::options())
                        ->default('base')
                        ->selectablePlaceholder(false)
                        ->required()
                        ->helperText('Portada y ambiente hechos a medida para el artista. La pasarela de productos es la misma en todas las tiendas.'),
                    Select::make('theme.genres')
                        ->label('Estilos musicales')
                        ->helperText('Para el filtro de tiendas de la matriz de Clave de Alma.')
                        ->options(Genres::ALL)
                        ->multiple(),
                    TextInput::make('tagline')->label('Frase de portada')->maxLength(255),
                    Textarea::make('bio')->label('Biografía del artista')->rows(5),
                ]),

            Section::make('SEO de la portada de la tienda')
                ->description('Cómo aparece la tienda en Google. Vacío = se genera con el nombre, la frase de portada y las prendas.')
                ->schema([
                    TextInput::make('seo_title')
                        ->label('Título para Google')
                        ->maxLength(70)
                        ->placeholder('Farruquito · Tienda oficial de merchandising')
                        ->helperText('Entre '.SeoText::TITLE_MIN.' y '.SeoText::TITLE_MAX.' caracteres, con el nombre del artista.'),
                    Textarea::make('seo_description')
                        ->label('Descripción para Google')
                        ->maxLength(170)
                        ->rows(3)
                        ->helperText('Entre '.SeoText::DESCRIPTION_MIN.' y '.SeoText::DESCRIPTION_MAX.' caracteres: qué se puede comprar y que es oficial.'),
                    Actions::make([
                        Action::make('aiStoreSeo')
                            ->label('Sugerir con IA')
                            ->icon(Heroicon::OutlinedSparkles)
                            ->visible(fn (?Store $record): bool => $record !== null)
                            ->disabled(fn (): bool => ! app(SeoAssistant::class)->available())
                            ->tooltip(fn (): ?string => app(SeoAssistant::class)->available() ? null : 'La IA no está configurada (AI_DRIVER, AI_API_KEY y AI_MODEL en el .env).')
                            ->action(function (Set $set, Store $record): void {
                                try {
                                    $s = app(SeoAssistant::class)->storeSeo($record);
                                    $set('seo_title', $s['seo_title']);
                                    $set('seo_description', $s['seo_description']);
                                    Notification::make()->title('Propuesta lista: revísala y guarda')->success()->send();
                                } catch (AiException $e) {
                                    Notification::make()->title('La IA no ha podido responder')->body($e->getMessage())->danger()->send();
                                }
                            }),
                    ]),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('artist'))
            ->columns([
                TextColumn::make('name')->label('Tienda')->searchable()->sortable(),
                TextColumn::make('artist.display_name')->label('Artista')->searchable(),
                TextColumn::make('subdomain')
                    ->label('Dirección')
                    ->formatStateUsing(fn (string $state): string => Urls::label($state))
                    ->url(fn (Store $record): string => Urls::home($record), shouldOpenInNewTab: true)
                    ->searchable(),
                TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->formatStateUsing(fn (StoreStatus $state): string => $state->label())
                    ->color(fn (StoreStatus $state): string => $state->color()),
                Fields::percentColumn('commission_bps')->label('Comisión'),
                TextColumn::make('published_at')->label('Publicada el')->date('d/m/Y')->sortable()->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')->label('Estado')->options(Fields::options(StoreStatus::class)),
                TrashedFilter::make(),
            ])
            ->recordActions([EditAction::make()])
            ->defaultSort('name');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListStores::route('/'),
            'create' => CreateStore::route('/create'),
            'edit' => EditStore::route('/{record}/edit'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()->withoutGlobalScopes([SoftDeletingScope::class]);
    }
}
