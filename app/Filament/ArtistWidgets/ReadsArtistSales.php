<?php

namespace App\Filament\ArtistWidgets;

use App\Filament\Pages\ArtistSalesDashboard;
use App\Modules\Identity\Infrastructure\Models\User;
use App\Platform\Artists\ArtistSales;
use App\Platform\Artists\ArtistScope;
use Filament\Widgets\Concerns\InteractsWithPageFilters;

/**
 * Los recuadros de «Mis ventas» leen el periodo (y, el superadmin, el artista) del filtro
 * de la página, y solo enseñan las tiendas del artista.
 */
trait ReadsArtistSales
{
    use InteractsWithPageFilters;

    public static function canView(): bool
    {
        return ArtistSalesDashboard::canAccess();
    }

    protected function sales(): ArtistSales
    {
        $user = auth()->user();
        $chosen = $this->pageFilters['artist'] ?? null;
        $artist = ArtistScope::artist($user instanceof User ? $user : null, $chosen === null || $chosen === '' ? null : (int) $chosen);

        return ArtistSales::for(ArtistScope::storeIds($artist), (string) ($this->pageFilters['period'] ?? '30d'));
    }
}
