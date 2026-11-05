<?php

namespace App\Filament\ArtistWidgets;

use Filament\Widgets\Widget;

/** «Día a día»: los días con ventas del periodo, del más reciente al más antiguo. */
class ArtistDailyTable extends Widget
{
    use ReadsArtistSales;

    protected static bool $isLazy = false;

    protected string $view = 'filament.artist.daily';

    protected int|string|array $columnSpan = 'full';

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $sales = $this->sales();
        $days = array_reverse(array_values(array_filter($sales->daily(), fn (array $d): bool => $d['units'] > 0)));

        return ['days' => $days, 'totals' => $sales->totals()];
    }
}
