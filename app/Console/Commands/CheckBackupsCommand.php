<?php

namespace App\Console\Commands;

use App\Platform\Backup\Backup;
use App\Platform\Backup\BackupSchedule;
use App\Platform\Backup\CreateBackup;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('copias:revisar')]
#[Description('Avisa por email si la última copia buena es demasiado antigua (p. ej. si el cron no se ejecuta)')]
class CheckBackupsCommand extends Command
{
    public function handle(CreateBackup $createBackup): int
    {
        if (! BackupSchedule::get()['enabled']) {
            return self::SUCCESS;
        }

        $last = Backup::query()->where('status', Backup::OK)->latest('started_at')->first();
        if ($last === null || $last->started_at->lt(BackupSchedule::staleAfter(now()))) {
            $when = $last === null ? 'Todavía no hay ninguna copia buena.' : 'La última copia buena es del '.$last->started_at->timezone((string) config('clavedealma.backups.timezone'))->format('d/m/Y H:i').'.';
            $createBackup->notify('Faltan copias de seguridad', $when.' Revisa que la tarea programada (cron) del servidor siga activa.');
            $this->warn($when);

            return self::FAILURE;
        }
        if (Backup::offsiteEnabled() && ! $last->isOffsite()) {
            $createBackup->notify('La última copia no está fuera del servidor', 'La copia del '.$last->started_at->timezone((string) config('clavedealma.backups.timezone'))->format('d/m/Y H:i').' solo está en el servidor: '.($last->offsite_error ?? 'no se ha subido al Object Storage').'. Pulsa «Subir fuera» en Herramientas → Copias de seguridad.');
            $this->warn('La última copia no está fuera del servidor.');

            return self::FAILURE;
        }
        $this->info('Copias al día.');

        return self::SUCCESS;
    }
}
