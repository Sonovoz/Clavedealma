<?php

namespace App\Platform\Artists;

use App\Modules\Orders\Contracts\OrderStatus;
use App\Modules\Orders\Infrastructure\Models\OrderLine;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Storage;

/**
 * Ventas de un artista para su visor «Mis ventas» (ADR 0023): unidades, importe sin IVA
 * y comisión estimada, por producto y por día, con la comparación con el periodo
 * anterior. Solo pedidos pagados y reales (sin compras de prueba ni cancelados) y
 * **nunca datos de clientes**: solo líneas de producto.
 *
 * @phpstan-type Line array{date: string, order_id: int, product_id: int, name: string, reference: ?string, image: ?string, units: int, base: int, commission: int}
 * @phpstan-type Totals array{units: int, base: int, commission: int, orders: int}
 */
final class ArtistSales
{
    public const TZ = 'Europe/Madrid';

    public const PERIODS = [
        '7d' => 'Últimos 7 días',
        '30d' => 'Últimos 30 días',
        'month' => 'Este mes',
        'last_month' => 'Mes anterior',
        'year' => 'Este año',
    ];

    /** @var list<Line>|null */
    private ?array $lines = null;

    /** @var list<Line>|null */
    private ?array $previous = null;

    /**
     * @param  list<int>  $storeIds
     */
    public function __construct(
        public readonly array $storeIds,
        public readonly CarbonImmutable $from,
        public readonly CarbonImmutable $to,
        public readonly CarbonImmutable $previousFrom,
        public readonly CarbonImmutable $previousTo,
        public readonly string $label,
    ) {}

    /**
     * @param  list<int>  $storeIds
     */
    public static function for(array $storeIds, string $period, ?CarbonImmutable $now = null): self
    {
        $now = ($now ?? CarbonImmutable::now())->timezone(self::TZ);
        $today = $now->startOfDay();

        [$from, $to, $prevFrom, $prevTo] = match ($period) {
            '7d' => [$today->subDays(6), $now->endOfDay(), $today->subDays(13), $today->subDays(7)->endOfDay()],
            'month' => [$today->startOfMonth(), $now->endOfDay(), $today->subMonthNoOverflow()->startOfMonth(), $today->subMonthNoOverflow()->startOfMonth()->addDays($today->day - 1)->endOfDay()],
            'last_month' => [$today->subMonthNoOverflow()->startOfMonth(), $today->subMonthNoOverflow()->endOfMonth(), $today->subMonthsNoOverflow(2)->startOfMonth(), $today->subMonthsNoOverflow(2)->endOfMonth()],
            'year' => [$today->startOfYear(), $now->endOfDay(), $today->subYear()->startOfYear(), $today->subYear()->endOfDay()],
            default => [$today->subDays(29), $now->endOfDay(), $today->subDays(59), $today->subDays(30)->endOfDay()],
        };

        return new self($storeIds, $from, $to, $prevFrom, $prevTo, self::PERIODS[$period] ?? self::PERIODS['30d']);
    }

    /**
     * @return Totals
     */
    public function totals(): array
    {
        return self::sum($this->lines());
    }

    /**
     * @return Totals
     */
    public function previousTotals(): array
    {
        $this->previous ??= $this->load($this->previousFrom, $this->previousTo);

        return self::sum($this->previous);
    }

    /**
     * Un punto por día del periodo (también los días sin ventas).
     *
     * @return list<array{date: string, label: string, units: int, base: int, commission: int}>
     */
    public function daily(): array
    {
        $byDay = [];
        foreach ($this->lines() as $l) {
            $byDay[$l['date']]['units'] = ($byDay[$l['date']]['units'] ?? 0) + $l['units'];
            $byDay[$l['date']]['base'] = ($byDay[$l['date']]['base'] ?? 0) + $l['base'];
            $byDay[$l['date']]['commission'] = ($byDay[$l['date']]['commission'] ?? 0) + $l['commission'];
        }

        $days = [];
        for ($d = $this->from; $d <= $this->to; $d = $d->addDay()) {
            $key = $d->format('Y-m-d');
            $days[] = [
                'date' => $key,
                'label' => $d->translatedFormat('j M'),
                'units' => $byDay[$key]['units'] ?? 0,
                'base' => $byDay[$key]['base'] ?? 0,
                'commission' => $byDay[$key]['commission'] ?? 0,
            ];
        }

        return $days;
    }

