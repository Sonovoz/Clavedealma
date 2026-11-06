<?php

namespace App\Console\Commands;

use App\Platform\Minimedia\CloseMinimediaMonth;
use App\Platform\Minimedia\MinimediaStatement;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Cierre de Minimedia (ADR 0029). Programado cada día: el día 1 cierra el mes anterior y lo
 * envía a Minimedia; si algún cierre no se pudo enviar, lo reintenta.
 */
#[Signature('minimedia:cierre {--mes= : Mes a cerrar (AAAA-MM); por defecto, el anterior} {--reenviar : Vuelve a enviar el email aunque ya se enviara}')]
#[Description('Cierra el mes de la comisión de Minimedia y envía el resumen por email')]
class MinimediaCloseCommand extends Command
{
    public function handle(CloseMinimediaMonth $close): int
    {
        $month = (string) ($this->option('mes') ?: CloseMinimediaMonth::previousMonth());
        if (! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month) || $month >= now('Europe/Madrid')->format('Y-m')) {
            $this->error('El mes tiene que ser AAAA-MM y ya terminado.');

            return self::FAILURE;
        }

        $statement = $close->close($month);
        $pending = MinimediaStatement::query()->whereNull('sent_at')->orderBy('month')->get();
        if ($this->option('reenviar') && $statement->sent_at !== null) {
            $pending->push($statement);
        }

        $failed = 0;
        foreach ($pending as $s) {
            $ok = $close->send($s);
            $failed += $ok ? 0 : 1;
            $this->line(($ok ? 'Enviado' : 'No se ha podido enviar').": {$s->label()} → ".$s->fresh()?->sent_to);
        }
        if ($pending->isEmpty()) {
            $this->info("{$statement->label()}: ya estaba cerrado y enviado.");
        }

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }
}
