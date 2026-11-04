<?php

namespace App\Platform\Documents;

use App\Modules\Orders\Contracts\OrderStatus;
use App\Modules\Orders\Infrastructure\Models\Order;
use App\Modules\Orders\Infrastructure\Models\OrderLine;
use Illuminate\Support\Collection;

/**
 * Orden de fabricación: qué fabricar, de un pedido o de todo lo pendiente.
 * Sin ningún dato del cliente (la usa el fabricante).
 */
final class ProductionSheet
{
    /**
     * @param  \Illuminate\Database\Eloquent\Collection<int, Order>|null  $orders  null = todo lo pagado y en fabricación
     * @return array<string, mixed>
     */
    public static function data(?Collection $orders = null): array
    {
        $orders ??= Order::query()->with('lines')
            ->whereIn('status', [OrderStatus::Paid, OrderStatus::InProduction])
            ->orderBy('paid_at')->get();
        $orders->loadMissing('lines');

        $deadline = fn (Order $o): ?string => $o->paid_at?->copy()->addWeekdays($o->lead_time_max_days)->format('d/m/Y');

        // Total por SKU para fabricar por tandas.
        $totals = $orders->flatMap(fn (Order $o) => $o->lines)
            ->groupBy('sku')
            ->map(fn (Collection $lines, string $sku): array => [
                'sku' => $sku,
                'name' => (string) $lines->first()?->product_name,
                'option' => (string) $lines->first()?->label(),
                'store' => (string) $lines->first()?->store_name,
                'quantity' => (int) $lines->sum('quantity'),
                'personalized' => (bool) $lines->first()?->personalized,
            ])
            ->sortBy('sku')->values()->all();

        return [
            'orders' => $orders->map(fn (Order $o): array => [
                'reference' => $o->reference,
                'status' => $o->status->label(),
                'paid_at' => $o->paid_at?->timezone('Europe/Madrid')->format('d/m/Y H:i'),
                'deadline' => $deadline($o),
                'late' => $o->paid_at !== null && $o->paid_at->copy()->addWeekdays($o->lead_time_max_days)->isPast(),
                'test' => $o->is_test,
                'note' => $o->customer_note,
                'lines' => $o->lines->map(fn (OrderLine $l): array => [
                    'sku' => $l->sku, 'name' => $l->product_name, 'option' => $l->label(), 'store' => $l->store_name,
                    'quantity' => $l->quantity, 'personalized' => $l->personalized,
                ])->all(),
            ])->all(),
            'totals' => $totals,
            'units' => array_sum(array_column($totals, 'quantity')),
        ];
    }
}
