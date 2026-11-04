<?php

namespace App\Filament\Pages;

use App\Modules\Identity\Infrastructure\Models\User;
use App\Platform\ApplyUpdate;
use App\Platform\Security\SecurityEventType;
use App\Platform\Security\SecurityLog;
use App\Platform\SystemStatus;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Artisan;
use UnitEnum;

/**
 * Página "Sistema": estado técnico y herramientas de actualización.
 * Solo para superadministradores.
 */
class System extends Page
{
    protected string $view = 'filament.pages.system';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static string|UnitEnum|null $navigationGroup = 'Administración';

    protected static ?string $navigationLabel = 'Sistema';

    protected static ?string $title = 'Sistema';

    protected static ?int $navigationSort = 100;

    protected static ?string $slug = 'sistema';

    /** @var list<array{step: string, ok: bool, output: string}> */
    public array $lastUpdate = [];

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
        $status = app(SystemStatus::class);

        return [
            'version' => $status->version(),
            'environment' => app()->environment(),
            'phpVersion' => PHP_VERSION,
            'laravelVersion' => app()->version(),
            'databaseOk' => $status->databaseIsReachable(),
            'pendingMigrations' => $status->pendingMigrations(),
            'storageLinked' => $status->storageIsLinked(),
            'storageWritable' => $status->storageIsWritable(),
            'maintenance' => $status->isInMaintenance(),
            'drivers' => $status->drivers(),
        ];
    }

    /**
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('applyUpdate')
                ->label('Aplicar actualización')
                ->icon(Heroicon::OutlinedArrowPath)
                ->requiresConfirmation()
                ->modalDescription('Ejecuta las migraciones pendientes y regenera cachés. Haz antes una copia de la base de datos.')
                ->action(function (ApplyUpdate $applyUpdate): void {
                    $this->lastUpdate = $applyUpdate(auth()->id());
                    $failed = collect($this->lastUpdate)->contains(fn (array $r): bool => ! $r['ok']);
                    SecurityLog::record(SecurityEventType::UpdateApplied, $failed ? 'Actualización aplicada con errores.' : 'Actualización aplicada.');

                    $notification = Notification::make();
                    $failed
                        ? $notification->title('La actualización terminó con errores')->danger()
                        : $notification->title('Actualización aplicada')->success();
                    $notification->send();
                }),

            Action::make('maintenanceOn')
                ->label('Activar mantenimiento')
                ->icon(Heroicon::OutlinedLockClosed)
                ->color('warning')
                ->requiresConfirmation()
                ->modalDescription('La web pública mostrará un aviso de mantenimiento. El panel sigue accesible.')
                ->visible(fn (): bool => ! app()->isDownForMaintenance())
                ->action(function (): void {
                    Artisan::call('down', ['--retry' => 60]);
                    SecurityLog::record(SecurityEventType::Maintenance, 'Modo mantenimiento activado.');
                    Notification::make()->title('Modo mantenimiento activado')->warning()->send();
                }),

            Action::make('maintenanceOff')
                ->label('Desactivar mantenimiento')
                ->icon(Heroicon::OutlinedLockOpen)
                ->color('success')
                ->visible(fn (): bool => app()->isDownForMaintenance())
                ->action(function (): void {
                    Artisan::call('up');
                    SecurityLog::record(SecurityEventType::Maintenance, 'Modo mantenimiento desactivado.');
                    Notification::make()->title('La web vuelve a estar disponible')->success()->send();
                }),
        ];
    }
}
