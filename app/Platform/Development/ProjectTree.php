<?php

namespace App\Platform\Development;

use Illuminate\Support\Facades\File;

/**
 * Árbol de carpetas del proyecto, generado en el momento a partir del código
 * (nunca se queda desfasado). Los módulos se muestran con sus capas y clases.
 */
final class ProjectTree
{
    /** Qué es cada carpeta principal. */
    private const NOTES = [
        'app' => 'código PHP',
        'app/Modules' => 'negocio: un módulo por área (solo se hablan por Contracts y eventos)',
        'app/Filament' => 'panel /admin (capa exterior)',
        'app/Http' => 'controladores de tienda y compra (capa exterior)',
        'app/Storefront' => 'lectura del catálogo, SEO y direcciones para las tiendas',
        'app/Platform' => 'servicios transversales: IA, copias, PDF, documentos, seguridad…',
        'app/Support' => 'utilidades sin negocio (Money)',
        'app/Policies' => 'permisos del panel (strictAuthorization)',
        'app/Providers' => 'arranque: contratos, políticas y panel',
        'resources/views' => 'plantillas Blade',
        'resources/css' => 'estilos (Vite)',
        'resources/js' => 'JavaScript (Vite)',
        'resources/designs' => 'imágenes propias de cada diseño de tienda',
        'config' => 'configuración (sin datos reales: van en .env o en el panel)',
        'routes' => 'web.php (tiendas, compra, SEO) y console.php (tareas programadas)',
        'database' => 'migraciones comunes, factories y seeders de demostración',
        'tests' => 'Pest: Architecture, Feature y Unit',
        'docs' => 'guías y decisiones (ADR)',
    ];

    /** Lo que dice cada capa de un módulo. */
    private const LAYERS = [
        'Contracts' => 'lo que pueden usar los demás',
        'Domain' => 'reglas puras',
        'Application' => 'casos de uso',
        'Infrastructure' => 'modelos, listeners, drivers',
        'Database' => 'migraciones',
    ];

    public static function render(): string
    {
        $lines = ['clavedealma/'];
        $roots = ['app', 'resources', 'config', 'routes', 'database', 'tests', 'docs'];
        foreach ($roots as $i => $root) {
            self::walk($root, '', $i === count($roots) - 1, $lines, 0);
        }

        return implode("\n", $lines);
    }

    /**
     * @param  list<string>  $lines
     */
    private static function walk(string $relative, string $prefix, bool $last, array &$lines, int $depth): void
    {
        $path = base_path($relative);
        $name = basename($relative);
        $note = self::NOTES[$relative] ?? null;
        $parent = basename(dirname($relative));

        if (dirname($relative) !== '.' && basename(dirname($relative, 2)) === 'Modules' && isset(self::LAYERS[$name])) {
            $note = self::LAYERS[$name];
        }

        $lines[] = $prefix.($last ? '└── ' : '├── ').$name.'/'.($note ? '  — '.$note : '');
        $childPrefix = $prefix.($last ? '    ' : '│   ');

        // Dentro de un módulo: sus capas y, en cada capa, los nombres de las clases.
        $inModuleLayer = basename(dirname($relative, 2)) === 'Modules' && isset(self::LAYERS[$name]);
        if ($inModuleLayer || $depth >= self::maxDepth($relative)) {
            foreach (self::summary($path) as $row) {
                $lines[] = $childPrefix.'    '.$row;
            }

            return;
        }

        $dirs = collect(File::directories($path))->map(fn (string $d): string => $relative.'/'.basename($d))
            ->reject(fn (string $d): bool => in_array(basename($d), ['vendor', 'node_modules', 'storage'], true))
            ->sort()->values();
        $files = $parent === 'Modules' || $relative === 'app/Modules' ? [] : self::classes($path, 8);

        if ($files !== []) {
            $lines[] = $childPrefix.($dirs->isEmpty() ? '    ' : '│   ').implode(', ', $files);
        }
        foreach ($dirs as $k => $dir) {
            self::walk($dir, $childPrefix, $k === $dirs->count() - 1, $lines, $depth + 1);
        }
    }

    private static function maxDepth(string $relative): int
    {
        return match (true) {
            str_starts_with($relative, 'app/Modules') => 3,
            str_starts_with($relative, 'app') => 2,
            str_starts_with($relative, 'resources/views') => 3,
            default => 1,
        };
    }

    /**
     * Ficheros de la carpeta y, por cada subcarpeta, «Sub: ficheros» (o solo su nombre).
     *
     * @return list<string>
     */
    private static function summary(string $path): array
    {
        $rows = [];
        $files = self::classes($path);
        if ($files !== []) {
            $rows[] = implode(', ', $files);
        }
        foreach (collect(File::directories($path))->sort() as $dir) {
            $inner = self::classes($dir, 12);
            $rows[] = basename($dir).'/'.($inner === [] ? '' : ': '.implode(', ', $inner));
        }

        return $rows;
    }

    /**
     * @return list<string>
     */
    private static function classes(string $path, int $limit = 40): array
    {
        $names = collect(File::files($path))
            ->map(fn ($f): string => str_replace(['.blade.php', '.php'], '', $f->getFilename()))
            ->reject(fn (string $n): bool => str_starts_with($n, '.'))
            ->sort()->values();

        if ($names->count() > $limit) {
            return [...$names->take($limit)->all(), '… ('.($names->count() - $limit).' más)'];
        }

        return $names->all();
    }
}
