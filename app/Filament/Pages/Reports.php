<?php

namespace App\Filament\Pages;

use App\Filament\Support\Documents;
use App\Modules\Identity\Infrastructure\Models\User;
use App\Platform\Documents\SalesReport;
use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/**
 * «Informes»: resumen del mes y descarga de los informes en PDF. Solo superadministradores.
 */
class Reports extends Page
{
    protected string $view = 'filament.pages.reports';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static string|UnitEnum|null $navigationGroup = 'Análisis';

    protected static ?string $navigationLabel = 'Informes';

    protected static ?string $title = 'Informes';

    protected static ?int $navigationSort = 1;

    protected static ?string $slug = 'informes';

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user instanceof User && $user->isSuperadmin();
    }

    protected function getHeaderActions(): array
    {
        return [Documents::salesReport(), Documents::shippingReport(), Documents::productionSheet()];
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $now = CarbonImmutable::now('Europe/Madrid');

        return ['month' => SalesReport::data($now->startOfMonth(), $now), 'monthName' => $now->translatedFormat('F Y')];
    }
}
