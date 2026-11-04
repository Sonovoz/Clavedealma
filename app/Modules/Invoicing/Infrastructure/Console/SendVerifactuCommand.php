<?php

namespace App\Modules\Invoicing\Infrastructure\Console;

use App\Modules\Invoicing\Application\SendToAeat;
use App\Modules\Invoicing\Contracts\AeatStatus;
use App\Modules\Invoicing\Contracts\VerifactuSender;
use App\Modules\Invoicing\Infrastructure\Models\Invoice;
use Illuminate\Console\Command;

class SendVerifactuCommand extends Command
{
    protected $signature = 'verifactu:enviar';

    protected $description = 'Envía a la AEAT los registros de facturación pendientes (Veri*Factu).';

    public function handle(SendToAeat $send, VerifactuSender $sender): int
    {
        if (! $sender->configured()) {
            $this->warn('El envío a la AEAT no está configurado (VERIFACTU_DRIVER). Los registros se guardan en local.');

            return self::SUCCESS;
        }
        $pending = Invoice::query()->with('rectifies')->whereIn('aeat_status', [AeatStatus::Pending, AeatStatus::NotConfigured])->orderBy('id')->get();
        $pending->each(fn (Invoice $i) => $send($i));
        $this->info("Registros enviados: {$pending->count()}.");

        return self::SUCCESS;
    }
}
