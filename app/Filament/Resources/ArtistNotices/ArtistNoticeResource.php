<?php

namespace App\Filament\Resources\ArtistNotices;

use App\Filament\Resources\ArtistNotices\Pages\CreateArtistNotice;
use App\Filament\Resources\ArtistNotices\Pages\ListArtistNotices;
use App\Filament\Resources\ArtistNotices\Pages\ViewArtistNotice;
use App\Modules\Catalog\Infrastructure\Models\Product;
use App\Modules\Identity\Infrastructure\Models\User;
use App\Modules\Stores\Application\SendNotice;
use App\Modules\Stores\Contracts\NoticeStatus;
use App\Modules\Stores\Infrastructure\Models\ArtistNotice;
use App\Platform\Artists\ArtistScope;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use UnitEnum;

/**
 * «Avisos» (ADR 0023): el artista cuenta a Clave de Alma lo que necesita («Firmo camisetas
 * en El Corte Inglés el día 12», «Nueva camiseta con el logo de temporada») y el equipo
 * lo atiende. Para el superadmin es *Tiendas → Avisos de artistas*.
 */
class ArtistNoticeResource extends Resource
{
    protected static ?string $model = ArtistNotice::class;

    protected static ?string $slug = 'avisos';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMegaphone;

    protected static ?string $modelLabel = 'aviso';

    protected static ?string $pluralModelLabel = 'avisos';

    protected static ?string $recordTitleAttribute = 'title';

    public static function getNavigationLabel(): string
    {
        return self::isAdmin() ? 'Avisos de artistas' : 'Avisos';
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return self::isAdmin() ? 'Tiendas' : null;
    }

    public static function getNavigationSort(): ?int
    {
        return self::isAdmin() ? 3 : -2;
    }

    /** Solo el artista envía avisos (el superadmin tiene todos los permisos, pero no es artista). */
    public static function canCreate(): bool
    {
        $user = auth()->user();

        return $user instanceof User && ! $user->isSuperadmin() && ArtistScope::artist($user) !== null;
    }

    public static function getNavigationBadge(): ?string
    {
        if (! self::isAdmin()) {
            return null;
        }
        $count = ArtistNotice::query()->where('status', NoticeStatus::New)->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Avisos nuevos sin atender';
    }

    /**
     * El artista solo ve los suyos.
     *
     * @return Builder<ArtistNotice>
     */
    public static function getEloquentQuery(): Builder
    {
        $query = ArtistNotice::query()->with('artist');
        if (self::isAdmin()) {
            return $query;
        }
        $user = auth()->user();
        $artist = ArtistScope::artist($user instanceof User ? $user : null);

        return $query->where('artist_id', $artist->id ?? 0);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Tu aviso para Clave de Alma')
                ->description('Cuéntanos qué necesitas y lo preparamos: un evento, una pieza nueva, un cambio en tu tienda… Te contestamos aquí y por email.')
                ->columns(2)
                ->schema([
                    TextInput::make('title')->label('Título')->required()->maxLength(120)->columnSpanFull()
                        ->placeholder('Firmo camisetas en El Corte Inglés el día 12'),
                    Textarea::make('message')->label('Mensaje')->required()->maxLength(2000)->rows(5)->columnSpanFull()
                        ->placeholder('Qué, dónde y qué necesitas de nosotros (fotos, una colección destacada, una pieza nueva…).'),
                    DatePicker::make('event_date')->label('Fecha (si es para un día concreto)')->native(false)->displayFormat('d/m/Y'),
                    Select::make('product_id')->label('Producto relacionado (opcional)')->searchable()
                        ->options(function (): array {
                            $user = auth()->user();

                            return Product::query()->whereIn('store_id', ArtistScope::storeIds(ArtistScope::artist($user instanceof User ? $user : null)))
                                ->orderBy('name')->pluck('name', 'id')->all();
                        }),
                    FileUpload::make('attachments')->label('Fotos o PDF (hasta '.SendNotice::MAX_FILES.')')
                        ->multiple()->maxFiles(SendNotice::MAX_FILES)->maxSize(10240)
                        ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp', 'application/pdf'])
                        ->disk(ArtistNotice::DISK)->directory('avisos')->visibility('private')
                        ->helperText('Un logo, el cartel del evento, una referencia… Solo los ve el equipo.')
                        ->columnSpanFull(),
                ]),
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->columns(3)->components([
            Section::make(fn (ArtistNotice $record): string => $record->title)->columnSpan(2)->schema([
                TextEntry::make('message')->hiddenLabel()->prose(),
                TextEntry::make('event_date')->label('Fecha')->date('l j \d\e F \d\e Y')->placeholder('Sin fecha'),
                TextEntry::make('product')->label('Producto')
                    ->state(fn (ArtistNotice $record): ?string => $record->product_id === null ? null : Product::withTrashed()->whereKey($record->product_id)->value('name'))
                    ->placeholder('—'),
                TextEntry::make('files')->label('Archivos')->html()
                    ->state(fn (ArtistNotice $record): ?string => ($record->attachments ?? []) === [] ? null : collect($record->attachments)
                        ->map(fn (string $path, int $i): string => '<a class="underline" href="'.e(route('artist-notices.file', [$record, $i])).'" target="_blank">'.e(basename($path)).'</a>')
                        ->implode('<br>'))
                    ->placeholder('Sin archivos'),
            ]),
            Section::make('Estado')->columnSpan(1)->schema([
                TextEntry::make('status')->label('Estado')->badge()
                    ->formatStateUsing(fn (NoticeStatus $state): string => $state->label())
                    ->color(fn (NoticeStatus $state): string => $state->color()),
                TextEntry::make('artist.display_name')->label('Artista')->visible(fn (): bool => self::isAdmin()),
                TextEntry::make('created_at')->label('Enviado')->dateTime('d/m/Y H:i'),
                TextEntry::make('reply')->label('Respuesta de Clave de Alma')->placeholder('Todavía sin respuesta'),
                TextEntry::make('handled_at')->label('Atendido')->dateTime('d/m/Y H:i')->placeholder('—'),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')->label('Aviso')->searchable()->weight('bold')->wrap()
                    ->description(fn (ArtistNotice $record): string => Str::limit($record->message, 90)),
                TextColumn::make('artist.display_name')->label('Artista')->visible(fn (): bool => self::isAdmin())->sortable(),
                TextColumn::make('event_date')->label('Fecha')->date('d/m/Y')->placeholder('—')->sortable(),
                TextColumn::make('status')->label('Estado')->badge()
                    ->formatStateUsing(fn (NoticeStatus $state): string => $state->label())
                    ->color(fn (NoticeStatus $state): string => $state->color()),
                TextColumn::make('created_at')->label('Enviado')->since()->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->label('Estado')->options(collect(NoticeStatus::cases())->mapWithKeys(fn (NoticeStatus $s): array => [$s->value => $s->label()])->all()),
            ])
            ->recordActions([ViewAction::make()])
            ->defaultSort('created_at', 'desc')
            ->emptyStateHeading('Todavía no hay avisos')
            ->emptyStateDescription(self::isAdmin() ? 'Aquí llegan los avisos que envían los artistas.' : 'Cuéntanos tus eventos, piezas nuevas o cambios para tu tienda con «Nuevo aviso».');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListArtistNotices::route('/'),
            'create' => CreateArtistNotice::route('/nuevo'),
            'view' => ViewArtistNotice::route('/{record}'),
        ];
    }

    private static function isAdmin(): bool
    {
        $user = auth()->user();

        return $user instanceof User && $user->isSuperadmin();
    }
}
