<?php

namespace App\Filament\Resources\SecurityEvents;

use App\Filament\Resources\SecurityEvents\Pages\ListSecurityEvents;
use App\Platform\Security\SecurityEvent;
use App\Platform\Security\SecurityEventType;
use BackedEnum;
use Filament\Forms\Components\DatePicker;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Registro de seguridad (solo lectura, solo superadministradores). ADR 0019.
 */
class SecurityEventResource extends Resource
{
    protected static ?string $slug = 'registro-de-seguridad';

    protected static ?string $model = SecurityEvent::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static string|UnitEnum|null $navigationGroup = 'Administración';

    protected static ?string $navigationLabel = 'Registro de seguridad';

    protected static ?string $modelLabel = 'entrada';

    protected static ?string $pluralModelLabel = 'registro de seguridad';

    protected static ?string $breadcrumb = 'Registro de seguridad';

    protected static ?int $navigationSort = 60;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getNavigationBadge(): ?string
    {
        $failed = SecurityEvent::query()->whereIn('type', [SecurityEventType::LoginFailed, SecurityEventType::Lockout])->where('created_at', '>=', now()->subDay())->count();

        return $failed > 0 ? (string) $failed : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Accesos fallidos en las últimas 24 horas';
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')->label('Cuándo')->dateTime('d/m/Y H:i:s', 'Europe/Madrid')->sortable(),
                TextColumn::make('type')->label('Qué')->badge()
                    ->formatStateUsing(fn (SecurityEventType $state): string => $state->label())
                    ->color(fn (SecurityEvent $record): string => match ($record->level) {
                        'danger' => 'danger',
                        'warning' => 'warning',
                        default => 'gray',
                    }),
                TextColumn::make('description')->label('Detalle')->wrap()
                    ->description(fn (SecurityEvent $record): ?string => $record->subject),
                // Quién y desde dónde (IP; el navegador, al pasar el ratón).
                TextColumn::make('user_email')->label('Quién')->placeholder('—')->searchable(['user_email', 'ip'])->wrap()
                    ->description(fn (SecurityEvent $record): ?string => $record->ip)
                    ->tooltip(fn (SecurityEvent $record): ?string => $record->user_agent),
            ])
            ->filters([
                SelectFilter::make('type')->label('Qué')->multiple()
                    ->options(collect(SecurityEventType::cases())->mapWithKeys(fn (SecurityEventType $t): array => [$t->value => $t->label()])->all()),
                SelectFilter::make('level')->label('Importancia')->options(['danger' => 'Alta', 'warning' => 'Media', 'info' => 'Normal']),
                Filter::make('dates')
                    ->schema([DatePicker::make('from')->label('Desde'), DatePicker::make('until')->label('Hasta')])
                    ->query(fn (Builder $q, array $data) => $q
                        ->when($data['from'] ?? null, fn (Builder $q, $d) => $q->whereDate('created_at', '>=', $d))
                        ->when($data['until'] ?? null, fn (Builder $q, $d) => $q->whereDate('created_at', '<=', $d))),
            ])
            ->searchable()
            ->defaultSort('created_at', 'desc')
            ->emptyStateHeading('Todavía no hay entradas');
    }

    public static function getPages(): array
    {
        return ['index' => ListSecurityEvents::route('/')];
    }
}
