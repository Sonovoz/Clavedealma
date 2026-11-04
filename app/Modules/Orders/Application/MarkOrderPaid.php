<?php

namespace App\Modules\Orders\Application;

use App\Modules\Orders\Contracts\Events\OrderPaid;
use App\Modules\Orders\Contracts\OrderStatus;
use App\Modules\Orders\Infrastructure\Models\Order;
use App\Modules\Payments\Contracts\Events\PaymentSucceeded;
use Illuminate\Support\Facades\DB;

/**
 * Cobro confirmado → pedido pagado. Idempotente: si ya estaba pagado no hace nada.
 * Si el importe no cuadra o llega tras cancelarse, se marca «a revisar» (no se pierde el cobro).
 */
class MarkOrderPaid
{
    public function handle(PaymentSucceeded $payment): void
    {
        DB::transaction(function () use ($payment): void {
            $order = Order::query()->with('lines')->lockForUpdate()->find($payment->orderId);
            if ($order === null || $order->status->isPaid()) {
                return;
            }

            $review = null;
            if ($payment->amountCents !== $order->total_cents || strtoupper($payment->currency) !== $order->currency) {
                $review = 'El importe cobrado ('.$payment->amountCents.' '.$payment->currency.') no coincide con el del pedido.';
            } elseif ($order->status === OrderStatus::Cancelled) {
                $review = 'Pagado después de cancelarse por tiempo: revisa el stock o devuelve el importe.';
            }

            $order->update([
                'status' => OrderStatus::Paid,
                'paid_at' => now(),
                'cancelled_at' => null,
                'review_reason' => $review ?? $order->review_reason,
            ]);
            $order->log('paid', $payment->provider.' · '.$payment->providerReference);
            if ($review !== null) {
                $order->log('review', $review);
            }

            OrderPaid::dispatch($order->id, (string) $order->reference, $order->lines
                ->map(fn ($l): array => ['variant_id' => $l->product_variant_id, 'quantity' => $l->quantity])
                ->values()->all());
        });
    }
}
