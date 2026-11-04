<?php

namespace App\Modules\Orders\Infrastructure\Listeners;

use App\Modules\Inventory\Contracts\Events\StockShortage;
use App\Modules\Orders\Infrastructure\Models\Order;

class FlagStockShortage
{
    public function handle(StockShortage $event): void
    {
        $order = Order::query()->find($event->orderId);
        if ($order === null) {
            return;
        }
        $reason = "Faltan {$event->missing} ud. de la variante #{$event->variantId}: se agotó mientras se pagaba. Fabrica la pieza o devuelve el importe.";
        $order->update(['review_reason' => $reason]);
        $order->log('review', $reason);
    }
}
