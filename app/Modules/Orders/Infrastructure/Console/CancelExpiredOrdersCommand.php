<?php

namespace App\Modules\Orders\Infrastructure\Console;

use App\Modules\Orders\Application\CancelExpiredOrders;
use Illuminate\Console\Command;

class CancelExpiredOrdersCommand extends Command
{
    protected $signature = 'pedidos:cancelar-caducados';

    protected $description = 'Cancela los pedidos que llevan demasiado tiempo sin pagarse.';

    public function handle(CancelExpiredOrders $cancel): int
    {
        $count = $cancel();
        $this->info($count === 0 ? 'No hay pedidos caducados.' : "Pedidos cancelados: {$count}.");

        return self::SUCCESS;
    }
}
