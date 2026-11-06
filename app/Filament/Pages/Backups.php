<?php

namespace App\Filament\Pages;

use App\Modules\Identity\Infrastructure\Models\User;
use App\Platform\Backup\Backup;
use App\Platform\Backup\BackupSchedule;
use App\Platform\Backup\CreateBackup;
use App\Platform\Security\SecurityEventType;
use App\Platform\Security\SecurityLog;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;
use UnitEnum;

/**
 * «Copias de seguridad»: estado, programación, copias guardadas y copia inmediata.
 * Solo superadministradores.
 */
class Backups extends Page implements HasTable
{
    use InteractsWithTable;

    protected string $view = 'filament.pages.backups';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCircleStack;

    protected static string|UnitEnum|null $navigationGroup = 'Herramientas';

    protected static ?string $navigationLabel = 'Copias de seguridad';

    protected static ?string $title = 'Copias de seguridad';

    protected static ?int $navigationSort = 2;

    protected static ?string $slug = 'copias-de-seguridad';

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user instanceof User && $user->isSuperadmin();
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $tz = (string) config('clavedealma.backups.timezone');
        $last = Backup::query()->where('status', Backup::OK)->latest('started_at')->first();
        $lastAttempt = Backup::query()->latest('started_at')->first();

        return [
            'schedule' => BackupSchedule::describe(),
            'enabled' => BackupSchedule::get()['enabled'],
            'disk' => (string) config('clavedealma.backups.disk'),
            'offsite' => Backup::offsiteEnabled(),
            'offsiteMissing' => Backup::offsiteEnabled() && $last !== null && ! $last->isOffsite() ? ($last->offsite_error ?? 'No se ha subido.') : null,
            'email' => BackupSchedule::notifyEmail(),
            'last' => $last === null ? null : $last->started_at->timezone($tz)->format('d/m/Y H:i'),
            'stale' => $last === null || $last->started_at->lt(BackupSchedule::staleAfter(now())),
            'failed' => $lastAttempt !== null && $lastAttempt->status === Backup::FAILED ? $lastAttempt->message : null,
        ];
    }

    public function table(Table $table): Table
    {
        $tz = (string) config('clavedealma.backups.timezone');

        return $table
            ->query(Backup::query())
            ->defaultSort('started_at', 'desc')
            ->emptyStateHeading('Todavía no hay copias')
            ->emptyStateDescription('Pulsa «Hacer copia ahora» o espera a la primera copia programada.')
            ->columns([
                TextColumn::make('started_at')->label('Fecha')->dateTime('d/m/Y H:i', $tz)->sortable(),
                TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        Backup::OK => 'Correcta',
                        Backup::FAILED => 'Ha fallado',
                        default => 'En curso',
                    })
                    ->color(fn (string $state): string => match ($state) {
                        Backup::OK => 'success',
                        Backup::FAILED => 'danger',
                        default => 'warning',
                    }),
                TextColumn::make('trigger')->label('Origen')->formatStateUsing(fn (string $state): string => $state === 'scheduled' ? 'Programada' : 'Manual'),
                TextColumn::make('size_bytes')->label('Tamaño')->formatStateUsing(fn (Backup $record): string => $record->size_bytes === null ? '—' : $record->sizeLabel())->alignEnd(),
                TextColumn::make('offsite_at')->label('Fuera del servidor')
                    ->visible(fn (): bool => Backup::offsiteEnabled())
                    ->state(fn (Backup $record): string => match (true) {
                        $record->status !== Backup::OK => '—',
                        $record->isOffsite() => 'Subida',
                        $record->offsite_error !== null => 'No se ha subido',
                        default => 'Pendiente',
                    })
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Subida' => 'success',
                        'No se ha subido' => 'danger',
                        default => 'gray',
                    })
                    ->tooltip(fn (Backup $record): ?string => $record->offsite_error),
                TextColumn::make('message')->label('Detalle')->wrap()->limit(120),
            ])
            ->recordActions([
                Action::make('download')
                    ->label('Descargar')
                    ->icon(Heroicon::OutlinedArrowDownTray)
                    ->visible(fn (Backup $record): bool => $record->status === Backup::OK && $record->path !== null)
                    ->action(function (Backup $record): StreamedResponse {
                        SecurityLog::record(SecurityEventType::BackupDownloaded, 'Copia de seguridad descargada.', subject: 'Copia #'.$record->id);
                        // Si en el servidor ya no está (por ejemplo, tras perderlo), desde fuera.
                        $disk = Storage::disk($record->disk)->exists((string) $record->path) || ! $record->isOffsite() ? $record->disk : (string) $record->offsite_disk;

                        return Storage::disk($disk)->download((string) ($disk === $record->disk ? $record->path : $record->offsite_path));
                    }),
                Action::make('uploadOffsite')
                    ->label('Subir fuera')
                    ->icon(Heroicon::OutlinedCloudArrowUp)
                    ->color('warning')
                    ->visible(fn (Backup $record): bool => Backup::offsiteEnabled() && $record->status === Backup::OK && ! $record->isOffsite())
                    ->action(function (Backup $record, CreateBackup $createBackup): void {
                        $createBackup->uploadOffsite($record)
                            ? Notification::make()->title('Copia subida fuera del servidor')->success()->send()
                            : Notification::make()->title('No se ha podido subir')->body((string) $record->fresh()?->offsite_error)->danger()->persistent()->send();
                    }),
                Action::make('delete')
                    ->label('Borrar')
                    ->icon(Heroicon::OutlinedTrash)
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalDescription('Se borra el archivo de la copia, en el servidor y fuera. No se puede deshacer.')
                    ->visible(fn (Backup $record): bool => $record->status !== Backup::RUNNING)
                    ->action(function (Backup $record): void {
                        CreateBackup::deleteFiles($record);
                        $record->delete();
                        SecurityLog::record(SecurityEventType::BackupDeleted, 'Copia de seguridad borrada.', subject: 'Copia #'.$record->id);
                        Notification::make()->title('Copia borrada')->success()->send();
                    }),
            ]);
    }

    /**
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('backupNow')
                ->label('Hacer copia ahora')
                ->icon(Heroicon::OutlinedCircleStack)
                ->requiresConfirmation()
                ->modalDescription('Guarda la base de datos y los archivos subidos. Puede tardar un minuto.')
                ->action(function (CreateBackup $createBackup): void {
                    $backup = $createBackup('manual', auth()->id());
                    SecurityLog::record(SecurityEventType::BackupCreated, 'Copia de seguridad manual: '.$backup->status.'.', subject: 'Copia #'.$backup->id);
                    $backup->status === Backup::OK
                        ? Notification::make()->title('Copia hecha')->body("{$backup->sizeLabel()} · {$backup->message}")->success()->send()
                        : Notification::make()->title('La copia ha fallado')->body((string) $backup->message)->danger()->persistent()->send();
                }),

            Action::make('testOffsite')
                ->label('Probar Object Storage')
                ->icon(Heroicon::OutlinedSignal)
                ->color('gray')
                ->visible(fn (): bool => Backup::offsiteEnabled())
                ->action(function (): void {
                    $disk = Storage::disk((string) config('clavedealma.backups.offsite_disk'));
                    $file = 'prueba-'.now()->format('YmdHis').'.txt';
                    try {
                        $disk->put($file, 'Prueba de conexión de Clave de Alma.');
                        $ok = $disk->get($file) === 'Prueba de conexión de Clave de Alma.';
                        $disk->delete($file);
                        $ok
                            ? Notification::make()->title('Object Storage funciona')->body('Se ha podido escribir, leer y borrar un archivo de prueba.')->success()->send()
                            : Notification::make()->title('Object Storage no devuelve lo escrito')->danger()->persistent()->send();
                    } catch (Throwable $e) {
                        Notification::make()->title('No se puede conectar con Object Storage')->body(Str::limit($e->getMessage(), 300).' Revisa AWS_* y BACKUP_OFFSITE_BUCKET en el .env.')->danger()->persistent()->send();
                    }
                }),

            Action::make('schedule')
                ->label('Programación')
                ->icon(Heroicon::OutlinedClock)
                ->color('gray')
                ->modalHeading('Programación de las copias')
                ->modalSubmitActionLabel('Guardar')
                ->fillForm(fn (): array => BackupSchedule::get())
                ->schema([
                    Toggle::make('enabled')->label('Hacer copias automáticas')->live(),
                    Select::make('frequency')->label('Frecuencia')->options(['daily' => 'Cada día', 'weekly' => 'Una vez a la semana'])->required()->live(),
                    Select::make('weekday')->label('Día de la semana')->options(BackupSchedule::WEEKDAYS)->visible(fn (Get $get): bool => $get('frequency') === 'weekly'),
                    TimePicker::make('time')->label('Hora (Madrid)')->seconds(false)->required()->helperText('Mejor de madrugada, cuando hay menos visitas.'),
                    TextInput::make('keep')->label('Copias que se conservan')->numeric()->minValue(1)->maxValue(365)->required()->helperText('Las más antiguas se borran solas.'),
                    TextInput::make('email')->label('Avisar por email a')->email()->placeholder((string) config('clavedealma.superadmin.email'))->helperText('Si una copia falla o lleva tiempo sin hacerse. Vacío = el superadministrador.'),
                ])
                ->action(function (array $data): void {
                    BackupSchedule::save([
                        'enabled' => (bool) $data['enabled'],
                        'frequency' => $data['frequency'],
                        'weekday' => (int) ($data['weekday'] ?? 1),
                        'time' => substr((string) $data['time'], 0, 5),
                        'keep' => (int) $data['keep'],
                        'email' => $data['email'] ?: null,
                    ]);
                    SecurityLog::record(SecurityEventType::BackupScheduleChanged, 'Programación de copias: '.BackupSchedule::describe());
                    Notification::make()->title('Programación guardada')->body(BackupSchedule::describe())->success()->send();
                }),
        ];
    }
}
