<?php

namespace App\Console\Commands;

use App\Platform\Backup\Backup;
use App\Platform\Backup\BackupSchedule;
use App\Platform\Backup\CreateBackup;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('copias:crear {--programada : La lanza el programador (respeta si las copias automáticas están desactivadas)}')]
#[Description('Hace una copia de seguridad: base de datos y archivos subidos')]
class CreateBackupCommand extends Command
{
    public function handle(CreateBackup $createBackup): int
    {
        if ($this->option('programada') && ! BackupSchedule::get()['enabled']) {
            $this->info('Copias automáticas desactivadas en el panel.');

            return self::SUCCESS;
        }

        $backup = $createBackup($this->option('programada') ? 'scheduled' : 'manual');

        if ($backup->status !== Backup::OK) {
            $this->error('La copia ha fallado: '.$backup->message);

            return self::FAILURE;
        }
        $this->info("Copia hecha: {$backup->path} ({$backup->sizeLabel()}). {$backup->message}");

        return self::SUCCESS;
    }
}
