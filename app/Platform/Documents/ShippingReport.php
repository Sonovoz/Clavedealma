<?php

namespace App\Platform\Documents;

use App\Modules\Orders\Contracts\OrderStatus;
use App\Modules\Orders\Infrastructure\Models\Order;

/**
 * Estado de los envíos: lo que está listo para salir y lo que va en camino
 * (en rojo lo que lleva más días de los previstos).
 */
final class ShippingReport
{
    /**
     * @return array<string, mixed>
     */
    public static function data(): array
    {
        $row = fn (Order $o): array => [
            'reference' => $o->reference,
            'customer' => $o->customer_name,
            'city' => $o->postal_code.' '.$o->city,
            'zone' => $o->shipping_zone->label(),
            'carrier' => $o->tracking_carrier ?? $o->shipping_carrier,
            'tracking' => $o->tracking_number,
            'units' => $o->itemCount(),
            'ready_at' => $o->ready_at?->timezone('Europe/Madrid')->format('d/m/Y'),
            'shipped_at' => $o->shipped_at?->timezone('Europe/Madrid')->format('d/m/Y'),
            'days' => $o->shipped_at === null ? null : (int) $o->shipped_at->diffInWeekdays(now()),
            'late' => $o->shipped_at !== null && $o->shipped_at->diffInWeekdays(now()) > $o->delivery_max_days,
            'test' => $o->is_test,
        ];

        $ready = Order::query()->with('lines')->where('status', OrderStatus::ReadyToShip)->orderBy('ready_at')->get();
        $shipped = Order::query()->with('lines')->where('status', OrderStatus::Shipped)->orderBy('shipped_at')->get();
        $production = Order::query()->whereIn('status', [OrderStatus::Paid, OrderStatus::InProduction])->count();

        return [
            'ready' => $ready->map($row)->all(),
            'shipped' => $shipped->map($row)->all(),
            'inProduction' => $production,
            'late' => $shipped->filter(fn (Order $o): bool => $o->shipped_at !== null && $o->shipped_at->diffInWeekdays(now()) > $o->delivery_max_days)->count(),
        ];
    }
}
