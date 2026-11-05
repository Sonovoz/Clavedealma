<?php

namespace App\Filament\ArtistWidgets;

use App\Platform\Artists\ArtistSales;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/** Las cuatro cifras de «Mis ventas», con su línea de tendencia y la comparación. */
class ArtistKpis extends StatsOverviewWidget
{
    use ReadsArtistSales;

    protected static bool $isLazy = false;

    protected ?string $pollingInterval = null;

    protected function getHeading(): ?string
    {
        return $this->sales()->label;
    }

    protected function getDescription(): ?string
    {
        $s = $this->sales();

        return 'Del '.$s->from->translatedFormat('j \d\e F').' al '.$s->to->translatedFormat('j \d\e F').'. Pedidos pagados. La comisión se liquida cada mes y ahí se descuentan las devoluciones.';
    }

    /**
     * @return array<Stat>
     */
    protected function getStats(): array
    {
        $s = $this->sales();
        $now = $s->totals();
        $before = $s->previousTotals();
        $daily = $s->daily();
        $series = fn (string $k): array => array_map(fn (array $d): int => $d[$k], $daily);

        $stat = function (string $label, string $value, int $current, int $previous, array $chart) {
            $change = ArtistSales::change($current, $previous);
            $text = match (true) {
                $change === null && $current > 0 => 'Primeras ventas del periodo',
                $change === null => 'Sin ventas todavía',
                $change > 0 => "+{$change} % frente al periodo anterior",
                $change < 0 => "{$change} % frente al periodo anterior",
                default => 'Igual que el periodo anterior',
            };

            return Stat::make($label, $value)
                ->description($text)
                ->descriptionIcon(match (true) {
                    $change !== null && $change > 0 => Heroicon::ArrowTrendingUp,
                    $change !== null && $change < 0 => Heroicon::ArrowTrendingDown,
                    default => Heroicon::Minus,
                })
                ->color(match (true) {
                    $change !== null && $change > 0, $change === null && $current > 0 => 'success',
                    $change !== null && $change < 0 => 'danger',
                    default => 'gray',
                })
                ->chart($chart);
        };

        return [
            $stat('Unidades vendidas', (string) $now['units'], $now['units'], $before['units'], $series('units')),
            $stat('Ventas (sin IVA)', ArtistSales::money($now['base']), $now['base'], $before['base'], $series('base')),
            $stat('Tu comisión estimada', ArtistSales::money($now['commission']), $now['commission'], $before['commission'], $series('commission')),
            $stat('Pedidos', (string) $now['orders'], $now['orders'], $before['orders'], $series('units')),
        ];
    }
}
