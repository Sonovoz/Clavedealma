<?php

namespace App\Filament\Pages;

use App\Modules\Identity\Infrastructure\Models\User;
use App\Platform\Development\ProjectTree;
use App\Platform\Development\SystemMap;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use UnitEnum;

/**
 * «Desarrollo»: cómo está hecho el proyecto (docs/desarrollo/README.md), el árbol del
 * sistema (generado del código), las decisiones técnicas (docs/adr) y el historial de entregas
 * (docs/desarrollo/historial.md). Solo superadministradores.
 */
class Development extends Page
{
    protected string $view = 'filament.pages.development';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCodeBracketSquare;

    protected static string|UnitEnum|null $navigationGroup = 'Herramientas';

    protected static ?int $navigationSort = 5;

    protected static ?string $navigationLabel = 'Desarrollo';

    protected static ?string $title = 'Desarrollo';

    protected static ?string $slug = 'desarrollo';

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user instanceof User && $user->isSuperadmin();
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $md = fn (string $text): string => Str::markdown($text, ['html_input' => 'strip', 'allow_unsafe_links' => false]);

        $adrs = collect(File::glob(base_path('docs/adr/*.md')))->sort()->map(function (string $file) use ($md): array {
            $text = (string) File::get($file);
            $title = trim((string) Str::of($text)->before("\n")->replace('#', ''));

            return ['title' => $title, 'html' => $md((string) Str::of($text)->after("\n"))];
        })->values()->all();

        return [
            'guide' => $md((string) File::get(base_path('docs/desarrollo/README.md'))),
            'history' => $md((string) File::get(base_path('docs/desarrollo/historial.md'))),
            'map' => SystemMap::TREE,
            'tree' => ProjectTree::render(),
            'adrs' => $adrs,
        ];
    }
}
