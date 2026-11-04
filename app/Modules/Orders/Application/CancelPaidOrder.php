<?php

namespace App\Modules\Orders\Application;

use App\Modules\Orders\Contracts\Events\PaidOrderCancelled;
use App\Modules\Orders\Contracts\OrderStatus;
use App\Modules\Orders\Domain\OrderFlow;
use App\Modules\Orders\Infrastructure\Models\Order;
use App\Modules\Payments\Contracts\Refunds;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Cancela un pedido pagado que aún no ha salido y devuelve todo lo cobrado al mismo
 * medio de pago. Si la devolución falla, el pedido no se cancela.
 */
class CancelPaidOrder
{
    public function __construct(private readonly Refunds $refunds) {}

    public function __invoke(Order $order, string $reason, ?string $by = null): Order
    {
        $reason = trim($reason);
        if ($reason === '') {
            throw new InvalidArgumentException('Escribe el motivo de la cancelación (se le dice al cliente).');
        }
        if (! OrderFlow::canCancelPaid($order->status)) {
            throw new InvalidArgumentException("Un pedido «{$order->status->label()}» no se puede cancelar desde aquí.");
        }

        $amount = $order->total_cents - $this->refunds->refundedCents($order->id);
        $result = $this->refunds->refund($order->id, $amount, 'cda-cancel-'.$order->id, 'Cancelación: '.$reason);
        if (! $result->succeeded) {
            $order->log('refund_failed', $result->message);

            throw new InvalidArgumentException($result->message ?? 'No se ha podido devolver el importe.');
        }

        return DB::transaction(function () use ($order, $reason, $by, $amount): Order {
            $fresh = Order::query()->lockForUpdate()->findOrFail($order->id);
            $fresh->update(['status' => OrderStatus::Cancelled, 'cancelled_at' => now()]);
            $fresh->log('refunded', Money::fromCents($amount)->format());
            $fresh->log('cancelled', $reason.($by === null ? '' : ' · por '.$by));

            PaidOrderCancelled::dispatch($fresh->id, (string) $fresh->reference);

            return $fresh;
        });
    }
}
