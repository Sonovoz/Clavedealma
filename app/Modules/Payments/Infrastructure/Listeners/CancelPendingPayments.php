<?php

namespace App\Modules\Payments\Infrastructure\Listeners;

use App\Modules\Orders\Contracts\Events\OrderCancelled;
use App\Modules\Payments\Contracts\PaymentGateway;
use App\Modules\Payments\Contracts\PaymentStatus;
use App\Modules\Payments\Infrastructure\Models\Payment;

class CancelPendingPayments
{
    public function __construct(private readonly PaymentGateway $gateway) {}

    public function handle(OrderCancelled $event): void
    {
        Payment::query()->where('order_id', $event->orderId)->whereIn('status', [PaymentStatus::Pending, PaymentStatus::Failed])
            ->each(function (Payment $payment): void {
                if ($payment->provider === $this->gateway->name() && $payment->provider_reference !== null) {
                    $this->gateway->cancel($payment->provider_reference);
                }
                $payment->update(['status' => PaymentStatus::Cancelled]);
            });
    }
}
