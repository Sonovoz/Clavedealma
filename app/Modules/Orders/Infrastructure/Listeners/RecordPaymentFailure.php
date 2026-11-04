<?php

namespace App\Modules\Orders\Infrastructure\Listeners;

use App\Modules\Orders\Infrastructure\Models\Order;
use App\Modules\Payments\Contracts\Events\PaymentFailed;

class RecordPaymentFailure
{
    public function handle(PaymentFailed $event): void
    {
        Order::query()->find($event->orderId)?->log('payment_failed', $event->message);
    }
}
