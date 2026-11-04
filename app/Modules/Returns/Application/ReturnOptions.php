<?php

namespace App\Modules\Returns\Application;

use App\Modules\Orders\Contracts\OrderStatus;
use App\Modules\Orders\Contracts\OrderSummary;
use App\Modules\Returns\Domain\ReturnPolicy;
use App\Modules\Returns\Infrastructure\Models\ReturnLine;
use App\Modules\Returns\Infrastructure\Models\ReturnRequest;
use DateTimeImmutable;

/**
 * Qué se puede devolver de un pedido ahora: plazo y unidades por línea.
 */
class ReturnOptions
{
    public function deadline(OrderSummary $order): ?DateTimeImmutable
    {
        return ReturnPolicy::deadline($order->deliveredAt, $order->shippedAt, $order->deliveryMaxDays);
    }

    public function open(OrderSummary $order): bool
    {
        $deadline = $this->deadline($order);

        return in_array($order->status, [OrderStatus::Shipped, OrderStatus::Delivered], true)
            && $deadline !== null && $deadline >= now()->toDateTimeImmutable()
            && array_sum($this->returnable($order)) > 0;
    }

    /**
     * @return array<int, int> línea del pedido → unidades que aún se pueden devolver
     */
    public function returnable(OrderSummary $order): array
    {
        return ReturnPolicy::returnable(
            array_map(fn ($l): array => ['id' => $l->id, 'quantity' => $l->quantity, 'personalized' => $l->personalized], $order->lines),
            $this->alreadyReturned($order->id),
        );
    }

    /**
     * @return array<int, int>
     */
    public function alreadyReturned(int $orderId, ?int $exceptReturnId = null): array
    {
        $ids = ReturnRequest::query()->where('order_id', $orderId)
            ->when($exceptReturnId, fn ($q) => $q->whereKeyNot($exceptReturnId))
            ->get()->filter(fn (ReturnRequest $r): bool => $r->status->counts())->modelKeys();

        return ReturnLine::query()->whereIn('return_request_id', $ids)
            ->selectRaw('order_line_id, SUM(quantity) as total')->groupBy('order_line_id')
            ->pluck('total', 'order_line_id')->map(fn ($v): int => (int) $v)->all();
    }
}
