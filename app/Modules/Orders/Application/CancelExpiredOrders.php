<?php

namespace App\Modules\Orders\Application;

use App\Modules\Orders\Contracts\Events\OrderCancelled;
use App\Modules\Orders\Contracts\OrderStatus;
use App\Modules\Orders\Infrastructure\Models\Order;

/**
 * Cancela los pedidos que llevan demasiado tiempo esperando el pago
 * (clavedealma.shop.pending_minutes). Se lanza cada cuarto de hora.
 */
class CancelExpiredOrders
{
    public function __invoke(): int
    {
        $limit = now()->subMinutes((int) config('clavedealma.shop.pending_minutes', 120));
        $count = 0;

        Order::query()->where('status', OrderStatus::PendingPayment)->where('created_at', '<', $limit)
            ->each(function (Order $order) use (&$count): void {
                $order->update(['status' => OrderStatus::Cancelled, 'cancelled_at' => now()]);
                $order->log('cancelled', 'Sin pago en el tiempo previsto.');
                OrderCancelled::dispatch($order->id);
                $count++;
            });

        return $count;
    }
}
