<?php

namespace App\Platform\Documents;

use App\Modules\Orders\Contracts\OrderStatus;
use App\Modules\Orders\Infrastructure\Models\Order;
use App\Modules\Orders\Infrastructure\Models\OrderLine;
use App\Modules\Payments\Infrastructure\Models\Refund;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Informe de ventas de un periodo (por fecha de pago, hora de Madrid). Informativo:
 * no es contabilidad ni sustituye a las facturas ni a las liquidaciones (fase 6).
 */
final class SalesReport
{
    /**
     * @return array<string, mixed>
     */
    public static function data(CarbonImmutable $from, CarbonImmutable $to, bool $includeTests = false): array
    {
        $tz = 'Europe/Madrid';
        $start = $from->timezone($tz)->startOfDay()->utc();
        $end = $to->timezone($tz)->endOfDay()->utc();
        $m = fn (int $c): string => Money::fromCents($c)->format();

        /** @var Collection<int, Order> $orders */
        $orders = Order::query()->with('lines')
            ->whereNotNull('paid_at')->whereBetween('paid_at', [$start, $end])
            ->when(! $includeTests, fn ($q) => $q->where('is_test', false))
            ->orderBy('paid_at')->get();

        $refunds = (int) Refund::query()->where('status', Refund::SUCCEEDED)
            ->whereBetween('created_at', [$start, $end])
            ->when(! $includeTests, fn ($q) => $q->whereIn('order_id', Order::query()->where('is_test', false)->select('id')))
            ->sum('amount_cents');

        $lines = $orders->flatMap(fn (Order $o) => $o->lines);
        $gross = (int) $orders->sum('total_cents');
        $shipping = (int) $orders->sum('shipping_cents');

        // Por tienda: comisión estimada sobre el precio sin IVA y sin envío (antes de devoluciones).
        $stores = $lines->groupBy('store_name')->map(function (Collection $ls, string $store) use ($m): array {
            $sales = (int) $ls->sum('total_cents');
            $base = (int) $ls->sum(fn (OrderLine $l): int => Money::fromCents($l->total_cents)->withoutVat($l->vat_rate_bps)->cents);
            $commission = (int) $ls->sum(fn (OrderLine $l): int => intdiv(Money::fromCents($l->total_cents)->withoutVat($l->vat_rate_bps)->cents * $l->commission_bps + 5000, 10000));

            return ['store' => $store, 'units' => (int) $ls->sum('quantity'), 'sales' => $m($sales), 'base' => $m($base), 'commission' => $m($commission), 'sales_cents' => $sales];
        })->sortByDesc('sales_cents')->values()->all();

        $products = $lines->groupBy('sku')->map(fn (Collection $ls, string $sku): array => [
            'sku' => $sku,
            'name' => (string) $ls->first()?->product_name,
            'option' => (string) $ls->first()?->label(),
            'store' => (string) $ls->first()?->store_name,
            'units' => (int) $ls->sum('quantity'),
            'sales' => $m((int) $ls->sum('total_cents')),
        ])->sortByDesc('units')->take(15)->values()->all();

        $byStatus = collect(OrderStatus::cases())
            ->map(fn (OrderStatus $s): array => ['label' => $s->label(), 'count' => $orders->where('status', $s)->count()])
            ->filter(fn (array $r): bool => $r['count'] > 0)->values()->all();

        $count = $orders->count();

        return [
            'from' => $from->format('d/m/Y'),
            'to' => $to->format('d/m/Y'),
            'includeTests' => $includeTests,
            'orders' => $count,
            'units' => (int) $lines->sum('quantity'),
            'gross' => $m($gross),
            'goods' => $m($gross - $shipping),
            'shipping' => $m($shipping),
            'vat' => $m((int) $orders->sum('vat_cents')),
            'refunds' => $m($refunds),
            'net' => $m($gross - $refunds),
            'average' => $m($count === 0 ? 0 : intdiv($gross, $count)),
            'stores' => $stores,
            'products' => $products,
            'byStatus' => $byStatus,
        ];
    }
}
