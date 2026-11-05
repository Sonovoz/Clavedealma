<?php

namespace App\Modules\Promotions\Infrastructure\Listeners;

use App\Modules\Orders\Contracts\Events\OrderPaid;
use App\Modules\Orders\Contracts\OrderLookup;
use App\Modules\Promotions\Infrastructure\Models\Coupon;
use App\Modules\Promotions\Infrastructure\Models\CouponRedemption;

/** Pedido pagado con cupón → cuenta un uso (para los límites). Una sola vez por pedido. */
class RecordRedemption
{
    public function __construct(private readonly OrderLookup $orders) {}

    public function handle(OrderPaid $event): void
    {
        $order = $this->orders->find($event->orderId);
        if ($order === null || $order->couponCode === null) {
            return;
        }
        $coupon = Coupon::query()->where('code', Coupon::normalize($order->couponCode))->first();
        if ($coupon === null) {
            return;
        }

        CouponRedemption::query()->firstOrCreate(
            ['order_id' => $order->id],
            ['coupon_id' => $coupon->id, 'email' => mb_strtolower(trim($order->email)), 'discount_cents' => $order->discountCents],
        );
    }
}
