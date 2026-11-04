<?php

namespace App\Modules\Orders\Infrastructure\Lookup;

use App\Modules\Orders\Application\PlaceOrder;
use App\Modules\Orders\Contracts\OrderLineSummary;
use App\Modules\Orders\Contracts\OrderLookup;
use App\Modules\Orders\Contracts\OrderSummary;
use App\Modules\Orders\Infrastructure\Models\Order;
use App\Modules\Orders\Infrastructure\Models\OrderLine;

class EloquentOrderLookup implements OrderLookup
{
    public function find(int $orderId): ?OrderSummary
    {
        return $this->summary(Order::query()->with('lines')->find($orderId));
    }

    public function findByToken(string $token): ?OrderSummary
    {
        return $this->summary(Order::query()->with('lines')->where('token', $token)->first());
    }

    public function paidOrderIds(): array
    {
        return Order::query()->whereNotNull('paid_at')->where('is_test', false)->orderBy('paid_at')->pluck('id')->map(fn ($id): int => (int) $id)->all();
    }

    public function note(int $orderId, string $type, ?string $note = null): void
    {
        Order::query()->find($orderId)?->log($type, $note);
    }

    private function summary(?Order $order): ?OrderSummary
    {
        if ($order === null) {
            return null;
        }

        return new OrderSummary(
            id: $order->id,
            reference: (string) $order->reference,
            token: $order->token,
            status: $order->status,
            email: $order->email,
            customerName: $order->customer_name,
            shippingCents: $order->shipping_cents,
            totalCents: $order->total_cents,
            deliveryMaxDays: $order->delivery_max_days,
            shippedAt: $order->shipped_at?->toDateTimeImmutable(),
            deliveredAt: $order->delivered_at?->toDateTimeImmutable(),
            isTest: $order->is_test,
            lines: $order->lines->map(fn (OrderLine $l): OrderLineSummary => new OrderLineSummary(
                $l->id, $l->product_variant_id, $l->sku, $l->product_name, $l->label(), $l->store_name,
                $l->quantity, $l->unit_price_cents, $l->personalized, $l->image_path,
                $l->vat_rate_bps, $l->total_cents,
            ))->values()->all(),
            paidAt: $order->paid_at?->toDateTimeImmutable(),
            shippingVatBps: PlaceOrder::SHIPPING_VAT_BPS,
            currency: $order->currency,
        );
    }
}
