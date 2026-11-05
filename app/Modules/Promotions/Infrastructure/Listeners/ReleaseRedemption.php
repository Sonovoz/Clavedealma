<?php

namespace App\Modules\Promotions\Infrastructure\Listeners;

use App\Modules\Orders\Contracts\Events\PaidOrderCancelled;
use App\Modules\Promotions\Infrastructure\Models\CouponRedemption;

/** Pedido pagado cancelado → su uso del cupón deja de contar. */
class ReleaseRedemption
{
    public function handle(PaidOrderCancelled $event): void
    {
        CouponRedemption::query()->where('order_id', $event->orderId)->whereNull('released_at')->update(['released_at' => now()]);
    }
}
