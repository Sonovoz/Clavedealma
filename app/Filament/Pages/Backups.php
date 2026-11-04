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
use Symfony\Component\HttpFoundation\StreamedResponse;
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
                TextColumn::make('message')->label('Detalle')->wrap()->limit(120),
            ])
            ->recordActions([
                Action::make('download')
                    ->label('Descargar')
                    ->icon(Heroicon::OutlinedArrowDownTray)
                    ->visible(fn (Backup $record): bool => $record->status === Backup::OK && $record->path !== null)
                    ->action(function (Backup $record): StreamedResponse {
                        SecurityLog::record(SecurityEventType::BackupDownloaded, 'Copia de seguridad descargada.', subject: 'Copia #'.$record->id);

                        return Storage::disk($record->disk)->download((string) $record->path);
                    }),
                Action::make('delete')
                    ->label('Borrar')
                    ->icon(Heroicon::OutlinedTrash)
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalDescription('Se borra el archivo de la copia. No se puede deshacer.')
                    ->visible(fn (Backup $record): bool => $record->status !== Backup::RUNNING)
                    ->action(function (Backup $record): void {
                        if ($record->path !== null) {
                            Storage::disk($record->disk)->delete($record->path);
                        }
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
