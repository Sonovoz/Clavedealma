<?php

namespace App\Platform\Minimedia;

use App\Modules\Orders\Infrastructure\Models\Order;
use App\Modules\Orders\Infrastructure\Models\OrderLine;
use App\Modules\Payments\Infrastructure\Models\Refund;
use App\Platform\Settings\Settings;
use App\Support\Money;
use Carbon\CarbonImmutable;

/**
 * Comisión de Minimedia sobre las ventas de todas las tiendas (ADR 0029).
 *
 * - **Base**: lo vendido en piezas, sin IVA ni envío y ya con los cupones aplicados, de los
 *   pedidos cobrados en el mes (fecha de pago, hora de Madrid). Las compras de prueba no cuentan.
 * - **Menos lo devuelto en el mes**: la parte de piezas (sin IVA) del dinero devuelto ese mes.
 *   El envío solo se devuelve cuando vuelve todo, así que cada devolución descuenta primero
 *   piezas y el resto es envío.
 * - **Comisión**: el porcentaje (6 % = 600 puntos básicos) sobre lo neto.
 *
 * @phpstan-type StoreRow array{store_id: int, store: string, orders: int, sales_base: int, refunds_base: int, net_base: int, commission: int}
 * @phpstan-type Result array{month: string, label: string, rate_bps: int, orders: int, sales_base: int, refunds_base: int, net_base: int, commission: int, stores: list<StoreRow>}
 */
final class MinimediaCommission
{
    public const TZ = 'Europe/Madrid';

    public const KEY = 'minimedia';

    /** Porcentaje en puntos básicos (600 = 6 %), del panel o de config. */
    public static function rateBps(): int
    {
        return (int) (Settings::get(self::KEY)['rate_bps'] ?? config('clavedealma.minimedia.rate_bps', 600));
    }

    public static function email(): string
    {
        return (string) (Settings::get(self::KEY)['email'] ?? config('clavedealma.minimedia.email', 'info@minimedia.es'));
    }

    /** Mes de una fecha («2026-10»), en hora de Madrid. */
    public static function monthKey(CarbonImmutable $date): string
    {
        return $date->timezone(self::TZ)->format('Y-m');
    }

    public static function label(string $month): string
    {
        return ucfirst(CarbonImmutable::createFromFormat('!Y-m', $month, self::TZ)->translatedFormat('F \d\e Y'));
    }

    /**
     * @return Result
     */
    public static function compute(string $month, ?int $rateBps = null): array
    {
        $rateBps ??= self::rateBps();
        $from = CarbonImmutable::createFromFormat('!Y-m', $month, self::TZ)->startOfMonth();
        $to = $from->endOfMonth();

        /** @var array<int, StoreRow> $stores */
        $stores = [];
        $row = function (int $id, string $name) use (&$stores): void {
            $stores[$id] ??= ['store_id' => $id, 'store' => $name, 'orders' => 0, 'sales_base' => 0, 'refunds_base' => 0, 'net_base' => 0, 'commission' => 0];
        };

        // Ventas: líneas de pedidos reales cobrados en el mes.
        $orders = [];
        $lines = OrderLine::query()
            ->whereHas('order', fn ($q) => $q->where('is_test', false)->whereBetween('paid_at', [$from->utc(), $to->utc()]))
            ->get(['id', 'order_id', 'store_id', 'store_name', 'total_cents', 'vat_rate_bps']);
        $perStoreOrders = [];
        foreach ($lines as $line) {
            $row($line->store_id, $line->store_name);
            $stores[$line->store_id]['sales_base'] += Money::fromCents($line->total_cents)->withoutVat($line->vat_rate_bps)->cents;
            $perStoreOrders[$line->store_id][$line->order_id] = true;
            $orders[$line->order_id] = true;
        }
        foreach ($perStoreOrders as $storeId => $ids) {
            $stores[$storeId]['orders'] = count($ids);
        }

        // Devoluciones de dinero hechas en el mes (de pedidos reales).
        $refunds = Refund::query()->where('status', Refund::SUCCEEDED)
            ->whereBetween('created_at', [$from->utc(), $to->utc()])
            ->whereIn('order_id', Order::query()->select('id')->where('is_test', false))
            ->orderBy('id')->get(['id', 'order_id', 'amount_cents']);
        foreach ($refunds as $refund) {
            foreach (self::refundedGoodsBase($refund) as $storeId => [$name, $base]) {
                $row($storeId, $name);
                $stores[$storeId]['refunds_base'] += $base;
            }
        }

        foreach ($stores as &$s) {
            $s['net_base'] = $s['sales_base'] - $s['refunds_base'];
            $s['commission'] = self::percent($s['net_base'], $rateBps);
        }
        unset($s);
        usort($stores, fn (array $a, array $b): int => [$b['net_base'], $a['store']] <=> [$a['net_base'], $b['store']]);

        $sales = array_sum(array_column($stores, 'sales_base'));
        $refunded = array_sum(array_column($stores, 'refunds_base'));

        return [
            'month' => $month,
            'label' => self::label($month),
            'rate_bps' => $rateBps,
            'orders' => count($orders),
            'sales_base' => $sales,
            'refunds_base' => $refunded,
            'net_base' => $sales - $refunded,
            'commission' => self::percent($sales - $refunded, $rateBps),
            'stores' => $stores,
        ];
    }

    /** Porcentaje de un importe en céntimos, redondeado al céntimo (también negativo). */
    public static function percent(int $cents, int $bps): int
    {
        $value = intdiv(abs($cents) * $bps + 5000, 10000);

        return $cents < 0 ? -$value : $value;
    }

    /**
     * Base sin IVA de las piezas que devuelve una devolución de dinero, por tienda.
     * Piezas primero: lo que pasa del total de piezas del pedido es envío.
     *
     * @return array<int, array{0: string, 1: int}>
     */
    private static function refundedGoodsBase(Refund $refund): array
    {
        $lines = OrderLine::query()->where('order_id', $refund->order_id)->orderBy('id')->get(['id', 'store_id', 'store_name', 'total_cents', 'vat_rate_bps']);
        $goods = (int) $lines->sum('total_cents');
        if ($goods <= 0) {
            return [];
        }

        $before = (int) Refund::query()->where('order_id', $refund->order_id)->where('status', Refund::SUCCEEDED)->where('id', '<', $refund->id)->sum('amount_cents');
        $part = min($before + $refund->amount_cents, $goods) - min($before, $goods);
        if ($part <= 0) {
            return [];
        }

        // Se reparte entre las líneas en proporción a su importe (el resto, a la mayor).
        $shares = [];
        foreach ($lines as $line) {
            $shares[$line->id] = intdiv($part * $line->total_cents, $goods);
        }
        $largest = $lines->sortByDesc('total_cents')->first();
        $shares[$largest->id] += $part - array_sum($shares);

        $out = [];
        foreach ($lines as $line) {
            $base = Money::fromCents($shares[$line->id])->withoutVat($line->vat_rate_bps)->cents;
            $out[$line->store_id] = [$line->store_name, ($out[$line->store_id][1] ?? 0) + $base];
        }

        return $out;
    }
}
