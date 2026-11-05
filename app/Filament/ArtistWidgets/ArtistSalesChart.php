<?php

namespace App\Filament\ArtistWidgets;

use Filament\Support\RawJs;
use Filament\Widgets\ChartWidget;

/** «Ventas por día»: importe sin IVA (línea) y unidades (barras) de cada día. */
class ArtistSalesChart extends ChartWidget
{
    use ReadsArtistSales;

    protected static bool $isLazy = false;

    protected ?string $heading = 'Ventas por día';

    protected ?string $maxHeight = '320px';

    protected int|string|array $columnSpan = 'full';

    protected function getType(): string
    {
        return 'line';
    }

    /**
     * @return array<string, mixed>
     */
    protected function getData(): array
    {
        $daily = $this->sales()->daily();

        return [
            'labels' => array_map(fn (array $d): string => $d['label'], $daily),
            'datasets' => [
                [
                    'label' => 'Ventas sin IVA (€)',
                    'data' => array_map(fn (array $d): float => round($d['base'] / 100, 2), $daily),
                    'borderColor' => '#d97706',
                    'backgroundColor' => 'rgba(217, 119, 6, .12)',
                    'fill' => true,
                    'tension' => 0.35,
                    'pointRadius' => 2,
                    'yAxisID' => 'y',
                ],
                [
                    'type' => 'bar',
                    'label' => 'Unidades',
                    'data' => array_map(fn (array $d): int => $d['units'], $daily),
                    'backgroundColor' => 'rgba(120, 113, 108, .35)',
                    'borderRadius' => 3,
                    'yAxisID' => 'y1',
                ],
            ],
        ];
    }

    protected function getOptions(): RawJs
    {
        return RawJs::make(<<<'JS'
        {
            interaction: { mode: 'index', intersect: false },
            plugins: { legend: { position: 'bottom' } },
            scales: {
                y: { beginAtZero: true, ticks: { callback: (v) => v.toLocaleString('es-ES') + ' €' } },
                y1: { beginAtZero: true, position: 'right', grid: { drawOnChartArea: false }, ticks: { precision: 0 } },
            },
        }
        JS);
    }
}
