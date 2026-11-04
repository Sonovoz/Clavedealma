<?php

use App\Platform\Backup\BackupSchedule;
use App\Platform\Settings\Settings;
use Illuminate\Support\Facades\Schedule;

/*
| Tareas programadas. En Hostinger las lanza el cron de cada minuto
| (php artisan schedule:run) y en Docker el contenedor «scheduler».
*/

// Copias de seguridad: frecuencia y hora elegidas en el panel (Administración → Copias de seguridad).
if (Settings::ready()) {
    Schedule::command('copias:crear --programada')
        ->cron(BackupSchedule::cron())
        ->timezone((string) config('clavedealma.backups.timezone'))
        ->withoutOverlapping(120);

    // Aviso si la última copia buena es demasiado antigua (por ejemplo, si el cron dejó de funcionar).
    Schedule::command('copias:revisar')->dailyAt('09:10')->timezone((string) config('clavedealma.backups.timezone'));
}

// Pedidos sin pagar: se cancelan pasado el tiempo de espera (clavedealma.shop.pending_minutes).
Schedule::command('pedidos:cancelar-caducados')->everyFifteenMinutes()->withoutOverlapping();
