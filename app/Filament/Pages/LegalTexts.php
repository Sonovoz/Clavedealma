<?php

namespace App\Filament\Pages;

use App\Modules\Identity\Infrastructure\Models\User;
use App\Modules\Stores\Infrastructure\Models\Store;
use App\Platform\Legal\LegalDocument;
use App\Platform\Legal\LegalGenerator;
use App\Platform\Legal\LegalPage;
use App\Platform\Security\SecurityEventType;
use App\Platform\Security\SecurityLog;
use App\Storefront\Urls;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use UnitEnum;

/**
 * «Textos legales»: los de Clave de Alma y los de cada tienda, en su última versión.
 * Se generan al guardar los datos fiscales; la asesoría los revisa aquí (ADR 0020).
 */
class LegalTexts extends Page implements HasTable
{
    use InteractsWithTable;

    protected string $view = 'filament.pages.legal-texts';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedScale;

    protected static string|UnitEnum|null $navigationGroup = 'Ajustes';

    protected static ?string $navigationLabel = 'Textos legales';

    protected static ?string $title = 'Textos legales';

    protected static ?int $navigationSort = 4;

    protected static ?string $slug = 'textos-legales';

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user instanceof User && $user->isSuperadmin();
    }

    public static function getNavigationBadge(): ?string
    {
        $drafts = self::latest()->whereNull('approved_at')->count();

        return $drafts > 0 ? (string) $drafts : null;
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Textos pendientes de revisión';
    }

    /**
     * La última versión de cada texto (de Clave de Alma y de cada tienda).
     *
     * @return Builder<LegalDocument>
     */
    private static function latest(): Builder
    {
        return LegalDocument::query()->whereIn('id', LegalDocument::query()->selectRaw('MAX(id)')->groupBy('page', 'store_id'));
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('regenerate')
                ->label('Regenerar textos')
                ->icon(Heroicon::OutlinedArrowPath)
                ->requiresConfirmation()
                ->modalDescription('Vuelve a generar los textos con los datos actuales (fiscales, tiendas, tarifas de envío). Solo se crea versión nueva donde el texto cambia.')
                ->action(function (LegalGenerator $generator): void {
                    $created = $generator->generateAll();
                    if ($created > 0) {
                        SecurityLog::record(SecurityEventType::LegalGenerated, "Textos legales regenerados: {$created} versiones nuevas.");
                    }
                    Notification::make()->title($created > 0 ? "Versiones nuevas: {$created}" : 'Los textos ya estaban al día')->success()->send();
                }),
        ];
    }

    public function table(Table $table): Table
    {
        $stores = Store::query()->pluck('name', 'id');

        return $table
            ->query(self::latest())
            ->columns([
                TextColumn::make('store_id')->label('De')
                    ->state(fn (LegalDocument $record): string => $record->store_id === null ? 'Clave de Alma' : (string) ($stores[$record->store_id] ?? 'Tienda #'.$record->store_id))
                    ->weight('bold'),
                TextColumn::make('page')->label('Texto')->formatStateUsing(fn (LegalPage $state): string => $state->label()),
                TextColumn::make('version')->label('Versión')->formatStateUsing(fn (int $state, LegalDocument $record): string => 'v'.$state.' · '.$record->generated_at->timezone('Europe/Madrid')->format('d/m/Y')),
                TextColumn::make('approved_at')->label('Estado')->badge()
                    ->state(fn (LegalDocument $record): string => $record->approved_at ? 'Revisado' : 'Borrador')
                    ->color(fn (LegalDocument $record): string => $record->approved_at ? 'success' : 'warning')
                    ->description(fn (LegalDocument $record): ?string => $record->approved_at ? 'por '.$record->approved_by : null),
                TextColumn::make('missing')->label('Falta')->wrap()->visibleFrom('lg')
                    ->state(fn (LegalDocument $record): string => $record->missing ? implode(', ', $record->missing) : '—')
                    ->color(fn (LegalDocument $record): ?string => $record->missing ? 'danger' : null),
            ])
            ->filters([
                SelectFilter::make('scope')->label('De')
                    ->options(['market' => 'Clave de Alma'] + $stores->mapWithKeys(fn ($name, $id): array => [(string) $id => $name])->all())
                    ->query(fn (Builder $query, array $data) => match ($data['value'] ?? null) {
                        null, '' => $query,
                        'market' => $query->whereNull('store_id'),
                        default => $query->where('store_id', (int) $data['value']),
                    }),
                SelectFilter::make('state')->label('Estado')->options(['draft' => 'Borrador', 'approved' => 'Revisado'])
                    ->query(fn (Builder $query, array $data) => match ($data['value'] ?? null) {
                        'draft' => $query->whereNull('approved_at'),
                        'approved' => $query->whereNotNull('approved_at'),
                        default => $query,
                    }),
            ])
            ->recordActions([
                Action::make('view')
                    ->label('Leer')
                    ->icon(Heroicon::OutlinedEye)
                    ->color('gray')
                    ->modalHeading(fn (LegalDocument $record): string => $record->page->label().' · v'.$record->version)
                    ->modalContent(fn (LegalDocument $record): HtmlString => new HtmlString('<div class="cda-legal">'.Str::markdown($record->content, ['html_input' => 'strip']).'</div>'))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Cerrar')
                    ->modalWidth('4xl'),
                Action::make('open')
                    ->label('En la web')
                    ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                    ->color('gray')
                    ->url(fn (LegalDocument $record): string => $record->store_id === null
                        ? route('legal', $record->page->value)
                        : Urls::legal((string) Store::query()->whereKey($record->store_id)->value('subdomain'), $record->page->value), shouldOpenInNewTab: true),
                Action::make('approve')
                    ->label('Marcar revisado')
                    ->icon(Heroicon::OutlinedCheckBadge)
                    ->color('success')
                    ->visible(fn (LegalDocument $record): bool => $record->approved_at === null)
                    ->requiresConfirmation()
                    ->modalDescription(fn (LegalDocument $record): string => $record->missing
                        ? 'Este texto aún tiene datos pendientes: '.implode(', ', $record->missing).'. Complétalos antes de darlo por revisado.'
                        : 'Confirma que la asesoría legal ha revisado esta versión. Quedará anotado quién y cuándo.')
                    ->action(function (LegalDocument $record): void {
                        $who = auth()->user()?->getAttribute('email');
                        $record->update(['approved_at' => now(), 'approved_by' => is_string($who) ? $who : null]);
                        SecurityLog::record(SecurityEventType::LegalApproved, $record->page->label().' v'.$record->version.' marcado como revisado.', subject: $record->store_id === null ? 'Clave de Alma' : 'Tienda #'.$record->store_id);
                        Notification::make()->title('Texto marcado como revisado')->success()->send();
                    }),
            ])
            ->defaultSort('store_id')
            ->emptyStateHeading('Todavía no hay textos legales')
            ->emptyStateDescription('Se generan al guardar los datos fiscales (Ajustes → Datos de facturación) o con «Regenerar textos».');
    }
}
