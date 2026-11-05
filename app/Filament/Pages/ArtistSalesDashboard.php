<?php

namespace App\Filament\Pages;

use App\Filament\ArtistWidgets\ArtistDailyTable;
use App\Filament\ArtistWidgets\ArtistKpis;
use App\Filament\ArtistWidgets\ArtistSalesChart;
use App\Filament\ArtistWidgets\ArtistTopProducts;
use App\Modules\Identity\Infrastructure\Models\User;
use App\Platform\Artists\ArtistSales;
use App\Platform\Artists\ArtistScope;
use BackedEnum;
use Filament\Forms\Components\Select;
use Filament\Pages\Dashboard;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;

/**
 * «Mis ventas»: el escritorio del artista (ADR 0023). Sus ventas por producto y por día,
 * con la comparación con el periodo anterior, para que de un vistazo sepa cómo va.
 * Sin datos de clientes. El superadministrador lo ve como cualquier artista.
 */
class ArtistSalesDashboard extends Dashboard
{
    use HasFiltersForm;

    protected static string $routePath = 'mis-ventas';

    protected static ?string $title = 'Mis ventas';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPresentationChartLine;

    protected static ?int $navigationSort = -3;

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user instanceof User && ($user->isArtist() || $user->isSuperadmin());
    }

    public static function getNavigationLabel(): string
    {
        return self::isAdmin() ? 'Ventas por artista' : 'Mis ventas';
    }

    public static function getNavigationGroup(): ?string
    {
        return self::isAdmin() ? 'Tiendas' : null;
    }

    public function getTitle(): string|Htmlable
    {
        return self::isAdmin() ? 'Ventas por artista' : 'Mis ventas';
    }

    public function getSubheading(): ?string
    {
        $artist = ArtistScope::artist($this->user(), $this->chosenArtist());
        if ($artist === null) {
            return 'Tu usuario aún no está enlazado a un artista. Escríbenos y lo dejamos listo.';
        }

        return self::isAdmin()
            ? "Lo que ve {$artist->display_name} en su panel."
            : "Hola, {$artist->display_name}. Así van tus ventas en Clave de Alma.";
    }

    public function filtersForm(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('period')
                ->label('Periodo')
                ->options(ArtistSales::PERIODS)
                ->default('30d')
                ->selectablePlaceholder(false),
            Select::make('artist')
                ->label('Artista')
                ->options(fn (): array => ArtistScope::options())
                ->visible(fn (): bool => self::isAdmin())
                ->placeholder('El primero'),
        ]);
    }

    public function getWidgets(): array
    {
        return [ArtistKpis::class, ArtistSalesChart::class, ArtistTopProducts::class, ArtistDailyTable::class];
    }

    public function getColumns(): int|array
    {
        return 1;
    }

    private function chosenArtist(): ?int
    {
        $id = $this->filters['artist'] ?? null;

        return $id === null || $id === '' ? null : (int) $id;
    }

    private function user(): ?User
    {
        $user = auth()->user();

        return $user instanceof User ? $user : null;
    }

    private static function isAdmin(): bool
    {
        $user = auth()->user();

        return $user instanceof User && $user->isSuperadmin();
    }
}
