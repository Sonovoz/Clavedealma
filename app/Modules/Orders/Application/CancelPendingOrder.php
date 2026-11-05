<?php

namespace App\Modules\Orders\Application;

use App\Modules\Orders\Contracts\Events\OrderCancelled;
use App\Modules\Orders\Contracts\OrderStatus;
use App\Modules\Orders\Infrastructure\Models\Order;
use Illuminate\Support\Facades\DB;

/**
 * Anula un pedido que aún espera el pago, porque el cliente lo ha sustituido por otro
 * (cambió la dirección o el carrito en «Finalizar compra»). Su cobro se anula con el
 * evento OrderCancelled, así que ya no se puede pagar. Si entretanto se pagó, no hace nada.
 */
class CancelPendingOrder
{
    public function __invoke(int $orderId, string $reason): bool
    {
        $cancelled = DB::transaction(function () use ($orderId, $reason): bool {
            $order = Order::query()->lockForUpdate()->find($orderId);
            if ($order === null || $order->status !== OrderStatus::PendingPayment) {
                return false;
            }

            $order->update(['status' => OrderStatus::Cancelled, 'cancelled_at' => now()]);
            $order->log('cancelled', $reason);

            return true;
        });

        if ($cancelled) {
            OrderCancelled::dispatch($orderId);
        }

        return $cancelled;
    }
}
