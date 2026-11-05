<?php

namespace App\Filament\ArtistWidgets;

use Filament\Widgets\Widget;

/** «Tus productos»: de más a menos vendido, con su parte del total y su mejor día. */
class ArtistTopProducts extends Widget
{
    use ReadsArtistSales;

    protected static bool $isLazy = false;

    protected string $view = 'filament.artist.top-products';

    protected int|string|array $columnSpan = 'full';

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        return ['products' => $this->sales()->byProduct()];
    }
}
