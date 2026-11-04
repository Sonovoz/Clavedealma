<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PurgeSecurityLogCommand extends Command
{
    protected $signature = 'seguridad:purgar';

    protected $description = 'Borra del registro de seguridad lo que pasa del plazo de conservación (RGPD).';

    public function handle(): int
    {
        $days = max(30, (int) config('clavedealma.security.retention_days', 365));
        $deleted = DB::table('security_events')->where('created_at', '<', now()->subDays($days))->delete();
        $this->info("Entradas borradas: {$deleted} (se conservan {$days} días).");

        return self::SUCCESS;
    }
}