    /**
     * Productos de más a menos vendidos, con su parte del total.
     *
     * @return list<array{product_id: int, name: string, reference: ?string, image: ?string, units: int, base: int, commission: int, share: int, best_day: string}>
     */
    public function byProduct(): array
    {
        $products = [];
        foreach ($this->lines() as $l) {
            $p = $products[$l['product_id']] ?? ['product_id' => $l['product_id'], 'name' => $l['name'], 'reference' => $l['reference'], 'image' => $l['image'], 'units' => 0, 'base' => 0, 'commission' => 0, 'days' => []];
            $p['units'] += $l['units'];
            $p['base'] += $l['base'];
            $p['commission'] += $l['commission'];
            $p['days'][$l['date']] = ($p['days'][$l['date']] ?? 0) + $l['units'];
            $products[$l['product_id']] = $p;
        }

        $total = max(1, array_sum(array_column($products, 'base')));
        $rows = [];
        foreach ($products as $p) {
            arsort($p['days']);
            $best = array_key_first($p['days']);
            unset($p['days']);
            $rows[] = $p + [
                'share' => (int) round($p['base'] * 100 / $total),
                'best_day' => CarbonImmutable::parse($best, self::TZ)->translatedFormat('j \d\e F'),
            ];
        }
        usort($rows, fn (array $a, array $b): int => [$b['base'], $b['units']] <=> [$a['base'], $a['units']]);

        return $rows;
    }

    /** Variación frente al periodo anterior, en %; null si antes no había nada que comparar. */
    public static function change(int $now, int $before): ?int
    {
        return $before === 0 ? null : (int) round(($now - $before) * 100 / $before);
    }

    public static function money(int $cents): string
    {
        return Money::fromCents($cents)->format();
    }

    /**
     * @return list<Line>
     */
    private function lines(): array
    {
        return $this->lines ??= $this->load($this->from, $this->to);
    }

    /**
     * @return list<Line>
     */
    private function load(CarbonImmutable $from, CarbonImmutable $to): array
    {
        if ($this->storeIds === []) {
            return [];
        }

        $lines = OrderLine::query()
            ->whereIn('store_id', $this->storeIds)
            ->whereHas('order', fn ($q) => $q->whereIn('status', OrderStatus::paid())->where('is_test', false)
                ->whereBetween('paid_at', [$from->utc(), $to->utc()]))
            ->with('order:id,paid_at')
            ->get();

        $rows = [];
        foreach ($lines as $line) {
            $base = Money::fromCents($line->total_cents)->withoutVat($line->vat_rate_bps)->cents;
            $rows[] = [
                'date' => $line->order->paid_at?->timezone(self::TZ)->format('Y-m-d') ?? $from->format('Y-m-d'),
                'order_id' => $line->order_id,
                'product_id' => $line->product_id,
                'name' => $line->product_name,
                'reference' => $line->reference,
                'image' => $line->image_path === null ? null : Storage::disk('public')->url($line->image_path),
                'units' => $line->quantity,
                'base' => $base,
                'commission' => intdiv($base * $line->commission_bps + 5000, 10000),
            ];
        }

        return $rows;
    }

    /**
     * @param  list<Line>  $lines
     * @return Totals
     */
    private static function sum(array $lines): array
    {
        return [
            'units' => array_sum(array_column($lines, 'units')),
            'base' => array_sum(array_column($lines, 'base')),
            'commission' => array_sum(array_column($lines, 'commission')),
            'orders' => count(array_unique(array_column($lines, 'order_id'))),
        ];
    }
}
